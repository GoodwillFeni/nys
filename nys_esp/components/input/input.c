#include "input.h"
#include "esp_timer.h" 

#include "freertos/FreeRTOS.h"
#include "freertos/task.h"
#include "freertos/event_groups.h"

#include "driver/gpio.h"
#include "esp_log.h"

#include "nys_common.h"
#include "wifi.h"
#include "comms.h"

static const char *TAG = "INPUT";

extern nys_cfg_t s_cfg;

// ─── GPIO test task – toggles output, logs input level every second ──────────
static void gpio_test_task(void *arg)
{
    (void)arg;
    int last      = gpio_get_level(TEST_INPUT_GPIO);
    int out_level = 0;
    ESP_LOGI(TAG, "GPIO input initial level: %d", last);

    while (1) {
        int cur = gpio_get_level(TEST_INPUT_GPIO);
        if (cur != last) {
            last = cur;
            ESP_LOGI(TAG, "GPIO input changed: %d", cur);
        }
        gpio_set_level(TEST_OUTPUT_GPIO, out_level);
        ESP_LOGI(TAG, "GPIO output set: %d", out_level);
        out_level ^= 1;
        vTaskDelay(pdMS_TO_TICKS(1000));
    }
}

// ─── Input watch task – sends HTTP on state change ───────────────────────────
static void gpio_input_watch_task(void *arg)
{
    (void)arg;
    int     last_read      = gpio_get_level(TEST_INPUT_GPIO);
    int     pending_level  = last_read;
    bool    pending        = false;
    int64_t last_reconnect_us = 0;  // tracks when we last tried reconnect

    while (1) {
        int cur = gpio_get_level(TEST_INPUT_GPIO);
        if (cur != last_read) {
            ESP_LOGI(TAG, "Input changed: %s=%d", s_cfg.input1_desc, cur);
            last_read     = cur;
            pending_level = cur;
            pending       = true;
        }

        if (pending) {
            bool connected = (xEventGroupGetBits(s_wifi_event_group) & WIFI_CONNECTED_BIT) != 0;

            if (!connected) {
                int64_t now_us   = esp_timer_get_time();
                int64_t since_us = now_us - last_reconnect_us;

                // Only attempt reconnect every 30 seconds
                if (last_reconnect_us == 0 || since_us >= 30000000LL) {
                    ESP_LOGI(TAG, "Input pending — attempting reconnect...");
                    last_reconnect_us = now_us;
                    connected = wifi_try_reconnect(10000);
                }
            }

            if (connected) {
                (void)http_send_input_change(&s_cfg, pending_level);
                pending           = false;
                last_reconnect_us = 0; // reset cooldown on success
            }
        }

        vTaskDelay(pdMS_TO_TICKS(100));
    }
}

// ─── Public init ─────────────────────────────────────────────────────────────
void input_init(void)
{
    gpio_config_t in_conf = {
        .pin_bit_mask  = 1ULL << TEST_INPUT_GPIO,
        .mode          = GPIO_MODE_INPUT,
        .pull_up_en    = GPIO_PULLUP_ENABLE,
        .pull_down_en  = GPIO_PULLDOWN_DISABLE,
        .intr_type     = GPIO_INTR_DISABLE,
    };
    ESP_ERROR_CHECK(gpio_config(&in_conf));

    gpio_config_t out_conf = {
        .pin_bit_mask  = 1ULL << TEST_OUTPUT_GPIO,
        .mode          = GPIO_MODE_OUTPUT,
        .pull_up_en    = GPIO_PULLUP_DISABLE,
        .pull_down_en  = GPIO_PULLDOWN_DISABLE,
        .intr_type     = GPIO_INTR_DISABLE,
    };
    ESP_ERROR_CHECK(gpio_config(&out_conf));

    ESP_LOGI(TAG, "GPIO test: input=%d output=%d",
             (int)TEST_INPUT_GPIO, (int)TEST_OUTPUT_GPIO);

    xTaskCreate(gpio_test_task,        "gpio_test_task",        3072, NULL, 5, NULL);
    xTaskCreate(gpio_input_watch_task, "gpio_input_watch_task", 3072, NULL, 5, NULL);
}