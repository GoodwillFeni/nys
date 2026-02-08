#include <stdint.h>
#include <stdio.h>
#include <stdlib.h>
 #include <string.h>
 #include <math.h>
 #include <time.h>

#include "freertos/FreeRTOS.h"
#include "freertos/task.h"
 #include "freertos/event_groups.h"
 #include "freertos/semphr.h"

#include "driver/gpio.h"
#include "driver/uart.h"

#include "esp_err.h"
#include "esp_log.h"
 #include "esp_timer.h"
 #include "nvs.h"
 #include "nvs_flash.h"

 #include "esp_event.h"
 #include "esp_netif.h"
 #include "esp_system.h"
 #include "esp_wifi.h"
 #include "esp_mac.h"
 #include "esp_http_client.h"
 #include "esp_http_server.h"
 #include "esp_sntp.h"
 #include "cJSON.h"
 #include "mbedtls/md.h"

#define GPS_UART UART_NUM_1
#define GPS_BAUD_RATE 9600
#define GPS_RX_GPIO GPIO_NUM_4
#define GPS_TX_GPIO GPIO_NUM_5
#define GPS_SWAP_RX_TX 0

#define TEST_INPUT_GPIO GPIO_NUM_1
#define TEST_OUTPUT_GPIO GPIO_NUM_2

static const char *TAG = "NYS_ESP";
#define NYS_API_URL "http://192.168.101.175:8000/api/device/message" //Home
//#define NYS_API_URL "http://192.168.200.21:8000/api/device/message" //Work
#define NYS_WIFI_AP_SSID_PREFIX "NYS_"
#define NYS_WIFI_AP_PASS "Goodwill@123"
 
typedef struct {
    double lat_deg;
    double lon_deg;
    int fix_quality;
    int sats_used;
    bool has_fix;
} gps_fix_t;

static gps_fix_t s_last_fix;
static bool s_last_fix_valid;
static int64_t s_last_fix_time_us;
static int64_t s_last_fix_nvs_save_us;

typedef struct {
    double lat_deg;
    double lon_deg;
    int32_t fix_quality;
    int32_t sats_used;
} gps_fix_nvs_v1_t;

typedef struct {
    char ssid[33];
    char password[65];
    bool has_wifi;
    char device_uid[32];
    char device_key[128];
    uint32_t heartbeat_interval_s;
    uint32_t location_interval_s;
    char input1_desc[64];
} nys_cfg_t;

static nys_cfg_t s_cfg;
static EventGroupHandle_t s_wifi_event_group;
static int s_wifi_retry;

static bool s_wifi_inited;
static bool s_wifi_started;
static bool s_ap_running;
static httpd_handle_t s_setup_httpd;
static bool s_reboot_scheduled;

#define WIFI_CONNECTED_BIT BIT0

#define NYS_QUEUE_NS "q"
#define NYS_QUEUE_SIZE 100
#define NYS_QUEUE_MAGIC 0x4E595331u

#define NYS_TIME_NS "time"
#define NYS_TIME_VALID_EPOCH_MIN 1700000000

typedef struct {
    uint32_t magic;
    uint32_t seq;
    int64_t ts_s;
    int64_t queued_at_epoch_s;
    int32_t lat_e6;
    int32_t lon_e6;
    int16_t fix_quality;
    int16_t sats_used;
    uint8_t has_coords;
    uint8_t fix;
    uint8_t last_known;
    uint8_t sent;
} nys_queue_rec_t;

static SemaphoreHandle_t s_queue_mutex;
static uint32_t s_queue_next_seq;
static uint32_t s_queue_widx;

static bool s_time_has_base;
static int64_t s_time_base_epoch_s;
static int64_t s_time_base_uptime_s;
static bool s_sntp_started;

#define NYS_AP_WINDOW_MS (60000)
#define NYS_STA_MAX_RETRIES_BEFORE_AP (20)

static void wifi_ensure_inited(bool need_sta, bool need_ap);
static void wifi_ensure_started(void);
static void ap_start_portal(void);
static void ap_stop_portal(void);
static void wifi_start_sta_with_portal_window(const char *ssid, const char *password);

static esp_err_t cfg_save_settings(uint32_t heartbeat_interval_s, uint32_t location_interval_s, const char *input1_desc);
static esp_err_t http_send_heartbeat(const nys_cfg_t *cfg);
static esp_err_t http_send_location(const nys_cfg_t *cfg, const gps_fix_t *fix, bool has_fix);
static esp_err_t http_send_input_change(const nys_cfg_t *cfg, int level);
static esp_err_t http_queue_get(httpd_req_t *req);

static void gps_sample_task(void *arg);
static void queue_init(void);
static void queue_push_sample(void);
static void queue_drain_step(void);
static bool queue_load_rec_locked(nvs_handle_t h, uint32_t idx, nys_queue_rec_t *out);
static esp_err_t http_send_location_record(const nys_cfg_t *cfg, const nys_queue_rec_t *rec);

static void time_init(void);
static int64_t time_uptime_s(void);
static int64_t time_now_epoch_s(void);
static void time_maybe_start_sntp(void);

static void time_try_update_base_from_system(void);

typedef struct {
    uint32_t magic;
    uint32_t seq;
    int64_t ts_s;
    int32_t lat_e6;
    int32_t lon_e6;
    int16_t fix_quality;
    int16_t sats_used;
    uint8_t has_coords;
    uint8_t fix;
    uint8_t last_known;
    uint8_t sent;
} nys_queue_rec_v1_t;

static esp_err_t cfg_load(nys_cfg_t *out)
{
    if (out == NULL) {
        return ESP_ERR_INVALID_ARG;
    }

    memset(out, 0, sizeof(*out));

    nvs_handle_t h;
    esp_err_t err = nvs_open("cfg", NVS_READONLY, &h);
    if (err != ESP_OK) {
        return err;
    }

    size_t ssid_len = sizeof(out->ssid);
    size_t pass_len = sizeof(out->password);
    size_t uid_len = sizeof(out->device_uid);
    size_t key_len = sizeof(out->device_key);
    out->heartbeat_interval_s = 60;
    out->location_interval_s = 60;
    strncpy(out->input1_desc, "Input 1", sizeof(out->input1_desc));
    out->input1_desc[sizeof(out->input1_desc) - 1] = 0;

    if (nvs_get_str(h, "ssid", out->ssid, &ssid_len) == ESP_OK && out->ssid[0] != 0) {
        out->has_wifi = true;
        (void)nvs_get_str(h, "pass", out->password, &pass_len);
    }
    (void)nvs_get_str(h, "uid", out->device_uid, &uid_len);
    (void)nvs_get_str(h, "key", out->device_key, &key_len);
    (void)nvs_get_u32(h, "hb_int", &out->heartbeat_interval_s);
    (void)nvs_get_u32(h, "loc_int", &out->location_interval_s);

    size_t in1_len = sizeof(out->input1_desc);
    (void)nvs_get_str(h, "in1_desc", out->input1_desc, &in1_len);
    if (out->heartbeat_interval_s == 0) {
        out->heartbeat_interval_s = 60;
    }
    if (out->location_interval_s == 0) {
        out->location_interval_s = 60;
    }
    if (out->input1_desc[0] == 0) {
        strncpy(out->input1_desc, "Input 1", sizeof(out->input1_desc));
        out->input1_desc[sizeof(out->input1_desc) - 1] = 0;
    }

    nvs_close(h);
    return ESP_OK;
}

static esp_err_t cfg_save_wifi(const char *ssid, const char *password)
{
    nvs_handle_t h;
    esp_err_t err = nvs_open("cfg", NVS_READWRITE, &h);
    if (err != ESP_OK) {
        return err;
    }

    err = nvs_set_str(h, "ssid", ssid);
    if (err == ESP_OK) {
        err = nvs_set_str(h, "pass", password ? password : "");
    }
    if (err == ESP_OK) {
        err = nvs_commit(h);
    }
    nvs_close(h);
    return err;
}

static esp_err_t cfg_save_settings(uint32_t heartbeat_interval_s, uint32_t location_interval_s, const char *input1_desc)
{
    nvs_handle_t h;
    esp_err_t err = nvs_open("cfg", NVS_READWRITE, &h);
    if (err != ESP_OK) {
        return err;
    }

    err = nvs_set_u32(h, "hb_int", heartbeat_interval_s);
    if (err == ESP_OK) {
        err = nvs_set_u32(h, "loc_int", location_interval_s);
    }
    if (err == ESP_OK && input1_desc != NULL) {
        err = nvs_set_str(h, "in1_desc", input1_desc);
    }
    if (err == ESP_OK) {
        err = nvs_commit(h);
    }
    nvs_close(h);
    return err;
}

static esp_err_t cfg_save_identity(const char *uid, const char *key)
{
    nvs_handle_t h;
    esp_err_t err = nvs_open("cfg", NVS_READWRITE, &h);
    if (err != ESP_OK) {
        return err;
    }

    err = nvs_set_str(h, "uid", uid);
    if (err == ESP_OK) {
        err = nvs_set_str(h, "key", key);
    }
    if (err == ESP_OK) {
        err = nvs_commit(h);
    }
    nvs_close(h);
    return err;
}

static void cfg_ensure_identity(nys_cfg_t *cfg)
{
    if (cfg == NULL) {
        return;
    }

    if (cfg->device_uid[0] == 0) {
        uint8_t mac[6] = {0};
        esp_read_mac(mac, ESP_MAC_WIFI_STA);
        snprintf(cfg->device_uid, sizeof(cfg->device_uid), "%02X%02X%02X%02X%02X%02X",
                 mac[0], mac[1], mac[2], mac[3], mac[4], mac[5]);
    }

    if (cfg->device_key[0] == 0) {
        uint8_t rnd[32];
        for (int i = 0; i < (int)sizeof(rnd); i++) {
            rnd[i] = (uint8_t)(esp_random() & 0xFF);
        }
        static const char hex[] = "0123456789abcdef";
        size_t pos = 0;
        for (int i = 0; i < (int)sizeof(rnd) && (pos + 2) < sizeof(cfg->device_key); i++) {
            cfg->device_key[pos++] = hex[(rnd[i] >> 4) & 0xF];
            cfg->device_key[pos++] = hex[rnd[i] & 0xF];
        }
        cfg->device_key[pos] = 0;
    }

    (void)cfg_save_identity(cfg->device_uid, cfg->device_key);

    ESP_LOGI(TAG, "Device UID: %s", cfg->device_uid);
    ESP_LOGI(TAG, "Device KEY: %s", cfg->device_key);
}

static int64_t time_uptime_s(void)
{
    return esp_timer_get_time() / 1000000;
}

static void time_init(void)
{
    s_time_has_base = false;
    s_time_base_epoch_s = 0;
    s_time_base_uptime_s = 0;

    nvs_handle_t h;
    if (nvs_open(NYS_TIME_NS, NVS_READONLY, &h) == ESP_OK) {
        int64_t epoch = 0;
        int64_t up = 0;
        if (nvs_get_i64(h, "epoch", &epoch) == ESP_OK && nvs_get_i64(h, "uptime", &up) == ESP_OK) {
            if (epoch >= NYS_TIME_VALID_EPOCH_MIN && up >= 0) {
                s_time_has_base = true;
                s_time_base_epoch_s = epoch;
                s_time_base_uptime_s = up;
            }
        }
        nvs_close(h);
    }
}

static void time_try_update_base_from_system(void)
{
    time_t now = time(NULL);
    if ((int64_t)now < NYS_TIME_VALID_EPOCH_MIN) {
        return;
    }

    int64_t up_s = time_uptime_s();
    s_time_has_base = true;
    s_time_base_epoch_s = (int64_t)now;
    s_time_base_uptime_s = up_s;

    nvs_handle_t h;
    if (nvs_open(NYS_TIME_NS, NVS_READWRITE, &h) == ESP_OK) {
        (void)nvs_set_i64(h, "epoch", s_time_base_epoch_s);
        (void)nvs_set_i64(h, "uptime", s_time_base_uptime_s);
        (void)nvs_commit(h);
        nvs_close(h);
    }
}

static int64_t time_now_epoch_s(void)
{
    if (!s_time_has_base) {
        time_try_update_base_from_system();
    }
    if (!s_time_has_base) {
        return 0;
    }
    int64_t up_s = time_uptime_s();
    int64_t delta = up_s - s_time_base_uptime_s;
    if (delta < 0) {
        delta = 0;
    }
    return s_time_base_epoch_s + delta;
}

static void time_maybe_start_sntp(void)
{
    if (s_sntp_started) {
        return;
    }

    s_sntp_started = true;
    esp_sntp_setoperatingmode(SNTP_OPMODE_POLL);
    esp_sntp_setservername(0, "pool.ntp.org");
    esp_sntp_init();
}

static bool hmac_sha256_hex(const char *key, const char *msg, char out_hex[65])
{
    if (key == NULL || msg == NULL || out_hex == NULL) {
        return false;
    }

    const mbedtls_md_info_t *info = mbedtls_md_info_from_type(MBEDTLS_MD_SHA256);
    if (info == NULL) {
        return false;
    }

    unsigned char hmac[32];
    mbedtls_md_context_t ctx;
    mbedtls_md_init(&ctx);

    int rc = mbedtls_md_setup(&ctx, info, 1);
    if (rc != 0) {
        mbedtls_md_free(&ctx);
        return false;
    }

    rc = mbedtls_md_hmac_starts(&ctx, (const unsigned char *)key, strlen(key));
    if (rc == 0) {
        rc = mbedtls_md_hmac_update(&ctx, (const unsigned char *)msg, strlen(msg));
    }
    if (rc == 0) {
        rc = mbedtls_md_hmac_finish(&ctx, hmac);
    }

    mbedtls_md_free(&ctx);

    if (rc != 0) {
        return false;
    }

    static const char hex[] = "0123456789abcdef";
    for (int i = 0; i < 32; i++) {
        out_hex[i * 2] = hex[(hmac[i] >> 4) & 0xF];
        out_hex[i * 2 + 1] = hex[hmac[i] & 0xF];
    }
    out_hex[64] = 0;
    return true;
}

static esp_err_t http_event_handler(esp_http_client_event_t *evt)
{
    switch (evt->event_id) {
    case HTTP_EVENT_ON_DATA:
        break;
    default:
        break;
    }
    return ESP_OK;
}

static esp_err_t http_send_heartbeat(const nys_cfg_t *cfg)
{
    if (cfg == NULL) {
        return ESP_ERR_INVALID_ARG;
    }

    cJSON *root = cJSON_CreateObject();
    if (!root) {
        return ESP_ERR_NO_MEM;
    }

    int64_t uptime_s = time_uptime_s();
    int64_t epoch_s = time_now_epoch_s();
    cJSON_AddNumberToObject(root, "uptime_s", (double)uptime_s);
    if (epoch_s > 0) {
        cJSON_AddNumberToObject(root, "message_timestamp", (double)epoch_s);
    }

    char *json = cJSON_PrintUnformatted(root);
    cJSON_Delete(root);
    if (!json) {
        return ESP_ERR_NO_MEM;
    }

    char sig[65];
    if (!hmac_sha256_hex(cfg->device_key, json, sig)) {
        free(json);
        return ESP_FAIL;
    }

    esp_http_client_config_t http_cfg = {
        .url = NYS_API_URL,
        .method = HTTP_METHOD_POST,
        .timeout_ms = 10000,
        .disable_auto_redirect = false,
        .keep_alive_enable = false,
        .event_handler = http_event_handler,
    };

    esp_http_client_handle_t client = esp_http_client_init(&http_cfg);
    if (!client) {
        free(json);
        return ESP_FAIL;
    }

    esp_http_client_set_header(client, "Content-Type", "application/json");
    esp_http_client_set_header(client, "Accept", "application/json");
    esp_http_client_set_header(client, "Connection", "close");
    esp_http_client_set_header(client, "X-DEVICE-ID", cfg->device_uid);
    esp_http_client_set_header(client, "X-DEVICE-SIGNATURE", sig);
    esp_http_client_set_post_field(client, json, (int)strlen(json));

    esp_err_t err = esp_http_client_perform(client);

    int status = esp_http_client_get_status_code(client);
    if (status > 0 && (status < 200 || status >= 300)) {
        char resp[512];
        int r = esp_http_client_read_response(client, resp, (int)(sizeof(resp) - 1));
        if (r >= 0) {
            resp[r] = 0;
            if (r > 0) {
                ESP_LOGW(TAG, "HTTP response body (%d bytes): %s", r, resp);
            }
        }
    }
    if (err == ESP_OK) {
        ESP_LOGI(TAG, "POST status=%d", status);
    } else if (err == ESP_ERR_HTTP_INCOMPLETE_DATA && status >= 200 && status < 300) {
        ESP_LOGW(TAG, "POST incomplete data but status=%d; treating as success", status);
        err = ESP_OK;
    } else {
        ESP_LOGE(TAG, "POST failed: %s (status=%d)", esp_err_to_name(err), status);
    }

    esp_http_client_cleanup(client);
    free(json);
    return err;
}

static esp_err_t http_send_location(const nys_cfg_t *cfg, const gps_fix_t *fix, bool has_fix)
{
    if (cfg == NULL) {
        return ESP_ERR_INVALID_ARG;
    }

    cJSON *root = cJSON_CreateObject();
    if (!root) {
        return ESP_ERR_NO_MEM;
    }

    int64_t uptime_s = time_uptime_s();
    int64_t epoch_s = time_now_epoch_s();
    cJSON_AddNumberToObject(root, "uptime_s", (double)uptime_s);
    if (epoch_s > 0) {
        cJSON_AddNumberToObject(root, "message_timestamp", (double)epoch_s);
    }

    bool include_gps = false;
    gps_fix_t to_send = { 0 };
    if (has_fix && fix != NULL && fix->has_fix) {
        include_gps = true;
        to_send = *fix;
    } else if (s_last_fix_valid && s_last_fix.has_fix) {
        include_gps = true;
        to_send = s_last_fix;
    }

    cJSON *gps = cJSON_AddObjectToObject(root, "gps");
    if (gps) {
        if (include_gps) {
            cJSON_AddNumberToObject(gps, "lat", to_send.lat_deg);
            cJSON_AddNumberToObject(gps, "lng", to_send.lon_deg);
            cJSON_AddNumberToObject(gps, "fix_quality", to_send.fix_quality);
            cJSON_AddNumberToObject(gps, "satellites", to_send.sats_used);
            cJSON_AddBoolToObject(gps, "fix", true);
        } else {
            cJSON_AddBoolToObject(gps, "fix", false);
            cJSON_AddNumberToObject(gps, "fix_quality", 0);
            cJSON_AddNumberToObject(gps, "satellites", 0);
        }
    }

    char *json = cJSON_PrintUnformatted(root);
    cJSON_Delete(root);
    if (!json) {
        return ESP_ERR_NO_MEM;
    }

    char sig[65];
    if (!hmac_sha256_hex(cfg->device_key, json, sig)) {
        free(json);
        return ESP_FAIL;
    }

    esp_http_client_config_t http_cfg = {
        .url = NYS_API_URL,
        .method = HTTP_METHOD_POST,
        .timeout_ms = 10000,
        .disable_auto_redirect = false,
        .keep_alive_enable = false,
        .event_handler = http_event_handler,
    };

    esp_http_client_handle_t client = esp_http_client_init(&http_cfg);
    if (!client) {
        free(json);
        return ESP_FAIL;
    }

    esp_http_client_set_header(client, "Content-Type", "application/json");
    esp_http_client_set_header(client, "Accept", "application/json");
    esp_http_client_set_header(client, "Connection", "close");
    esp_http_client_set_header(client, "X-DEVICE-ID", cfg->device_uid);
    esp_http_client_set_header(client, "X-DEVICE-SIGNATURE", sig);
    esp_http_client_set_post_field(client, json, (int)strlen(json));

    esp_err_t err = esp_http_client_perform(client);

    int status = esp_http_client_get_status_code(client);
    if (status > 0 && (status < 200 || status >= 300)) {
        char resp[512];
        int r = esp_http_client_read_response(client, resp, (int)(sizeof(resp) - 1));
        if (r >= 0) {
            resp[r] = 0;
            if (r > 0) {
                ESP_LOGW(TAG, "HTTP response body (%d bytes): %s", r, resp);
            }
        }
    }
    if (err == ESP_OK) {
        ESP_LOGI(TAG, "POST status=%d", status);
    } else if (err == ESP_ERR_HTTP_INCOMPLETE_DATA && status >= 200 && status < 300) {
        ESP_LOGW(TAG, "POST incomplete data but status=%d; treating as success", status);
        err = ESP_OK;
    } else {
        ESP_LOGE(TAG, "POST failed: %s (status=%d)", esp_err_to_name(err), status);
    }

    esp_http_client_cleanup(client);
    free(json);
    return err;
}

static esp_err_t http_send_input_change(const nys_cfg_t *cfg, int level)
{
    if (cfg == NULL) {
        return ESP_ERR_INVALID_ARG;
    }

    cJSON *root = cJSON_CreateObject();
    if (!root) {
        return ESP_ERR_NO_MEM;
    }

    int64_t uptime_s = time_uptime_s();
    int64_t epoch_s = time_now_epoch_s();
    cJSON_AddNumberToObject(root, "uptime_s", (double)uptime_s);
    if (epoch_s > 0) {
        cJSON_AddNumberToObject(root, "message_timestamp", (double)epoch_s);
    }

    cJSON *inputs = cJSON_AddObjectToObject(root, "inputs");
    if (inputs) {
        cJSON *in1 = cJSON_AddObjectToObject(inputs, "input1");
        if (in1) {
            cJSON_AddStringToObject(in1, "description", cfg->input1_desc);
            cJSON_AddNumberToObject(in1, "state", level);
        }
    }

    char *json = cJSON_PrintUnformatted(root);
    cJSON_Delete(root);
    if (!json) {
        return ESP_ERR_NO_MEM;
    }

    char sig[65];
    if (!hmac_sha256_hex(cfg->device_key, json, sig)) {
        free(json);
        return ESP_FAIL;
    }

    esp_http_client_config_t http_cfg = {
        .url = NYS_API_URL,
        .method = HTTP_METHOD_POST,
        .timeout_ms = 10000,
        .disable_auto_redirect = false,
        .keep_alive_enable = false,
        .event_handler = http_event_handler,
    };

    esp_http_client_handle_t client = esp_http_client_init(&http_cfg);
    if (!client) {
        free(json);
        return ESP_FAIL;
    }

    esp_http_client_set_header(client, "Content-Type", "application/json");
    esp_http_client_set_header(client, "Accept", "application/json");
    esp_http_client_set_header(client, "Connection", "close");
    esp_http_client_set_header(client, "X-DEVICE-ID", cfg->device_uid);
    esp_http_client_set_header(client, "X-DEVICE-SIGNATURE", sig);
    esp_http_client_set_post_field(client, json, (int)strlen(json));

    esp_err_t err = esp_http_client_perform(client);
    int status = esp_http_client_get_status_code(client);
    if (status > 0 && (status < 200 || status >= 300)) {
        char resp[512];
        int r = esp_http_client_read_response(client, resp, (int)(sizeof(resp) - 1));
        if (r >= 0) {
            resp[r] = 0;
            if (r > 0) {
                ESP_LOGW(TAG, "HTTP response body (%d bytes): %s", r, resp);
            }
        }
    }
    if (err == ESP_OK) {
        ESP_LOGI(TAG, "POST status=%d", status);
    } else if (err == ESP_ERR_HTTP_INCOMPLETE_DATA && status >= 200 && status < 300) {
        ESP_LOGW(TAG, "POST incomplete data but status=%d; treating as success", status);
        err = ESP_OK;
    } else {
        ESP_LOGE(TAG, "POST failed: %s (status=%d)", esp_err_to_name(err), status);
    }

    esp_http_client_cleanup(client);
    free(json);
    return err;
}

static void send_task(void *arg)
{
    (void)arg;

    ESP_LOGI(TAG, "send_task started");

    bool logged_connected = false;

    int64_t last_hb_us = 0;
    int64_t last_loc_us = 0;

    while (1) {
        EventBits_t before = xEventGroupGetBits(s_wifi_event_group);
        if ((before & WIFI_CONNECTED_BIT) == 0) {
            logged_connected = false;
        }
        (void)xEventGroupWaitBits(s_wifi_event_group, WIFI_CONNECTED_BIT, pdFALSE, pdTRUE, portMAX_DELAY);

        if (!logged_connected) {
            ESP_LOGI(TAG, "send_task unblocked: WIFI_CONNECTED_BIT set");
            logged_connected = true;
        }

        int64_t now_us = esp_timer_get_time();
        int64_t hb_int_us = (int64_t)s_cfg.heartbeat_interval_s * 1000000LL;
        int64_t loc_int_us = (int64_t)s_cfg.location_interval_s * 1000000LL;
        if (hb_int_us <= 0) hb_int_us = 60000000LL;
        if (loc_int_us <= 0) loc_int_us = 60000000LL;

        bool do_hb = (last_hb_us == 0) || (now_us - last_hb_us >= hb_int_us);
        bool do_loc = (last_loc_us == 0) || (now_us - last_loc_us >= loc_int_us);

        if (do_hb) {
            (void)http_send_heartbeat(&s_cfg);
            last_hb_us = now_us;
        }
        if (do_loc) {
            bool has_fix = s_last_fix_valid;
            gps_fix_t fix = s_last_fix;
            (void)http_send_location(&s_cfg, &fix, has_fix);
            last_loc_us = now_us;
        }

        queue_drain_step();

        vTaskDelay(pdMS_TO_TICKS(500));
    }
}

static void wifi_event_handler(void *arg, esp_event_base_t event_base, int32_t event_id, void *event_data)
{
    (void)arg;
    (void)event_data;

    if (event_base == WIFI_EVENT && event_id == WIFI_EVENT_STA_START) {
        esp_wifi_connect();
    } else if (event_base == WIFI_EVENT && event_id == WIFI_EVENT_STA_DISCONNECTED) {
        xEventGroupClearBits(s_wifi_event_group, WIFI_CONNECTED_BIT);
        s_wifi_retry++;
        if (s_wifi_retry < NYS_STA_MAX_RETRIES_BEFORE_AP) {
            esp_wifi_connect();
        } else {
            if (!s_ap_running) {
                ap_start_portal();
            }
        }
    } else if (event_base == IP_EVENT && event_id == IP_EVENT_STA_GOT_IP) {
        s_wifi_retry = 0;
        xEventGroupSetBits(s_wifi_event_group, WIFI_CONNECTED_BIT);
        ESP_LOGI(TAG, "WiFi got IP (WIFI_CONNECTED_BIT set)");

        time_maybe_start_sntp();
        time_try_update_base_from_system();
    }
}

static void wifi_ensure_inited(bool need_sta, bool need_ap)
{
    if (s_wifi_inited) {
        return;
    }

    if (need_sta) {
        esp_netif_create_default_wifi_sta();
    }
    if (need_ap) {
        esp_netif_create_default_wifi_ap();
    }

    wifi_init_config_t cfg = WIFI_INIT_CONFIG_DEFAULT();
    ESP_ERROR_CHECK(esp_wifi_init(&cfg));

    s_wifi_event_group = xEventGroupCreate();
    s_wifi_retry = 0;

    ESP_ERROR_CHECK(esp_event_handler_instance_register(WIFI_EVENT, ESP_EVENT_ANY_ID, &wifi_event_handler, NULL, NULL));
    ESP_ERROR_CHECK(esp_event_handler_instance_register(IP_EVENT, IP_EVENT_STA_GOT_IP, &wifi_event_handler, NULL, NULL));

    s_wifi_inited = true;
}

static void wifi_ensure_started(void)
{
    if (s_wifi_started) {
        return;
    }
    ESP_ERROR_CHECK(esp_wifi_start());
    s_wifi_started = true;
}

static void ap_build_ssid(char out_ssid[33])
{
    uint8_t mac[6] = {0};
    esp_read_mac(mac, ESP_MAC_WIFI_SOFTAP);
    snprintf(out_ssid, 33, NYS_WIFI_AP_SSID_PREFIX "%02X%02X%02X%02X%02X%02X",
             mac[0], mac[1], mac[2], mac[3], mac[4], mac[5]);
}

static esp_err_t http_root_get(httpd_req_t *req)
{
    char html[2048];
    snprintf(
        html,
        sizeof(html),
        "<!doctype html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'>"
        "<title>NYS Setup</title></head><body style='font-family:Arial;padding:16px'>"
        "<h2>NYS WiFi Setup</h2>"
        "<form method='POST' action='/save'>"
        "<label>SSID</label><br><input name='ssid' value='%s' style='width:100%%;padding:8px' /><br><br>"
        "<label>Password</label><br><input name='pass' type='password' value='%s' style='width:100%%;padding:8px' /><br><br>"
        "<label>Heartbeat interval (seconds)</label><br><input name='hb' value='%u' style='width:100%%;padding:8px' /><br><br>"
        "<label>Location interval (seconds)</label><br><input name='loc' value='%u' style='width:100%%;padding:8px' /><br><br>"
        "<label>Input 1 description</label><br><input name='in1' value='%s' style='width:100%%;padding:8px' /><br><br>"
        "<button type='submit' style='padding:10px 14px'>Save</button>"
        "</form>"
        "</body></html>",
        s_cfg.ssid,
        s_cfg.password,
        (unsigned)s_cfg.heartbeat_interval_s,
        (unsigned)s_cfg.location_interval_s,
        s_cfg.input1_desc
    );

    httpd_resp_set_type(req, "text/html");
    return httpd_resp_send(req, html, HTTPD_RESP_USE_STRLEN);
}

static void url_decode_inplace(char *s)
{
    char *o = s;
    while (*s) {
        if (*s == '+') {
            *o++ = ' ';
            s++;
        } else if (*s == '%' && s[1] && s[2]) {
            char hex[3] = { s[1], s[2], 0 };
            *o++ = (char)strtol(hex, NULL, 16);
            s += 3;
        } else {
            *o++ = *s++;
        }
    }
    *o = 0;
}

static void reboot_task(void *arg)
{
    (void)arg;
    vTaskDelay(pdMS_TO_TICKS(4000));
    esp_restart();
}

static esp_err_t http_save_post(httpd_req_t *req)
{
    int total = req->content_len;
    if (total <= 0 || total > 512) {
        httpd_resp_send_err(req, HTTPD_400_BAD_REQUEST, "Bad request");
        return ESP_FAIL;
    }

    char *buf = (char *)calloc(1, (size_t)total + 1);
    if (!buf) {
        httpd_resp_send_err(req, HTTPD_500_INTERNAL_SERVER_ERROR, "No mem");
        return ESP_FAIL;
    }

    int received = httpd_req_recv(req, buf, total);
    if (received <= 0) {
        free(buf);
        httpd_resp_send_err(req, HTTPD_500_INTERNAL_SERVER_ERROR, "Recv failed");
        return ESP_FAIL;
    }
    buf[received] = 0;

    char ssid[33] = {0};
    char pass[65] = {0};
    char hb_s[16] = {0};
    char loc_s[16] = {0};
    char in1[64] = {0};

    char *ssid_p = strstr(buf, "ssid=");
    char *pass_p = strstr(buf, "pass=");
    char *hb_p = strstr(buf, "hb=");
    char *loc_p = strstr(buf, "loc=");
    char *in1_p = strstr(buf, "in1=");
    if (ssid_p) {
        ssid_p += 5;
        char *end = strchr(ssid_p, '&');
        size_t len = end ? (size_t)(end - ssid_p) : strlen(ssid_p);
        if (len >= sizeof(ssid)) len = sizeof(ssid) - 1;
        memcpy(ssid, ssid_p, len);
        ssid[len] = 0;
        url_decode_inplace(ssid);
    }
    if (pass_p) {
        pass_p += 5;
        char *end = strchr(pass_p, '&');
        size_t len = end ? (size_t)(end - pass_p) : strlen(pass_p);
        if (len >= sizeof(pass)) len = sizeof(pass) - 1;
        memcpy(pass, pass_p, len);
        pass[len] = 0;
        url_decode_inplace(pass);
    }

    if (hb_p) {
        hb_p += 3;
        char *end = strchr(hb_p, '&');
        size_t len = end ? (size_t)(end - hb_p) : strlen(hb_p);
        if (len >= sizeof(hb_s)) len = sizeof(hb_s) - 1;
        memcpy(hb_s, hb_p, len);
        hb_s[len] = 0;
        url_decode_inplace(hb_s);
    }
    if (loc_p) {
        loc_p += 4;
        char *end = strchr(loc_p, '&');
        size_t len = end ? (size_t)(end - loc_p) : strlen(loc_p);
        if (len >= sizeof(loc_s)) len = sizeof(loc_s) - 1;
        memcpy(loc_s, loc_p, len);
        loc_s[len] = 0;
        url_decode_inplace(loc_s);
    }
    if (in1_p) {
        in1_p += 4;
        char *end = strchr(in1_p, '&');
        size_t len = end ? (size_t)(end - in1_p) : strlen(in1_p);
        if (len >= sizeof(in1)) len = sizeof(in1) - 1;
        memcpy(in1, in1_p, len);
        in1[len] = 0;
        url_decode_inplace(in1);
    }

    free(buf);

    if (ssid[0] == 0) {
        httpd_resp_send_err(req, HTTPD_400_BAD_REQUEST, "SSID required");
        return ESP_FAIL;
    }

    if (cfg_save_wifi(ssid, pass) != ESP_OK) {
        httpd_resp_send_err(req, HTTPD_500_INTERNAL_SERVER_ERROR, "Save failed");
        return ESP_FAIL;
    }

    uint32_t hb = (uint32_t)strtoul(hb_s[0] ? hb_s : "60", NULL, 10);
    uint32_t loc = (uint32_t)strtoul(loc_s[0] ? loc_s : "60", NULL, 10);
    if (hb == 0) hb = 60;
    if (loc == 0) loc = 60;
    if (in1[0] == 0) {
        strncpy(in1, "Input 1", sizeof(in1));
        in1[sizeof(in1) - 1] = 0;
    }

    if (cfg_save_settings(hb, loc, in1) != ESP_OK) {
        httpd_resp_send_err(req, HTTPD_500_INTERNAL_SERVER_ERROR, "Save settings failed");
        return ESP_FAIL;
    }

    const char *ok =
        "<!doctype html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'>"
        "<title>Saved</title></head><body style='font-family:Arial;padding:16px'>"
        "<h3>Saved</h3>"
        "<p>Settings saved. The device will reboot in a few seconds.</p>"
        "</body></html>";

    httpd_resp_set_type(req, "text/html");
    httpd_resp_send(req, ok, HTTPD_RESP_USE_STRLEN);

    if (!s_reboot_scheduled) {
        s_reboot_scheduled = true;
        xTaskCreate(reboot_task, "reboot_task", 2048, NULL, 5, NULL);
    }
    return ESP_OK;
}

static esp_err_t http_queue_get(httpd_req_t *req)
{
    if (s_queue_mutex == NULL) {
        httpd_resp_send_err(req, HTTPD_500_INTERNAL_SERVER_ERROR, "Queue not initialized");
        return ESP_FAIL;
    }

    if (xSemaphoreTake(s_queue_mutex, pdMS_TO_TICKS(500)) != pdTRUE) {
        httpd_resp_send_err(req, HTTPD_500_INTERNAL_SERVER_ERROR, "Queue busy");
        return ESP_FAIL;
    }

    nvs_handle_t h;
    esp_err_t err = nvs_open(NYS_QUEUE_NS, NVS_READONLY, &h);
    if (err != ESP_OK) {
        xSemaphoreGive(s_queue_mutex);
        httpd_resp_send_err(req, HTTPD_500_INTERNAL_SERVER_ERROR, "NVS open failed");
        return ESP_FAIL;
    }

    cJSON *root = cJSON_CreateObject();
    cJSON *items = cJSON_AddArrayToObject(root, "items");
    cJSON_AddNumberToObject(root, "capacity", NYS_QUEUE_SIZE);
    cJSON_AddNumberToObject(root, "next_seq", (double)s_queue_next_seq);
    cJSON_AddNumberToObject(root, "widx", (double)s_queue_widx);

    if (items) {
        for (uint32_t i = 0; i < NYS_QUEUE_SIZE; i++) {
            nys_queue_rec_t rec;
            if (!queue_load_rec_locked(h, i, &rec)) {
                continue;
            }

            cJSON *it = cJSON_CreateObject();
            if (!it) {
                continue;
            }

            cJSON_AddNumberToObject(it, "idx", (double)i);
            cJSON_AddNumberToObject(it, "seq", (double)rec.seq);
            cJSON_AddNumberToObject(it, "uptime_s", (double)rec.ts_s);
            if (rec.queued_at_epoch_s > 0) {
                cJSON_AddNumberToObject(it, "message_timestamp", (double)rec.queued_at_epoch_s);
            }
            cJSON_AddBoolToObject(it, "sent", rec.sent ? true : false);

            cJSON *gps = cJSON_AddObjectToObject(it, "gps");
            if (gps) {
                if (rec.has_coords) {
                    cJSON_AddNumberToObject(gps, "lat", ((double)rec.lat_e6) / 1000000.0);
                    cJSON_AddNumberToObject(gps, "lng", ((double)rec.lon_e6) / 1000000.0);
                }
                cJSON_AddNumberToObject(gps, "fix_quality", rec.fix_quality);
                cJSON_AddNumberToObject(gps, "satellites", rec.sats_used);
                cJSON_AddBoolToObject(gps, "fix", rec.fix ? true : false);
                if (rec.last_known) {
                    cJSON_AddBoolToObject(gps, "last_known", true);
                }
            }

            cJSON_AddItemToArray(items, it);
        }
    }

    nvs_close(h);
    xSemaphoreGive(s_queue_mutex);

    char *json = cJSON_PrintUnformatted(root);
    cJSON_Delete(root);
    if (!json) {
        httpd_resp_send_err(req, HTTPD_500_INTERNAL_SERVER_ERROR, "No mem");
        return ESP_FAIL;
    }

    httpd_resp_set_type(req, "application/json");
    httpd_resp_send(req, json, HTTPD_RESP_USE_STRLEN);
    free(json);
    return ESP_OK;
}

static httpd_handle_t start_setup_webserver(void)
{
    httpd_config_t config = HTTPD_DEFAULT_CONFIG();
    config.stack_size = 8192;

    httpd_handle_t server = NULL;
    if (httpd_start(&server, &config) != ESP_OK) {
        return NULL;
    }

    httpd_uri_t root = {
        .uri = "/",
        .method = HTTP_GET,
        .handler = http_root_get,
        .user_ctx = NULL,
    };
    httpd_uri_t save = {
        .uri = "/save",
        .method = HTTP_POST,
        .handler = http_save_post,
        .user_ctx = NULL,
    };
    httpd_uri_t queue = {
        .uri = "/queue",
        .method = HTTP_GET,
        .handler = http_queue_get,
        .user_ctx = NULL,
    };

    (void)httpd_register_uri_handler(server, &root);
    (void)httpd_register_uri_handler(server, &save);
    (void)httpd_register_uri_handler(server, &queue);
    return server;
}

static void stop_setup_webserver(void)
{
    if (s_setup_httpd) {
        httpd_stop(s_setup_httpd);
        s_setup_httpd = NULL;
    }
}

static void ap_start_portal(void)
{
    char ap_ssid[33];
    ap_build_ssid(ap_ssid);

    wifi_config_t ap_cfg = { 0 };
    strncpy((char *)ap_cfg.ap.ssid, ap_ssid, sizeof(ap_cfg.ap.ssid));
    strncpy((char *)ap_cfg.ap.password, NYS_WIFI_AP_PASS, sizeof(ap_cfg.ap.password));
    ap_cfg.ap.ssid_len = (uint8_t)strlen(ap_ssid);
    ap_cfg.ap.max_connection = 4;
    ap_cfg.ap.authmode = WIFI_AUTH_WPA_WPA2_PSK;
    if (strlen(NYS_WIFI_AP_PASS) == 0) {
        ap_cfg.ap.authmode = WIFI_AUTH_OPEN;
    }

    ESP_ERROR_CHECK(esp_wifi_set_config(WIFI_IF_AP, &ap_cfg));
    ESP_ERROR_CHECK(esp_wifi_set_mode(WIFI_MODE_APSTA));
    wifi_ensure_started();

    if (!s_setup_httpd) {
        s_setup_httpd = start_setup_webserver();
    }

    s_ap_running = true;
    ESP_LOGI(TAG, "Setup AP started SSID=%s", ap_ssid);
}

static void ap_stop_portal(void)
{
    if (!s_ap_running) {
        return;
    }

    stop_setup_webserver();
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

static void wifi_start_sta_with_portal_window(const char *ssid, const char *password)
{
    wifi_config_t wifi_cfg = { 0 };
    strncpy((char *)wifi_cfg.sta.ssid, ssid, sizeof(wifi_cfg.sta.ssid));
    strncpy((char *)wifi_cfg.sta.password, password ? password : "", sizeof(wifi_cfg.sta.password));

    ESP_ERROR_CHECK(esp_wifi_set_config(WIFI_IF_STA, &wifi_cfg));
    ESP_ERROR_CHECK(esp_wifi_set_mode(WIFI_MODE_APSTA));
    wifi_ensure_started();

    esp_wifi_connect();

    ap_start_portal();
    xTaskCreate(ap_window_task, "ap_window_task", 3072, NULL, 3, NULL);
}

static esp_err_t gps_nvs_load_last_fix(void)
{
    nvs_handle_t h;
    esp_err_t err = nvs_open("gps", NVS_READONLY, &h);
    if (err != ESP_OK) {
        return err;
    }

    gps_fix_nvs_v1_t stored;
    size_t len = sizeof(stored);
    err = nvs_get_blob(h, "last_fix", &stored, &len);
    nvs_close(h);

    if (err != ESP_OK || len != sizeof(stored)) {
        return err;
    }

    s_last_fix.lat_deg = stored.lat_deg;
    s_last_fix.lon_deg = stored.lon_deg;
    s_last_fix.fix_quality = (int)stored.fix_quality;
    s_last_fix.sats_used = (int)stored.sats_used;
    s_last_fix.has_fix = true;
    s_last_fix_valid = true;
    s_last_fix_time_us = esp_timer_get_time();
    return ESP_OK;
}

static esp_err_t gps_nvs_save_last_fix(const gps_fix_t *fix)
{
    if (fix == NULL || !fix->has_fix) {
        return ESP_ERR_INVALID_ARG;
    }

    nvs_handle_t h;
    esp_err_t err = nvs_open("gps", NVS_READWRITE, &h);
    if (err != ESP_OK) {
        return err;
    }

    gps_fix_nvs_v1_t stored = {
        .lat_deg = fix->lat_deg,
        .lon_deg = fix->lon_deg,
        .fix_quality = (int32_t)fix->fix_quality,
        .sats_used = (int32_t)fix->sats_used,
    };

    err = nvs_set_blob(h, "last_fix", &stored, sizeof(stored));
    if (err == ESP_OK) {
        err = nvs_commit(h);
    }
    nvs_close(h);
    return err;
}

static const char *gps_fixq_to_str(int fixq)
{
    switch (fixq) {
    case 0:
        return "INVALID";
    case 1:
        return "GPS";
    case 2:
        return "DGPS";
    case 4:
        return "RTK_FIXED";
    case 5:
        return "RTK_FLOAT";
    case 6:
        return "DR";
    default:
        return "OTHER";
    }
}

static bool nmea_checksum_ok(const char *s) // Checksum validation 
{
    const char *star = strchr(s, '*');
    if (star == NULL || star == s) {
        return false;
    }

    uint8_t calc = 0;
    for (const char *p = s + 1; p < star; p++) {
        calc ^= (uint8_t)(*p);
    }

    if (star[1] == '\0' || star[2] == '\0') {
        return false;
    }

    char hex[3] = { star[1], star[2], 0 };
    char *end = NULL;
    long got = strtol(hex, &end, 16);
    if (end == NULL || *end != 0) {
        return false;
    }
    return (uint8_t)got == calc;
}

static double nmea_degmin_to_deg(const char *dm)
{
    if (dm == NULL || dm[0] == '\0') {
        return NAN;
    }

    double v = atof(dm);
    int deg = (int)(v / 100.0);
    double minutes = v - (deg * 100.0);
    return (double)deg + (minutes / 60.0);
}

static bool nmea_parse_gga(char *line, gps_fix_t *out)
{
    if (out == NULL) {
        return false;
    }

    if (!(strncmp(line, "$GPGGA,", 7) == 0 || strncmp(line, "$GNGGA,", 7) == 0)) {
        return false;
    }

    if (!nmea_checksum_ok(line)) {
        return false;
    }

    char *save = NULL;
    char *tok = strtok_r(line, ",", &save);
    if (tok == NULL) {
        return false;
    }

    (void)strtok_r(NULL, ",", &save);
    const char *lat_s = strtok_r(NULL, ",", &save);
    const char *lat_h = strtok_r(NULL, ",", &save);
    const char *lon_s = strtok_r(NULL, ",", &save);
    const char *lon_h = strtok_r(NULL, ",", &save);
    const char *fix_s = strtok_r(NULL, ",", &save);
    const char *sats_s = strtok_r(NULL, ",", &save);

    double lat = nmea_degmin_to_deg(lat_s);
    double lon = nmea_degmin_to_deg(lon_s);

    if (lat_h && (lat_h[0] == 'S' || lat_h[0] == 's')) {
        lat = -lat;
    }
    if (lon_h && (lon_h[0] == 'W' || lon_h[0] == 'w')) {
        lon = -lon;
    }

    int fixq = (fix_s && fix_s[0]) ? atoi(fix_s) : 0;
    int sats = (sats_s && sats_s[0]) ? atoi(sats_s) : 0;

    out->lat_deg = lat;
    out->lon_deg = lon;
    out->fix_quality = fixq;
    out->sats_used = sats;
    out->has_fix = (fixq > 0) && !isnan(lat) && !isnan(lon);
    return true;
}

static void gps_uart_task(void *arg)
{
    (void)arg;

    const int buf_size = 512;
    uint8_t *buf = (uint8_t *)malloc(buf_size);
    if (buf == NULL) {
        ESP_LOGE(TAG, "Failed to allocate GPS buffer");
        vTaskDelete(NULL);
        return;
    }

    int64_t no_data_ticks = 0;
    char line[128];
    size_t line_len = 0;
    gps_fix_t fix = { 0 };

    while (1) {
        int len = uart_read_bytes(GPS_UART, buf, buf_size, pdMS_TO_TICKS(250));
        if (len <= 0) {
            no_data_ticks++;
            if (no_data_ticks >= 4) {
                size_t buffered = 0;
                ESP_ERROR_CHECK(uart_get_buffered_data_len(GPS_UART, &buffered));
                ESP_LOGW(TAG, "GPS: no data yet (rx buffered=%u)", (unsigned)buffered);
                no_data_ticks = 0;
            }
            continue;
        }

        no_data_ticks = 0;

        for (int i = 0; i < len; i++) {
            const uint8_t c = buf[i];
            if (c == '\r') {
                continue;
            }
            if (c == '\n') {
                if (line_len == 0) {
                    continue;
                }

                line[line_len] = 0;

                char work[128];
                strncpy(work, line, sizeof(work));
                work[sizeof(work) - 1] = 0;

                if (nmea_parse_gga(work, &fix)) {
                    if (fix.has_fix) {
                        s_last_fix = fix;
                        s_last_fix_valid = true;
                        s_last_fix_time_us = esp_timer_get_time();

                        if ((s_last_fix_time_us - s_last_fix_nvs_save_us) > 30000000) {
                            if (gps_nvs_save_last_fix(&fix) == ESP_OK) {
                                s_last_fix_nvs_save_us = s_last_fix_time_us;
                            }
                        }

                        ESP_LOGI(TAG, "GPS FIX: lat=%.6f lon=%.6f sats=%d fixq=%d(%s)", fix.lat_deg, fix.lon_deg, fix.sats_used, fix.fix_quality, gps_fixq_to_str(fix.fix_quality));
                    } else {
                        if (s_last_fix_valid) {
                            int64_t age_s = (esp_timer_get_time() - s_last_fix_time_us) / 1000000;
                            ESP_LOGI(TAG, "GPS NO FIX: sats=%d fixq=%d(%s) last: lat=%.6f lon=%.6f age=%llds", fix.sats_used, fix.fix_quality, gps_fixq_to_str(fix.fix_quality), s_last_fix.lat_deg, s_last_fix.lon_deg, (long long)age_s);
                        } else {
                            ESP_LOGI(TAG, "GPS NO FIX: sats=%d fixq=%d(%s)", fix.sats_used, fix.fix_quality, gps_fixq_to_str(fix.fix_quality));
                        }
                    }
                }

                line_len = 0;
                continue;
            }

            if (line_len < sizeof(line) - 1) {
                line[line_len++] = (char)c;
            } else {
                line_len = 0;
            }
        }
    }
}

static void gpio_test_task(void *arg)
{
    (void)arg;

    int last = gpio_get_level(TEST_INPUT_GPIO);
    ESP_LOGI(TAG, "GPIO input initial level: %d", last);

    int out_level = 0;
    while (1) {
        int cur = gpio_get_level(TEST_INPUT_GPIO);
        if (cur != last) {
            last = cur;
            ESP_LOGI(TAG, "GPIO input changed: %d", cur);
        }

        gpio_set_level(TEST_OUTPUT_GPIO, out_level);
        ESP_LOGI(TAG, "GPIO output set: %d", out_level);

        vTaskDelay(pdMS_TO_TICKS(1000));
    }
}

static void gpio_input_watch_task(void *arg)
{
    (void)arg;
    int last_read = gpio_get_level(TEST_INPUT_GPIO);
    int pending_level = last_read;
    bool pending = false;
    while (1) {
        int cur = gpio_get_level(TEST_INPUT_GPIO);
        if (cur != last_read) {
            ESP_LOGI(TAG, "Input changed: %s=%d", s_cfg.input1_desc, cur);
            last_read = cur;
            pending_level = cur;
            pending = true;
        }

        if (pending) {
            EventBits_t bits = xEventGroupGetBits(s_wifi_event_group);
            if ((bits & WIFI_CONNECTED_BIT) != 0) {
                (void)http_send_input_change(&s_cfg, pending_level);
                pending = false;
            }
        }
        vTaskDelay(pdMS_TO_TICKS(100));
    }
}

static void queue_init(void)
{
    if (s_queue_mutex == NULL) {
        s_queue_mutex = xSemaphoreCreateMutex();
    }

    s_queue_next_seq = 1;
    s_queue_widx = 0;

    nvs_handle_t h;
    if (nvs_open(NYS_QUEUE_NS, NVS_READONLY, &h) == ESP_OK) {
        (void)nvs_get_u32(h, "seq", &s_queue_next_seq);
        (void)nvs_get_u32(h, "widx", &s_queue_widx);
        nvs_close(h);
    }

    if (s_queue_next_seq == 0) {
        s_queue_next_seq = 1;
    }
    if (s_queue_widx >= NYS_QUEUE_SIZE) {
        s_queue_widx = 0;
    }
}

static void queue_save_meta_locked(nvs_handle_t h)
{
    (void)nvs_set_u32(h, "seq", s_queue_next_seq);
    (void)nvs_set_u32(h, "widx", s_queue_widx);
}

static void queue_key_for_idx(char out[8], uint32_t idx)
{
    snprintf(out, 8, "r%02u", (unsigned)idx);
}

static void queue_push_sample(void)
{
    if (s_queue_mutex == NULL) {
        return;
    }

    if (xSemaphoreTake(s_queue_mutex, pdMS_TO_TICKS(200)) != pdTRUE) {
        return;
    }

    nvs_handle_t h;
    esp_err_t err = nvs_open(NYS_QUEUE_NS, NVS_READWRITE, &h);
    if (err != ESP_OK) {
        xSemaphoreGive(s_queue_mutex);
        return;
    }

    int64_t now_s = time_uptime_s();
    bool has_last = s_last_fix_valid && s_last_fix.has_fix;
    bool fresh = false;
    if (has_last) {
        int64_t age_us = esp_timer_get_time() - s_last_fix_time_us;
        fresh = age_us < 5000000;
    }

    nys_queue_rec_t rec = { 0 };
    rec.magic = NYS_QUEUE_MAGIC;
    rec.seq = s_queue_next_seq++;
    rec.ts_s = now_s;
    rec.queued_at_epoch_s = time_now_epoch_s();
    rec.sent = 0;

    if (has_last) {
        rec.has_coords = 1;
        rec.lat_e6 = (int32_t)lrint(s_last_fix.lat_deg * 1000000.0);
        rec.lon_e6 = (int32_t)lrint(s_last_fix.lon_deg * 1000000.0);
        rec.fix_quality = (int16_t)s_last_fix.fix_quality;
        rec.sats_used = (int16_t)s_last_fix.sats_used;
        rec.fix = fresh ? 1 : 0;
        rec.last_known = fresh ? 0 : 1;
    } else {
        rec.has_coords = 0;
        rec.fix = 0;
        rec.last_known = 0;
    }

    char key[8];
    queue_key_for_idx(key, s_queue_widx);
    (void)nvs_set_blob(h, key, &rec, sizeof(rec));

    s_queue_widx = (s_queue_widx + 1) % NYS_QUEUE_SIZE;
    queue_save_meta_locked(h);
    (void)nvs_commit(h);
    nvs_close(h);

    xSemaphoreGive(s_queue_mutex);
}

static bool queue_load_rec_locked(nvs_handle_t h, uint32_t idx, nys_queue_rec_t *out)
{
    if (out == NULL) {
        return false;
    }
    char key[8];
    queue_key_for_idx(key, idx);
    size_t len = sizeof(*out);
    esp_err_t err = nvs_get_blob(h, key, out, &len);
    if (err != ESP_OK) {
        return false;
    }
    if (len == sizeof(*out)) {
        if (out->magic != NYS_QUEUE_MAGIC) {
            return false;
        }
        return true;
    }
    if (len == sizeof(nys_queue_rec_v1_t)) {
        nys_queue_rec_v1_t v1;
        memcpy(&v1, out, sizeof(v1));
        if (v1.magic != NYS_QUEUE_MAGIC) {
            return false;
        }
        memset(out, 0, sizeof(*out));
        out->magic = v1.magic;
        out->seq = v1.seq;
        out->ts_s = v1.ts_s;
        out->queued_at_epoch_s = 0;
        out->lat_e6 = v1.lat_e6;
        out->lon_e6 = v1.lon_e6;
        out->fix_quality = v1.fix_quality;
        out->sats_used = v1.sats_used;
        out->has_coords = v1.has_coords;
        out->fix = v1.fix;
        out->last_known = v1.last_known;
        out->sent = v1.sent;
        return true;
    }
    return false;
}

static void queue_mark_sent_locked(nvs_handle_t h, uint32_t idx, nys_queue_rec_t *rec)
{
    if (rec == NULL) {
        return;
    }
    rec->sent = 1;
    char key[8];
    queue_key_for_idx(key, idx);
    (void)nvs_set_blob(h, key, rec, sizeof(*rec));
}

static void queue_drain_step(void)
{
    if (s_queue_mutex == NULL) {
        return;
    }
    EventBits_t bits = xEventGroupGetBits(s_wifi_event_group);
    if ((bits & WIFI_CONNECTED_BIT) == 0) {
        return;
    }

    if (xSemaphoreTake(s_queue_mutex, pdMS_TO_TICKS(200)) != pdTRUE) {
        return;
    }

    nvs_handle_t h;
    esp_err_t err = nvs_open(NYS_QUEUE_NS, NVS_READWRITE, &h);
    if (err != ESP_OK) {
        xSemaphoreGive(s_queue_mutex);
        return;
    }

    uint32_t best_idx = UINT32_MAX;
    uint32_t best_seq = 0;
    nys_queue_rec_t best = { 0 };

    for (uint32_t i = 0; i < NYS_QUEUE_SIZE; i++) {
        nys_queue_rec_t cur;
        if (!queue_load_rec_locked(h, i, &cur)) {
            continue;
        }
        if (cur.sent) {
            continue;
        }
        if (best_idx == UINT32_MAX || cur.seq < best_seq) {
            best_idx = i;
            best_seq = cur.seq;
            best = cur;
        }
    }

    if (best_idx == UINT32_MAX) {
        nvs_close(h);
        xSemaphoreGive(s_queue_mutex);
        return;
    }

    nvs_close(h);
    xSemaphoreGive(s_queue_mutex);

    if (http_send_location_record(&s_cfg, &best) == ESP_OK) {
        if (xSemaphoreTake(s_queue_mutex, pdMS_TO_TICKS(200)) == pdTRUE) {
            if (nvs_open(NYS_QUEUE_NS, NVS_READWRITE, &h) == ESP_OK) {
                nys_queue_rec_t to_update;
                if (queue_load_rec_locked(h, best_idx, &to_update) && to_update.seq == best.seq) {
                    queue_mark_sent_locked(h, best_idx, &to_update);
                    queue_save_meta_locked(h);
                    (void)nvs_commit(h);
                }
                nvs_close(h);
            }
            xSemaphoreGive(s_queue_mutex);
        }
    }
}

static esp_err_t http_send_location_record(const nys_cfg_t *cfg, const nys_queue_rec_t *rec)
{
    if (cfg == NULL || rec == NULL) {
        return ESP_ERR_INVALID_ARG;
    }

    cJSON *root = cJSON_CreateObject();
    if (!root) {
        return ESP_ERR_NO_MEM;
    }

    cJSON_AddNumberToObject(root, "uptime_s", (double)rec->ts_s);
    if (rec->queued_at_epoch_s > 0) {
        cJSON_AddNumberToObject(root, "message_timestamp", (double)rec->queued_at_epoch_s);
    }

    cJSON *gps = cJSON_AddObjectToObject(root, "gps");
    if (gps) {
        if (rec->has_coords) {
            cJSON_AddNumberToObject(gps, "lat", ((double)rec->lat_e6) / 1000000.0);
            cJSON_AddNumberToObject(gps, "lng", ((double)rec->lon_e6) / 1000000.0);
        }
        cJSON_AddNumberToObject(gps, "fix_quality", rec->fix_quality);
        cJSON_AddNumberToObject(gps, "satellites", rec->sats_used);
        cJSON_AddBoolToObject(gps, "fix", rec->fix ? true : false);
        if (rec->last_known) {
            cJSON_AddBoolToObject(gps, "last_known", true);
        }
    }

    char *json = cJSON_PrintUnformatted(root);
    cJSON_Delete(root);
    if (!json) {
        return ESP_ERR_NO_MEM;
    }

    char sig[65];
    if (!hmac_sha256_hex(cfg->device_key, json, sig)) {
        free(json);
        return ESP_FAIL;
    }

    esp_http_client_config_t http_cfg = {
        .url = NYS_API_URL,
        .method = HTTP_METHOD_POST,
        .timeout_ms = 10000,
        .disable_auto_redirect = false,
        .keep_alive_enable = false,
    };

    esp_http_client_handle_t client = esp_http_client_init(&http_cfg);
    if (!client) {
        free(json);
        return ESP_FAIL;
    }

    esp_http_client_set_header(client, "Content-Type", "application/json");
    esp_http_client_set_header(client, "Accept", "application/json");
    esp_http_client_set_header(client, "Connection", "close");
    esp_http_client_set_header(client, "X-DEVICE-ID", cfg->device_uid);
    esp_http_client_set_header(client, "X-DEVICE-SIGNATURE", sig);
    esp_http_client_set_post_field(client, json, (int)strlen(json));

    esp_err_t err = esp_http_client_perform(client);
    int status = esp_http_client_get_status_code(client);
    if (err == ESP_OK) {
        ESP_LOGI(TAG, "POST status=%d", status);
    } else if (err == ESP_ERR_HTTP_INCOMPLETE_DATA && status >= 200 && status < 300) {
        ESP_LOGW(TAG, "POST incomplete data but status=%d; treating as success", status);
        err = ESP_OK;
    } else {
        ESP_LOGE(TAG, "POST failed: %s (status=%d)", esp_err_to_name(err), status);
    }

    esp_http_client_cleanup(client);
    free(json);
    return err;
}

static void gps_sample_task(void *arg)
{
    (void)arg;
    while (1) {
        uint32_t interval_s = s_cfg.location_interval_s;
        if (interval_s == 0) {
            interval_s = 60;
        }
        queue_push_sample();
        vTaskDelay(pdMS_TO_TICKS((int)(interval_s * 1000)));
    }
}

void app_main(void)
{
    esp_err_t err = nvs_flash_init();
    if (err == ESP_ERR_NVS_NO_FREE_PAGES || err == ESP_ERR_NVS_NEW_VERSION_FOUND) {
        ESP_ERROR_CHECK(nvs_flash_erase());
        err = nvs_flash_init();
    }
    ESP_ERROR_CHECK(err);

    ESP_ERROR_CHECK(esp_netif_init());
    ESP_ERROR_CHECK(esp_event_loop_create_default());

    esp_log_level_set("HTTP_CLIENT", ESP_LOG_WARN);

    (void)cfg_load(&s_cfg);
    cfg_ensure_identity(&s_cfg);

    time_init();
    queue_init();

    if (!s_cfg.has_wifi) {
        ESP_LOGW(TAG, "No WiFi configured. Starting setup portal.");
        wifi_ensure_inited(false, true);
        ap_start_portal();
    } else {
        wifi_ensure_inited(true, true);
        wifi_start_sta_with_portal_window(s_cfg.ssid, s_cfg.password);
        xTaskCreate(send_task, "send_task", 6144, NULL, 5, NULL);
    }

    if (gps_nvs_load_last_fix() == ESP_OK) {
        ESP_LOGI(TAG, "Restored last fix from NVS: lat=%.6f lon=%.6f sats=%d fixq=%d(%s)", s_last_fix.lat_deg, s_last_fix.lon_deg, s_last_fix.sats_used, s_last_fix.fix_quality, gps_fixq_to_str(s_last_fix.fix_quality));
    }

    uart_config_t uart_config = {
        .baud_rate = GPS_BAUD_RATE,
        .data_bits = UART_DATA_8_BITS,
        .parity = UART_PARITY_DISABLE,
        .stop_bits = UART_STOP_BITS_1,
        .flow_ctrl = UART_HW_FLOWCTRL_DISABLE,
        .source_clk = UART_SCLK_DEFAULT,
    };

    ESP_ERROR_CHECK(uart_driver_install(GPS_UART, 2048, 0, 0, NULL, 0));
    ESP_ERROR_CHECK(uart_param_config(GPS_UART, &uart_config));
    ESP_ERROR_CHECK(uart_set_rx_timeout(GPS_UART, 10));

#if GPS_SWAP_RX_TX
    ESP_ERROR_CHECK(uart_set_pin(GPS_UART, GPS_RX_GPIO, GPS_TX_GPIO, UART_PIN_NO_CHANGE, UART_PIN_NO_CHANGE));
#else
    ESP_ERROR_CHECK(uart_set_pin(GPS_UART, GPS_TX_GPIO, GPS_RX_GPIO, UART_PIN_NO_CHANGE, UART_PIN_NO_CHANGE));
#endif

    gpio_config_t in_conf = {
        .pin_bit_mask = 1ULL << TEST_INPUT_GPIO,
        .mode = GPIO_MODE_INPUT,
        .pull_up_en = GPIO_PULLUP_ENABLE,
        .pull_down_en = GPIO_PULLDOWN_DISABLE,
        .intr_type = GPIO_INTR_DISABLE,
    };
    ESP_ERROR_CHECK(gpio_config(&in_conf));

    gpio_config_t out_conf = {
        .pin_bit_mask = 1ULL << TEST_OUTPUT_GPIO,
        .mode = GPIO_MODE_OUTPUT,
        .pull_up_en = GPIO_PULLUP_DISABLE,
        .pull_down_en = GPIO_PULLDOWN_DISABLE,
        .intr_type = GPIO_INTR_DISABLE,
    };
    ESP_ERROR_CHECK(gpio_config(&out_conf));

    ESP_LOGI(TAG, "Starting GPS UART on RX=%d TX=%d @ %d baud", (int)GPS_RX_GPIO, (int)GPS_TX_GPIO, GPS_BAUD_RATE);
    ESP_LOGI(TAG, "GPIO test: input=%d output=%d", (int)TEST_INPUT_GPIO, (int)TEST_OUTPUT_GPIO);

    xTaskCreate(gps_uart_task, "gps_uart_task", 4096, NULL, 5, NULL);
    xTaskCreate(gpio_test_task, "gpio_test_task", 3072, NULL, 5, NULL);
    xTaskCreate(gpio_input_watch_task, "gpio_input_watch_task", 3072, NULL, 5, NULL);
    xTaskCreate(gps_sample_task, "gps_sample_task", 4096, NULL, 5, NULL);
}
