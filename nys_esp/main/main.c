#include "freertos/FreeRTOS.h"
#include "freertos/task.h"

#include "esp_err.h"
#include "esp_log.h"
#include "esp_netif.h"
#include "esp_event.h"
#include "esp_sleep.h"
#include "nvs_flash.h"

#include "driver/uart.h"
#include "driver/gpio.h"

// ─── Component headers ────────────────────────────────────────────────────────
#include "../components/nys_common/nys_common.h"
#include "../components/comms/comms.h"
#include "../components/wifi/wifi.h"
#include "../components/gps/gps.h"
#include "../components/input/input.h"
#include "../components/web/web_server.h"

extern nys_cfg_t s_cfg;

static const char *TAG = "MAIN";

// ─── RTC memory — survives deep sleep ────────────────────────────────────────
RTC_DATA_ATTR static nys_rtc_data_t s_rtc;

// ─── Common hardware init (shared by both modes) ────────────────────────────

static void hw_init_led(void)
{
    gpio_config_t led_conf = {
        .pin_bit_mask  = 1ULL << LED_GPIO,
        .mode          = GPIO_MODE_OUTPUT,
        .pull_up_en    = GPIO_PULLUP_DISABLE,
        .pull_down_en  = GPIO_PULLDOWN_DISABLE,
        .intr_type     = GPIO_INTR_DISABLE,
    };
    ESP_ERROR_CHECK(gpio_config(&led_conf));
    gpio_set_level(LED_GPIO, LED_OFF);
}

static void hw_init_gps_uart(void)
{
    uart_config_t uart_config = {
        .baud_rate  = GPS_BAUD_RATE,
        .data_bits  = UART_DATA_8_BITS,
        .parity     = UART_PARITY_DISABLE,
        .stop_bits  = UART_STOP_BITS_1,
        .flow_ctrl  = UART_HW_FLOWCTRL_DISABLE,
        .source_clk = UART_SCLK_DEFAULT,
    };
    ESP_ERROR_CHECK(uart_driver_install(GPS_UART, 2048, 0, 0, NULL, 0));
    ESP_ERROR_CHECK(uart_param_config(GPS_UART, &uart_config));
    ESP_ERROR_CHECK(uart_set_rx_timeout(GPS_UART, 10));

#if GPS_SWAP_RX_TX
    ESP_ERROR_CHECK(uart_set_pin(GPS_UART, GPS_RX_GPIO, GPS_TX_GPIO,
                                 UART_PIN_NO_CHANGE, UART_PIN_NO_CHANGE));
#else
    ESP_ERROR_CHECK(uart_set_pin(GPS_UART, GPS_TX_GPIO, GPS_RX_GPIO,
                                 UART_PIN_NO_CHANGE, UART_PIN_NO_CHANGE));
#endif

    ESP_LOGI(TAG, "GPS UART on RX=%d TX=%d @ %d baud",
             (int)GPS_RX_GPIO, (int)GPS_TX_GPIO, GPS_BAUD_RATE);
}

// ─── Deep sleep flow ─────────────────────────────────────────────────────────
//
// GPIO wake  → WiFi → send input change event → sleep  (no GPS)
// Timer wake → check intervals:
//              - location due?  → GPS → send location
//              - heartbeat due? → send heartbeat
//              → sleep

// Helper: connect WiFi with retries. Returns true if connected.
static bool ds_wifi_connect(void)
{
    nys_network_t nets[NYS_MAX_NETWORKS];
    int net_count = cfg_load_networks(nets);

    if (net_count == 0 || nets[0].ssid[0] == 0) {
        // No networks — start AP so user can configure
        ESP_LOGW(TAG, "No saved networks — starting AP for config");
        wifi_ensure_inited(true, true);
        wifi_auto_connect_on_boot();
        web_start_server();
        vTaskDelay(pdMS_TO_TICKS(NYS_AP_WINDOW_MS));
        return false;
    }

    wifi_ensure_inited(true, false);  // STA only

    for (int attempt = 1; attempt <= 3; attempt++) {
        for (int n = 0; n < net_count; n++) {
            if (nets[n].ssid[0] == 0) continue;
            ESP_LOGI(TAG, "WiFi attempt %d: trying '%s'", attempt, nets[n].ssid);
            wifi_connect_to_network(nets[n].ssid, nets[n].password);

            EventBits_t bits = xEventGroupWaitBits(
                s_wifi_event_group, WIFI_CONNECTED_BIT,
                false, true, pdMS_TO_TICKS(10000));
            if (bits & WIFI_CONNECTED_BIT) {
                ESP_LOGI(TAG, "WiFi connected to '%s'", nets[n].ssid);
                return true;
            }
        }
    }

    ESP_LOGE(TAG, "WiFi failed after 3 attempts");
    return false;
}

// Helper: enter deep sleep with timer + GPIO wake sources
static void ds_enter_sleep(void)
{
    uint32_t sleep_s = s_cfg.location_interval_s > 0
                     ? s_cfg.location_interval_s
                     : NYS_DEEP_SLEEP_DEFAULT_S;

    gpio_set_level(LED_GPIO, LED_OFF);

    // Record current GPIO levels for change detection on next wake
    s_rtc.last_input1_level = (uint8_t)gpio_get_level(TEST_INPUT_GPIO);
    s_rtc.last_input2_level = (uint8_t)gpio_get_level(TEST_OUTPUT_GPIO);

    // Maintain pull-ups during deep sleep (GPIO isolation disables them)
    gpio_sleep_set_pull_mode(TEST_INPUT_GPIO, GPIO_PULLUP_ONLY);
    gpio_sleep_set_pull_mode(TEST_OUTPUT_GPIO, GPIO_PULLUP_ONLY);

    // GPIO wake: both inputs are pull-up, wake on LOW (triggered)
    esp_deep_sleep_enable_gpio_wakeup(
        (1ULL << TEST_INPUT_GPIO) | (1ULL << TEST_OUTPUT_GPIO),
        ESP_GPIO_WAKEUP_GPIO_LOW);

    // Timer wake
    esp_sleep_enable_timer_wakeup((uint64_t)sleep_s * 1000000ULL);

    ESP_LOGI(TAG, "Sleeping %us | GPIO%d=%d GPIO%d=%d",
             (unsigned)sleep_s,
             TEST_INPUT_GPIO, s_rtc.last_input1_level,
             TEST_OUTPUT_GPIO, s_rtc.last_input2_level);

    vTaskDelay(pdMS_TO_TICKS(100)); // flush logs
    esp_deep_sleep_start();
}

static void deep_sleep_flow(void)
{
    s_rtc.boot_count++;
    esp_sleep_wakeup_cause_t wake_cause = esp_sleep_get_wakeup_cause();
    ESP_LOGI(TAG, "=== Boot #%u | wake: %d ===",
             (unsigned)s_rtc.boot_count, (int)wake_cause);

    hw_init_led();

    // Configure input GPIOs (needed for reading state + wake config)
    gpio_config_t in_conf = {
        .pin_bit_mask  = (1ULL << TEST_INPUT_GPIO) | (1ULL << TEST_OUTPUT_GPIO),
        .mode          = GPIO_MODE_INPUT,
        .pull_up_en    = GPIO_PULLUP_ENABLE,
        .pull_down_en  = GPIO_PULLDOWN_DISABLE,
        .intr_type     = GPIO_INTR_DISABLE,
    };
    ESP_ERROR_CHECK(gpio_config(&in_conf));

    // ══════════════════════════════════════════════════════════════════════
    // GPIO WAKE — input state changed, send event only (no GPS)
    // ══════════════════════════════════════════════════════════════════════
    if (wake_cause == ESP_SLEEP_WAKEUP_GPIO) {
        int in1 = gpio_get_level(TEST_INPUT_GPIO);
        int in2 = gpio_get_level(TEST_OUTPUT_GPIO);
        ESP_LOGI(TAG, "GPIO wake: in1=%d (was %d), in2=%d (was %d)",
                 in1, s_rtc.last_input1_level, in2, s_rtc.last_input2_level);

        bool in1_changed = (in1 != s_rtc.last_input1_level);
        bool in2_changed = (in2 != s_rtc.last_input2_level);

        if (!in1_changed && !in2_changed) {
            ESP_LOGW(TAG, "GPIO wake but no change detected — back to sleep");
            ds_enter_sleep();
        }

        // Connect WiFi to send the event
        if (!ds_wifi_connect()) {
            // Can't send now — update RTC levels anyway so we don't re-trigger
            s_rtc.last_input1_level = (uint8_t)in1;
            s_rtc.last_input2_level = (uint8_t)in2;
            ds_enter_sleep();
        }

        // Send input change for whichever GPIO(s) changed
        if (in1_changed) {
            ESP_LOGI(TAG, "Sending input1 change: %d", in1);
            http_send_input_change(&s_cfg, in1);
            s_rtc.last_input1_level = (uint8_t)in1;
        }
        if (in2_changed) {
            ESP_LOGI(TAG, "Sending input2 change: %d", in2);
            http_send_input_change(&s_cfg, in2);
            s_rtc.last_input2_level = (uint8_t)in2;
        }

        // Also drain any queued location data while we have WiFi
        uint32_t unsent = queue_count_unsent();
        if (unsent > 0) {
            ESP_LOGI(TAG, "Draining %u queued records while connected", (unsigned)unsent);
            for (int i = 0; i < (int)unsent; i++) queue_drain_step();
        }

        ds_enter_sleep();  // back to sleep — no GPS needed
    }

    // ══════════════════════════════════════════════════════════════════════
    // TIMER WAKE (or first boot) — check if location / heartbeat are due
    // ══════════════════════════════════════════════════════════════════════

    // Seed RTC from NVS on first power-on (RTC zeroed on power reset)
    if (gps_nvs_load_last_fix() == ESP_OK) {
        gps_fix_t f = {0};
        if (gps_get_last_fix(&f) && f.has_fix && !s_rtc.has_fix) {
            s_rtc.lat     = (float)f.lat_deg;
            s_rtc.lng     = (float)f.lon_deg;
            s_rtc.has_fix = true;
            ESP_LOGI(TAG, "RTC seeded from NVS: lat=%.6f lon=%.6f", f.lat_deg, f.lon_deg);
        }
    }

    // ── GPS: get fix ─────────────────────────────────────────────────────
    hw_init_gps_uart();

    int gps_timeout = s_rtc.has_fix ? GPS_FIX_TIMEOUT_SHORT_S : GPS_FIX_TIMEOUT_S;
    ESP_LOGI(TAG, "GPS timeout: %ds (has_prev_fix=%d)", gps_timeout, s_rtc.has_fix);

    gps_power_on();
    bool got_fix = gps_try_get_fix(gps_timeout);

    if (got_fix) {
        gps_fix_t fix = {0};
        gps_get_last_fix(&fix);
        s_rtc.lat     = (float)fix.lat_deg;
        s_rtc.lng     = (float)fix.lon_deg;
        s_rtc.has_fix = true;
        ESP_LOGI(TAG, "Fix: lat=%.6f lon=%.6f sats=%d",
                 fix.lat_deg, fix.lon_deg, fix.sats_used);
    } else {
        ESP_LOGW(TAG, "No fix — using last known if available");
    }

    gps_power_off();

    // ── WiFi connect ─────────────────────────────────────────────────────
    if (!ds_wifi_connect()) {
        queue_push_sample();  // save for next cycle
        ds_enter_sleep();
    }

    // SNTP time sync
    time_maybe_start_sntp();
    vTaskDelay(pdMS_TO_TICKS(2000));

    // ── Send location data (queued + current) ────────────────────────────
    send_all_pending(&s_cfg);

    // ── Heartbeat — only when interval has elapsed ───────────────────────
    {
        int64_t now_epoch   = time_now_epoch_s();
        int64_t hb_interval = s_cfg.heartbeat_interval_s > 0
                            ? (int64_t)s_cfg.heartbeat_interval_s : 3600;
        int64_t since_last  = now_epoch - s_rtc.last_heartbeat_epoch;

        if (s_rtc.last_heartbeat_epoch == 0 || since_last >= hb_interval) {
            ESP_LOGI(TAG, "Heartbeat due (every %llds, last %llds ago)",
                     (long long)hb_interval, (long long)since_last);
            if (http_send_heartbeat(&s_cfg) == ESP_OK) {
                s_rtc.last_heartbeat_epoch = now_epoch;
            }
        } else {
            ESP_LOGI(TAG, "Heartbeat not due (%llds remaining)",
                     (long long)(hb_interval - since_last));
        }
    }

    ds_enter_sleep();
}

// ─── Always-on flow (original behavior) ──────────────────────────────────────

static void always_on_flow(void)
{
    hw_init_led();

    // WiFi
    wifi_ensure_inited(true, true);
    if (!s_cfg.has_wifi) {
        ESP_LOGW(TAG, "No WiFi configured – starting setup portal");
        wifi_auto_connect_on_boot();
    } else {
        ESP_LOGI(TAG, "Connecting to configured WiFi");
        wifi_connect_to_network(s_cfg.ssid, s_cfg.password);
    }

    sender_start_task();
    web_start_server();

    // GPS
    hw_init_gps_uart();
    if (gps_nvs_load_last_fix() == ESP_OK) {
        gps_fix_t f = {0};
        gps_get_last_fix(&f);
        ESP_LOGI(TAG, "Restored last fix: lat=%.6f lon=%.6f sats=%d fixq=%d",
                 f.lat_deg, f.lon_deg, f.sats_used, f.fix_quality);
    }
    gps_init();

    // Input
    input_init();
}

// ─── Entry point ─────────────────────────────────────────────────────────────

void app_main(void)
{
    // ── NVS flash init ────────────────────────────────────────────────────────
    esp_err_t err = nvs_flash_init();
    if (err == ESP_ERR_NVS_NO_FREE_PAGES || err == ESP_ERR_NVS_NEW_VERSION_FOUND) {
        ESP_ERROR_CHECK(nvs_flash_erase());
        err = nvs_flash_init();
    }
    ESP_ERROR_CHECK(err);

    // ── System init ───────────────────────────────────────────────────────────
    ESP_ERROR_CHECK(esp_netif_init());
    ESP_ERROR_CHECK(esp_event_loop_create_default());
    esp_log_level_set("HTTP_CLIENT", ESP_LOG_WARN);

    // ── Config & identity ─────────────────────────────────────────────────────
    (void)cfg_load(&s_cfg);
    cfg_ensure_identity(&s_cfg);

    // ── Time & queue ──────────────────────────────────────────────────────────
    time_init();
    queue_init();

    // ── Branch: deep sleep vs always-on ───────────────────────────────────────
    if (s_cfg.deep_sleep_enabled) {
        deep_sleep_flow();  // never returns
    } else {
        always_on_flow();
    }
}