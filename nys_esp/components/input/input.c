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

// ─── Input watch task – sends HTTP on state change (with debounce) ───────────
static void gpio_input_watch_task(void *arg)
{
    (void)arg;
    int     confirmed_level = gpio_get_level(TEST_INPUT_GPIO);
    int     candidate_level = confirmed_level;
    int64_t candidate_since_us = 0;
    bool    debouncing      = false;
    bool    pending_send    = false;
    int     pending_level   = confirmed_level;
    int64_t last_reconnect_us = 0;

    while (1) {
        int cur = gpio_get_level(TEST_INPUT_GPIO);

        // Debounce: track how long the new level has been stable
        if (cur != confirmed_level) {
            if (!debouncing || cur != candidate_level) {
                // New candidate level detected — start debounce timer
                candidate_level    = cur;
                candidate_since_us = esp_timer_get_time();
                debouncing         = true;
            } else {
                // Same candidate — check if debounce period elapsed
                int64_t elapsed_us = esp_timer_get_time() - candidate_since_us;
                if (elapsed_us >= (int64_t)INPUT_DEBOUNCE_MS * 1000) {
                    // Debounce passed — confirm the change
                    confirmed_level = candidate_level;
                    debouncing      = false;
                    pending_send    = true;
                    pending_level   = confirmed_level;
                    ESP_LOGI(TAG, "Input confirmed (debounced): %s=%d",
                             s_cfg.input1_desc, confirmed_level);
                }
            }
        } else {
            // Level returned to confirmed — cancel debounce (false alarm)
            debouncing = false;
        }

        if (pending_send) {
            bool connected = (xEventGroupGetBits(s_wifi_event_group) & WIFI_CONNECTED_BIT) != 0;

            if (!connected) {
                int64_t now_us   = esp_timer_get_time();
                int64_t since_us = now_us - last_reconnect_us;

                if (last_reconnect_us == 0 || since_us >= 30000000LL) {
                    ESP_LOGI(TAG, "Input pending — attempting reconnect...");
                    last_reconnect_us = now_us;
                    connected = wifi_try_reconnect(10000);
                }
            }

            if (connected) {
                (void)http_send_input_change(&s_cfg, pending_level);
                pending_send      = false;
                last_reconnect_us = 0;
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

    ESP_LOGI(TAG, "GPIO input=%d output=%d", (int)TEST_INPUT_GPIO, (int)TEST_OUTPUT_GPIO);

    xTaskCreate(gpio_input_watch_task, "gpio_input_watch_task", 3072, NULL, 5, NULL);
}