#include "freertos/FreeRTOS.h"
#include "freertos/task.h"

#include "esp_err.h"
#include "esp_log.h"
#include "esp_netif.h"
#include "esp_event.h"
#include "nvs_flash.h"

#include "driver/uart.h"

// ─── Component headers ────────────────────────────────────────────────────────
#include "../components/nys_common/nys_common.h"
#include "../components/comms/comms.h"
#include "../components/wifi/wifi.h"
#include "../components/gps/gps.h"
#include "../components/input/input.h"
#include "../components/web/web_server.h"

extern nys_cfg_t s_cfg;

static const char *TAG = "MAIN";

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

    // ── WiFi ──────────────────────────────────────────────────────────────────
    wifi_ensure_inited(true, true);  // Always init both STA & AP

    if (!s_cfg.has_wifi) {
        ESP_LOGW(TAG, "No WiFi configured – starting setup portal");
        wifi_auto_connect_on_boot();  // Will start AP if no networks saved
    } else {
        ESP_LOGI(TAG, "Connecting to configured WiFi");
        wifi_connect_to_network(s_cfg.ssid, s_cfg.password);
    }

    // Always start sender and web server regardless of WiFi config method
    sender_start_task();
    web_start_server();

    // ── LED init ──────────────────────────────────────────────────────────────
    gpio_config_t led_conf = {
        .pin_bit_mask  = 1ULL << LED_GPIO,
        .mode          = GPIO_MODE_OUTPUT,
        .pull_up_en    = GPIO_PULLUP_DISABLE,
        .pull_down_en  = GPIO_PULLDOWN_DISABLE,
        .intr_type     = GPIO_INTR_DISABLE,
    };
    ESP_ERROR_CHECK(gpio_config(&led_conf));
    gpio_set_level(LED_GPIO, LED_OFF);

    // ── GPS UART hardware init ────────────────────────────────────────────────
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

    // ── Restore last GPS fix, then start GPS reader task ─────────────────────
    if (gps_nvs_load_last_fix() == ESP_OK) {
        gps_fix_t f = {0};
        gps_get_last_fix(&f);
        ESP_LOGI(TAG, "Restored last fix: lat=%.6f lon=%.6f sats=%d fixq=%d",
                 f.lat_deg, f.lon_deg, f.sats_used, f.fix_quality);
    }
    gps_init();

    // ── Input GPIO init ───────────────────────────────────────────────────────
    input_init();
}