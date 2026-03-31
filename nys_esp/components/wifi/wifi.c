#include "wifi.h"

#include <string.h>
#include "freertos/FreeRTOS.h"
#include "freertos/task.h"
#include "freertos/event_groups.h"

#include "esp_log.h"
#include "esp_wifi.h"
#include "esp_event.h"
#include "esp_netif.h"
#include "esp_mac.h"
#include "esp_timer.h"

#include "nys_common.h"
#include "web_server.h"
#include "comms.h"

static const char *TAG = "WIFI";

EventGroupHandle_t s_wifi_event_group;

static bool s_wifi_inited;
static bool s_wifi_started;
static bool s_ap_running;
static bool s_wifi_event_group_valid = false;

static char s_last_connected_ssid[33];

// ─────────────────────────────────────────────────────────────────────────────
// Internal helpers
// ─────────────────────────────────────────────────────────────────────────────
static void wifi_ensure_started(void)
{
    if (s_wifi_started) return;
    ESP_ERROR_CHECK(esp_wifi_start());
    s_wifi_started = true;
}

static void ap_build_ssid(char out[33])
{
    uint8_t mac[6] = {0};
    esp_read_mac(mac, ESP_MAC_WIFI_SOFTAP);
    snprintf(out, 33, NYS_WIFI_AP_SSID_PREFIX "%02X%02X%02X%02X%02X%02X",
             mac[0], mac[1], mac[2], mac[3], mac[4], mac[5]);
}

static void ap_start_portal(void)
{
    if (s_ap_running) return;

    char ap_ssid[33];
    ap_build_ssid(ap_ssid);

    wifi_config_t ap_cfg = {0};
    strncpy((char *)ap_cfg.ap.ssid, ap_ssid, sizeof(ap_cfg.ap.ssid));
    strncpy((char *)ap_cfg.ap.password, NYS_WIFI_AP_PASS, sizeof(ap_cfg.ap.password));
    ap_cfg.ap.ssid_len = (uint8_t)strlen(ap_ssid);
    ap_cfg.ap.max_connection = 4;
    ap_cfg.ap.authmode = strlen(NYS_WIFI_AP_PASS) ? WIFI_AUTH_WPA_WPA2_PSK
                                                  : WIFI_AUTH_OPEN;

    ESP_ERROR_CHECK(esp_wifi_set_config(WIFI_IF_AP, &ap_cfg));
    ESP_ERROR_CHECK(esp_wifi_set_mode(WIFI_MODE_APSTA));
    wifi_ensure_started();

    web_start_server();

    s_ap_running = true;
    ESP_LOGI(TAG, "Setup AP started: %s", ap_ssid);
}

static void ap_stop_portal(void)
{
    if (!s_ap_running) return;

    ESP_ERROR_CHECK(esp_wifi_set_mode(WIFI_MODE_STA));
    s_ap_running = false;

    ESP_LOGI(TAG, "Setup AP stopped");
}

static void ap_window_task(void *arg)
{
    (void)arg;
    vTaskDelay(pdMS_TO_TICKS(NYS_AP_WINDOW_MS));
    ap_stop_portal();
    vTaskDelete(NULL);
}

static void wifi_try_connect(const char *ssid, const char *password)
{
    wifi_config_t cfg = {0};

    strncpy((char *)cfg.sta.ssid, ssid, sizeof(cfg.sta.ssid) - 1);
    strncpy((char *)cfg.sta.password, password ? password : "", sizeof(cfg.sta.password) - 1);

    esp_err_t err = esp_wifi_set_config(WIFI_IF_STA, &cfg);
    if (err != ESP_OK) {
        ESP_LOGW(TAG, "set_config failed: %s", esp_err_to_name(err));
    }

    err = esp_wifi_connect();
    if (err != ESP_OK && err != ESP_ERR_WIFI_CONN) {
        ESP_LOGW(TAG, "connect failed: %s", esp_err_to_name(err));
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Event handler
// ─────────────────────────────────────────────────────────────────────────────
static void wifi_event_handler(void *arg, esp_event_base_t event_base,
                               int32_t event_id, void *event_data)
{
    (void)arg;

    if (!s_wifi_event_group_valid || s_wifi_event_group == NULL) {
        return;
    }

    if (event_base == WIFI_EVENT && event_id == WIFI_EVENT_STA_DISCONNECTED) {

        wifi_event_sta_disconnected_t *disc = (wifi_event_sta_disconnected_t *)event_data;

        if (disc->reason == WIFI_REASON_ASSOC_LEAVE) return;

        xEventGroupClearBits(s_wifi_event_group, WIFI_CONNECTED_BIT);

        ESP_LOGW(TAG, "Disconnected (reason=%d)", disc->reason);

        if (!s_ap_running) {
            ap_start_portal();
        }

    } 
    else if (event_base == IP_EVENT && event_id == IP_EVENT_STA_GOT_IP) {

        xEventGroupSetBits(s_wifi_event_group, WIFI_CONNECTED_BIT);

        ip_event_got_ip_t *evt = (ip_event_got_ip_t *)event_data;
        ESP_LOGI(TAG, "Got IP: " IPSTR, IP2STR(&evt->ip_info.ip));

        // Stop AP when connected
        if (s_ap_running) {
            ap_stop_portal();
        }

        wifi_ap_record_t ap_info;
        if (esp_wifi_sta_get_ap_info(&ap_info) == ESP_OK) {
            strncpy(s_last_connected_ssid, (char *)ap_info.ssid,
                    sizeof(s_last_connected_ssid) - 1);
            cfg_set_last_connected(s_last_connected_ssid);
        }

        time_maybe_start_sntp();
        time_try_update_base_from_system();
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Public API
// ─────────────────────────────────────────────────────────────────────────────
void wifi_ensure_inited(bool need_sta, bool need_ap)
{
    if (s_wifi_inited) return;

    if (need_sta) esp_netif_create_default_wifi_sta();
    if (need_ap)  esp_netif_create_default_wifi_ap();

    wifi_init_config_t cfg = WIFI_INIT_CONFIG_DEFAULT();
    ESP_ERROR_CHECK(esp_wifi_init(&cfg));

    s_wifi_event_group = xEventGroupCreate();
    if (!s_wifi_event_group) return;

    esp_event_handler_instance_register(WIFI_EVENT, ESP_EVENT_ANY_ID,
                                        &wifi_event_handler, NULL, NULL);

    esp_event_handler_instance_register(IP_EVENT, IP_EVENT_STA_GOT_IP,
                                        &wifi_event_handler, NULL, NULL);

    s_wifi_event_group_valid = true;
    s_wifi_inited = true;

    ESP_LOGI(TAG, "WiFi initialized");
}

// 🔥 Used by web server
void wifi_connect_to_network(const char *ssid, const char *password)
{
    ESP_LOGI(TAG, "Connecting to: %s", ssid);

    ESP_ERROR_CHECK(esp_wifi_set_mode(WIFI_MODE_APSTA));
    wifi_ensure_started();

    wifi_try_connect(ssid, password);

    ap_start_portal();
    xTaskCreate(ap_window_task, "ap_window_task", 2048, NULL, 3, NULL);
}

bool wifi_try_reconnect(uint32_t timeout_ms)
{
    if (!s_wifi_event_group_valid || !s_wifi_event_group) return false;

    if (xEventGroupGetBits(s_wifi_event_group) & WIFI_CONNECTED_BIT) return true;

    nys_network_t nets[NYS_MAX_NETWORKS];
    int count = cfg_load_networks(nets);
    if (count == 0) return false;

    uint32_t per_network_ms = timeout_ms / count;
    if (per_network_ms < 3000) per_network_ms = 3000;

    for (int i = 0; i < count; i++) {

        if (nets[i].ssid[0] == 0) continue;

        ESP_LOGI(TAG, "Trying: %s", nets[i].ssid);

        wifi_try_connect(nets[i].ssid, nets[i].password);

        EventBits_t bits = xEventGroupWaitBits(
            s_wifi_event_group,
            WIFI_CONNECTED_BIT,
            pdFALSE,
            pdTRUE,
            pdMS_TO_TICKS(per_network_ms));

        if (bits & WIFI_CONNECTED_BIT) {
            ESP_LOGI(TAG, "Connected: %s", nets[i].ssid);
            return true;
        }
    }

    return false;
}

const char *wifi_get_ip_str(void)
{
    static char ip_str[16] = "";

    esp_netif_t *netif = esp_netif_get_handle_from_ifkey("WIFI_STA_DEF");
    if (!netif) return "";

    esp_netif_ip_info_t ip_info;
    if (esp_netif_get_ip_info(netif, &ip_info) != ESP_OK) return "";

    snprintf(ip_str, sizeof(ip_str), IPSTR, IP2STR(&ip_info.ip));
    return ip_str;
}

// Boot auto connect
void wifi_auto_connect_on_boot(void)
{
    nys_network_t nets[NYS_MAX_NETWORKS];
    int count = cfg_load_networks(nets);

    if (count > 0 && nets[0].ssid[0] != 0) {
        ESP_LOGI(TAG, "Auto connecting to saved network: %s", nets[0].ssid);
        wifi_connect_to_network(nets[0].ssid, nets[0].password);
    } else {
        ESP_LOGW(TAG, "No saved networks, starting AP portal");

        ESP_ERROR_CHECK(esp_wifi_set_mode(WIFI_MODE_APSTA));
        wifi_ensure_started();
        ap_start_portal();
    }
}