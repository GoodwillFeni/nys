#include "comms.h"

#include <stdint.h>
#include <string.h>
#include <stdlib.h>
#include <time.h>
#include <math.h>

#include "freertos/FreeRTOS.h"
#include "freertos/task.h"
#include "freertos/event_groups.h"
#include "freertos/semphr.h"

#include "esp_log.h"
#include "esp_timer.h"
#include "esp_http_client.h"
#include "esp_sntp.h"
#include "esp_mac.h"
#include "nvs.h"
#include "nvs_flash.h"
#include "cJSON.h"
#include "mbedtls/md.h"

#include "driver/gpio.h"

#include "nys_common.h"
#include "wifi.h"
#include "gps.h"

static const char *TAG = "COMMS";

// ─── Shared state ─────────────────────────────────────────────────────────────
nys_cfg_t         s_cfg;
SemaphoreHandle_t s_queue_mutex;
uint32_t          s_queue_next_seq;
uint32_t          s_queue_widx;

// ─── Private time state ───────────────────────────────────────────────────────
static bool    s_time_has_base;
static bool    s_time_from_gps;
static int64_t s_time_base_epoch_s;
static int64_t s_time_base_uptime_s;
static bool    s_sntp_started;

// ════════════════════════════════════════════════════════════════════════════
// CONFIG
// ════════════════════════════════════════════════════════════════════════════

esp_err_t cfg_load(nys_cfg_t *out)
{
    if (!out) return ESP_ERR_INVALID_ARG;
    memset(out, 0, sizeof(*out));

    out->heartbeat_interval_s = 60;
    out->location_interval_s  = 60;
    strncpy(out->api_url,     NYS_API_URL, sizeof(out->api_url) - 1);
    strncpy(out->input1_desc, "Input 1",   sizeof(out->input1_desc) - 1);

    nvs_handle_t h;
    if (nvs_open("cfg", NVS_READONLY, &h) != ESP_OK) return ESP_OK;

    size_t n;
    n = sizeof(out->ssid);
    if (nvs_get_str(h, "ssid", out->ssid, &n) == ESP_OK && out->ssid[0]) {
        out->has_wifi = true;
        n = sizeof(out->password);
        (void)nvs_get_str(h, "pass", out->password, &n);
    }
    n = sizeof(out->api_url);     (void)nvs_get_str(h, "api_url",  out->api_url,     &n);
    n = sizeof(out->device_uid);  (void)nvs_get_str(h, "uid",      out->device_uid,  &n);
    n = sizeof(out->device_key);  (void)nvs_get_str(h, "key",      out->device_key,  &n);
    n = sizeof(out->input1_desc); (void)nvs_get_str(h, "in1_desc", out->input1_desc, &n);
    (void)nvs_get_u32(h, "hb_int",  &out->heartbeat_interval_s);
    (void)nvs_get_u32(h, "loc_int", &out->location_interval_s);

    uint8_t ds = 0;
    if (nvs_get_u8(h, "ds_en", &ds) == ESP_OK) out->deep_sleep_enabled = (ds != 0);

    if (out->heartbeat_interval_s == 0) out->heartbeat_interval_s = 60;
    if (out->location_interval_s  == 0) out->location_interval_s  = 60;
    if (out->input1_desc[0] == 0) strncpy(out->input1_desc, "Input 1", sizeof(out->input1_desc) - 1);

    nvs_close(h);
    return ESP_OK;
}

esp_err_t cfg_save_wifi(const char *ssid, const char *password)
{
    nvs_handle_t h;
    esp_err_t err = nvs_open("cfg", NVS_READWRITE, &h);
    if (err != ESP_OK) return err;
    err = nvs_set_str(h, "ssid", ssid);
    if (err == ESP_OK) err = nvs_set_str(h, "pass", password ? password : "");
    if (err == ESP_OK) err = nvs_commit(h);
    nvs_close(h);
    if (err == ESP_OK) cfg_save_network(ssid, password);
    return err;
}

esp_err_t cfg_save_settings(uint32_t hb_s, uint32_t loc_s,
                              const char *in1_desc, const char *api_url,
                              int deep_sleep_enabled)
{
    nvs_handle_t h;
    esp_err_t err = nvs_open("cfg", NVS_READWRITE, &h);
    if (err != ESP_OK) return err;
    err = nvs_set_u32(h, "hb_int",  hb_s);
    if (err == ESP_OK) err = nvs_set_u32(h, "loc_int", loc_s);
    if (err == ESP_OK && in1_desc) err = nvs_set_str(h, "in1_desc", in1_desc);
    if (err == ESP_OK && api_url)  err = nvs_set_str(h, "api_url",  api_url);
    if (err == ESP_OK && deep_sleep_enabled >= 0)
        err = nvs_set_u8(h, "ds_en", (uint8_t)(deep_sleep_enabled ? 1 : 0));
    if (err == ESP_OK) err = nvs_commit(h);
    nvs_close(h);
    return err;
}

void cfg_ensure_identity(nys_cfg_t *cfg)
{
    if (!cfg) return;

    if (cfg->device_uid[0] == 0) {
        uint8_t mac[6] = {0};
        esp_read_mac(mac, ESP_MAC_WIFI_STA);
        snprintf(cfg->device_uid, sizeof(cfg->device_uid),
                 "%02X%02X%02X%02X%02X%02X",
                 mac[0], mac[1], mac[2], mac[3], mac[4], mac[5]);
    }

    if (cfg->device_key[0] == 0) {
        static const char hex[] = "0123456789abcdef";
        uint8_t rnd[32];
        for (int i = 0; i < (int)sizeof(rnd); i++) rnd[i] = (uint8_t)esp_random();
        size_t pos = 0;
        for (int i = 0; i < (int)sizeof(rnd) && pos + 2 < sizeof(cfg->device_key); i++) {
            cfg->device_key[pos++] = hex[(rnd[i] >> 4) & 0xF];
            cfg->device_key[pos++] = hex[rnd[i] & 0xF];
        }
        cfg->device_key[pos] = 0;
    }

    nvs_handle_t h;
    if (nvs_open("cfg", NVS_READWRITE, &h) == ESP_OK) {
        (void)nvs_set_str(h, "uid", cfg->device_uid);
        (void)nvs_set_str(h, "key", cfg->device_key);
        (void)nvs_commit(h);
        nvs_close(h);
    }

    ESP_LOGI(TAG, "Device UID: %s", cfg->device_uid);
    ESP_LOGI(TAG, "Device KEY: %s", cfg->device_key);
}

// ════════════════════════════════════════════════════════════════════════════
// SAVED NETWORKS
// ════════════════════════════════════════════════════════════════════════════

static void net_ssid_key(char out[4], int i) { snprintf(out, 4, "s%d", i); }
static void net_pass_key(char out[4], int i) { snprintf(out, 4, "p%d", i); }

int cfg_load_networks(nys_network_t out[NYS_MAX_NETWORKS])
{
    memset(out, 0, sizeof(nys_network_t) * NYS_MAX_NETWORKS);
    nvs_handle_t h;
    if (nvs_open(NYS_NETWORKS_NS, NVS_READONLY, &h) != ESP_OK) return 0;

    uint8_t count = 0;
    (void)nvs_get_u8(h, "count", &count);
    if (count > NYS_MAX_NETWORKS) count = NYS_MAX_NETWORKS;

    for (int i = 0; i < (int)count; i++) {
        char sk[4], pk[4];
        net_ssid_key(sk, i);
        net_pass_key(pk, i);
        size_t n = sizeof(out[i].ssid);
        (void)nvs_get_str(h, sk, out[i].ssid, &n);
        n = sizeof(out[i].password);
        (void)nvs_get_str(h, pk, out[i].password, &n);
    }
    nvs_close(h);
    return (int)count;
}

static esp_err_t networks_save_all(nvs_handle_t h,
                                   const nys_network_t nets[NYS_MAX_NETWORKS],
                                   int count)
{
    esp_err_t err = nvs_set_u8(h, "count", (uint8_t)count);
    for (int i = 0; i < count && err == ESP_OK; i++) {
        char sk[4], pk[4];
        net_ssid_key(sk, i);
        net_pass_key(pk, i);
        err = nvs_set_str(h, sk, nets[i].ssid);
        if (err == ESP_OK) err = nvs_set_str(h, pk, nets[i].password);
    }
    if (err == ESP_OK) err = nvs_commit(h);
    return err;
}

esp_err_t cfg_save_network(const char *ssid, const char *password)
{
    if (!ssid || ssid[0] == 0) return ESP_ERR_INVALID_ARG;

    nys_network_t nets[NYS_MAX_NETWORKS];
    int count = cfg_load_networks(nets);

    for (int i = 0; i < count; i++) {
        if (strcmp(nets[i].ssid, ssid) == 0) {
            strncpy(nets[i].password, password ? password : "",
                    sizeof(nets[i].password) - 1);
            goto save;
        }
    }

    if (count >= NYS_MAX_NETWORKS) count = NYS_MAX_NETWORKS - 1;
    for (int i = count; i > 0; i--) nets[i] = nets[i - 1];
    strncpy(nets[0].ssid,     ssid,                    sizeof(nets[0].ssid) - 1);
    strncpy(nets[0].password, password ? password : "", sizeof(nets[0].password) - 1);
    count++;

save:;
    nvs_handle_t h;
    esp_err_t err = nvs_open(NYS_NETWORKS_NS, NVS_READWRITE, &h);
    if (err != ESP_OK) return err;
    err = networks_save_all(h, nets, count);
    nvs_close(h);
    ESP_LOGI(TAG, "Saved network: %s (%d total)", ssid, count);
    return err;
}

esp_err_t cfg_delete_network(const char *ssid)
{
    if (!ssid || ssid[0] == 0) return ESP_ERR_INVALID_ARG;

    nys_network_t nets[NYS_MAX_NETWORKS];
    int count = cfg_load_networks(nets);

    int found = -1;
    for (int i = 0; i < count; i++) {
        if (strcmp(nets[i].ssid, ssid) == 0) { found = i; break; }
    }
    if (found < 0) return ESP_ERR_NOT_FOUND;

    for (int i = found; i < count - 1; i++) nets[i] = nets[i + 1];
    count--;

    nvs_handle_t h;
    esp_err_t err = nvs_open(NYS_NETWORKS_NS, NVS_READWRITE, &h);
    if (err != ESP_OK) return err;
    err = networks_save_all(h, nets, count);
    nvs_close(h);
    ESP_LOGI(TAG, "Deleted network: %s (%d remaining)", ssid, count);
    return err;
}

esp_err_t cfg_set_last_connected(const char *ssid)
{
    if (!ssid || ssid[0] == 0) return ESP_ERR_INVALID_ARG;

    nys_network_t nets[NYS_MAX_NETWORKS];
    int count = cfg_load_networks(nets);

    int found = -1;
    for (int i = 0; i < count; i++) {
        if (strcmp(nets[i].ssid, ssid) == 0) { found = i; break; }
    }
    if (found <= 0) return ESP_OK;

    nys_network_t tmp = nets[found];
    for (int i = found; i > 0; i--) nets[i] = nets[i - 1];
    nets[0] = tmp;

    nvs_handle_t h;
    esp_err_t err = nvs_open(NYS_NETWORKS_NS, NVS_READWRITE, &h);
    if (err != ESP_OK) return err;
    err = networks_save_all(h, nets, count);
    nvs_close(h);
    return err;
}

// ════════════════════════════════════════════════════════════════════════════
// TIME
// ════════════════════════════════════════════════════════════════════════════

int64_t time_uptime_s(void) { return esp_timer_get_time() / 1000000; }

static void time_set_base(int64_t epoch_s)
{
    s_time_has_base      = true;
    s_time_base_epoch_s  = epoch_s;
    s_time_base_uptime_s = time_uptime_s();
    nvs_handle_t h;
    if (nvs_open(NYS_TIME_NS, NVS_READWRITE, &h) == ESP_OK) {
        (void)nvs_set_i64(h, "epoch",  s_time_base_epoch_s);
        (void)nvs_set_i64(h, "uptime", s_time_base_uptime_s);
        (void)nvs_commit(h);
        nvs_close(h);
    }
}

void time_init(void)
{
    nvs_handle_t h;
    if (nvs_open(NYS_TIME_NS, NVS_READONLY, &h) != ESP_OK) return;
    int64_t epoch = 0, up = 0;
    if (nvs_get_i64(h, "epoch",  &epoch) == ESP_OK &&
        nvs_get_i64(h, "uptime", &up)    == ESP_OK &&
        epoch >= NYS_TIME_VALID_EPOCH_MIN && up >= 0) {
        s_time_has_base      = true;
        s_time_from_gps      = false;
        s_time_base_epoch_s  = epoch;
        s_time_base_uptime_s = up;
    }
    nvs_close(h);
}

void time_update_from_gps(int64_t gps_epoch_s)
{
    if (gps_epoch_s < NYS_TIME_VALID_EPOCH_MIN) return;
    bool was_set = s_time_has_base;
    s_time_from_gps = true;
    time_set_base(gps_epoch_s);
    if (!was_set) ESP_LOGI(TAG, "Time base set from GPS: %lld", (long long)gps_epoch_s);
}

void time_try_update_base_from_system(void)
{
    if (s_time_from_gps) return;
    time_t now = time(NULL);
    if ((int64_t)now < NYS_TIME_VALID_EPOCH_MIN) return;
    time_set_base((int64_t)now);
    ESP_LOGI(TAG, "Time base set from SNTP: %lld", (long long)now);
}

int64_t time_now_epoch_s(void)
{
    if (!s_time_has_base) return 0;
    int64_t delta = time_uptime_s() - s_time_base_uptime_s;
    return s_time_base_epoch_s + (delta > 0 ? delta : 0);
}

static void time_sntp_sync_cb(struct timeval *tv)
{
    (void)tv;
    ESP_LOGI(TAG, "SNTP synced");
    time_try_update_base_from_system();
}

void time_maybe_start_sntp(void)
{
    if (s_sntp_started) return;
    s_sntp_started = true;
    esp_sntp_setoperatingmode(SNTP_OPMODE_POLL);
    esp_sntp_setservername(0, "pool.ntp.org");
    esp_sntp_set_time_sync_notification_cb(time_sntp_sync_cb);
    esp_sntp_init();
}

// ════════════════════════════════════════════════════════════════════════════
// HTTP
// ════════════════════════════════════════════════════════════════════════════

static bool hmac_sha256_hex(const char *key, const char *msg, char out[65])
{
    const mbedtls_md_info_t *info = mbedtls_md_info_from_type(MBEDTLS_MD_SHA256);
    if (!key || !msg || !out || !info) return false;
    unsigned char        hmac[32];
    mbedtls_md_context_t ctx;
    mbedtls_md_init(&ctx);
    int rc = mbedtls_md_setup(&ctx, info, 1);
    if (rc == 0) rc = mbedtls_md_hmac_starts(&ctx, (const unsigned char *)key, strlen(key));
    if (rc == 0) rc = mbedtls_md_hmac_update(&ctx, (const unsigned char *)msg, strlen(msg));
    if (rc == 0) rc = mbedtls_md_hmac_finish(&ctx, hmac);
    mbedtls_md_free(&ctx);
    if (rc != 0) return false;
    static const char hex[] = "0123456789abcdef";
    for (int i = 0; i < 32; i++) {
        out[i * 2]     = hex[(hmac[i] >> 4) & 0xF];
        out[i * 2 + 1] = hex[hmac[i] & 0xF];
    }
    out[64] = 0;
    return true;
}

static esp_err_t http_post_json(const nys_cfg_t *cfg, const char *json)
{
    char sig[65];
    if (!hmac_sha256_hex(cfg->device_key, json, sig)) return ESP_FAIL;

    esp_http_client_config_t http_cfg = {
        .url        = cfg->api_url[0] ? cfg->api_url : NYS_API_URL,
        .method     = HTTP_METHOD_POST,
        .timeout_ms = 10000,
    };
    esp_http_client_handle_t client = esp_http_client_init(&http_cfg);
    if (!client) return ESP_FAIL;

    esp_http_client_set_header(client, "Content-Type",       "application/json");
    esp_http_client_set_header(client, "Connection",         "close");
    esp_http_client_set_header(client, "X-DEVICE-ID",        cfg->device_uid);
    esp_http_client_set_header(client, "X-DEVICE-SIGNATURE", sig);
    esp_http_client_set_header(client, "X-DEVICE-KEY",       cfg->device_key);
    esp_http_client_set_post_field(client, json, (int)strlen(json));

    esp_err_t err    = esp_http_client_perform(client);
    int       status = esp_http_client_get_status_code(client);

    if (err == ESP_OK || (err == ESP_ERR_HTTP_INCOMPLETE_DATA && status >= 200 && status < 300)) {
        err = (status >= 200 && status < 300) ? ESP_OK : ESP_FAIL;
        ESP_LOGI(TAG, "POST status=%d", status);
    } else {
        ESP_LOGE(TAG, "POST failed: %s", esp_err_to_name(err));
    }

    esp_http_client_cleanup(client);
    return err;
}

esp_err_t http_send_heartbeat(const nys_cfg_t *cfg)
{
    if (!cfg) return ESP_ERR_INVALID_ARG;
    cJSON *root = cJSON_CreateObject();
    if (!root) return ESP_ERR_NO_MEM;
    cJSON_AddNumberToObject(root, "uptime_s", (double)time_uptime_s());
    int64_t epoch = time_now_epoch_s();
    if (epoch > 0) cJSON_AddNumberToObject(root, "message_timestamp", (double)epoch);
    char *json = cJSON_PrintUnformatted(root);
    cJSON_Delete(root);
    if (!json) return ESP_ERR_NO_MEM;
    esp_err_t err = http_post_json(cfg, json);
    free(json);
    return err;
}

esp_err_t http_send_input_change(const nys_cfg_t *cfg, int level)
{
    if (!cfg) return ESP_ERR_INVALID_ARG;
    cJSON *root = cJSON_CreateObject();
    if (!root) return ESP_ERR_NO_MEM;
    cJSON_AddNumberToObject(root, "uptime_s", (double)time_uptime_s());
    int64_t epoch = time_now_epoch_s();
    if (epoch > 0) cJSON_AddNumberToObject(root, "message_timestamp", (double)epoch);
    cJSON *inputs = cJSON_AddObjectToObject(root, "inputs");
    cJSON *in1    = inputs ? cJSON_AddObjectToObject(inputs, "input1") : NULL;
    if (in1) {
        cJSON_AddStringToObject(in1, "description", cfg->input1_desc);
        cJSON_AddNumberToObject(in1, "state",       level);
    }
    char *json = cJSON_PrintUnformatted(root);
    cJSON_Delete(root);
    if (!json) return ESP_ERR_NO_MEM;
    esp_err_t err = http_post_json(cfg, json);
    free(json);
    return err;
}

static esp_err_t http_send_location_record(const nys_cfg_t *cfg, const nys_queue_rec_t *rec)
{
    if (!cfg || !rec) return ESP_ERR_INVALID_ARG;
    cJSON *root = cJSON_CreateObject();
    if (!root) return ESP_ERR_NO_MEM;
    cJSON_AddNumberToObject(root, "uptime_s", (double)rec->ts_s);
    if (rec->queued_at_epoch_s > 0)
        cJSON_AddNumberToObject(root, "message_timestamp", (double)rec->queued_at_epoch_s);
    cJSON *gps = cJSON_AddObjectToObject(root, "gps");
    if (gps) {
        if (rec->has_coords) {
            cJSON_AddNumberToObject(gps, "lat", ((double)rec->lat_e6) / 1000000.0);
            cJSON_AddNumberToObject(gps, "lng", ((double)rec->lon_e6) / 1000000.0);
        }
        cJSON_AddNumberToObject(gps, "fix_quality", rec->fix_quality);
        cJSON_AddNumberToObject(gps, "satellites",  rec->sats_used);
        cJSON_AddBoolToObject  (gps, "fix",         rec->fix ? true : false);
        if (rec->last_known) cJSON_AddBoolToObject(gps, "last_known", true);
    }
    char *json = cJSON_PrintUnformatted(root);
    cJSON_Delete(root);
    if (!json) return ESP_ERR_NO_MEM;
    esp_err_t err = http_post_json(cfg, json);
    free(json);
    return err;
}

// ════════════════════════════════════════════════════════════════════════════
// QUEUE
// ════════════════════════════════════════════════════════════════════════════

static void queue_key_for_idx(char out[8], uint32_t idx) { snprintf(out, 8, "r%02u", (unsigned)idx); }

static void queue_save_meta_locked(nvs_handle_t h)
{
    (void)nvs_set_u32(h, "seq",  s_queue_next_seq);
    (void)nvs_set_u32(h, "widx", s_queue_widx);
}

void queue_init(void)
{
    if (!s_queue_mutex) s_queue_mutex = xSemaphoreCreateMutex();
    s_queue_next_seq = 1;
    s_queue_widx     = 0;
    nvs_handle_t h;
    if (nvs_open(NYS_QUEUE_NS, NVS_READONLY, &h) == ESP_OK) {
        (void)nvs_get_u32(h, "seq",  &s_queue_next_seq);
        (void)nvs_get_u32(h, "widx", &s_queue_widx);
        nvs_close(h);
    }
    if (s_queue_next_seq == 0)          s_queue_next_seq = 1;
    if (s_queue_widx >= NYS_QUEUE_SIZE) s_queue_widx = 0;
    (void)queue_purge_sent();
}

bool queue_load_rec_locked(nvs_handle_t h, uint32_t idx, nys_queue_rec_t *out)
{
    if (!out) return false;
    char key[8];
    queue_key_for_idx(key, idx);
    size_t len = sizeof(*out);
    if (nvs_get_blob(h, key, out, &len) != ESP_OK || len != sizeof(*out)) return false;
    return out->magic == NYS_QUEUE_MAGIC;
}

void queue_delete_rec_locked(nvs_handle_t h, uint32_t idx)
{
    char key[8];
    queue_key_for_idx(key, idx);
    (void)nvs_erase_key(h, key);
}

void queue_push_sample(void)
{
    if (!s_queue_mutex) return;
    if (xSemaphoreTake(s_queue_mutex, pdMS_TO_TICKS(200)) != pdTRUE) return;

    nvs_handle_t h;
    if (nvs_open(NYS_QUEUE_NS, NVS_READWRITE, &h) != ESP_OK) {
        xSemaphoreGive(s_queue_mutex); return;
    }

    gps_fix_t fix     = {0};
    bool      has_fix = gps_get_last_fix(&fix) && fix.has_fix;

    nys_queue_rec_t rec = {0};
    rec.magic             = NYS_QUEUE_MAGIC;
    rec.seq               = s_queue_next_seq++;
    rec.ts_s              = time_uptime_s();
    rec.queued_at_epoch_s = time_now_epoch_s();

    if (has_fix) {
        rec.has_coords  = 1;
        rec.fix         = 1;
        rec.lat_e6      = (int32_t)lrint(fix.lat_deg * 1000000.0);
        rec.lon_e6      = (int32_t)lrint(fix.lon_deg * 1000000.0);
        rec.fix_quality = (int16_t)fix.fix_quality;
        rec.sats_used   = (int16_t)fix.sats_used;
        rec.last_known  = 0;
    } else if (gps_get_last_fix(&fix)) {
        // No current fix but have a last known position
        rec.has_coords  = 1;
        rec.fix         = 0;
        rec.lat_e6      = (int32_t)lrint(fix.lat_deg * 1000000.0);
        rec.lon_e6      = (int32_t)lrint(fix.lon_deg * 1000000.0);
        rec.fix_quality = 0;
        rec.sats_used   = 0;
        rec.last_known  = 1;
    }

    char key[8];
    queue_key_for_idx(key, s_queue_widx);
    esp_err_t wr_err = nvs_set_blob(h, key, &rec, sizeof(rec));
    s_queue_widx = (s_queue_widx + 1) % NYS_QUEUE_SIZE;
    queue_save_meta_locked(h);
    esp_err_t cm_err = nvs_commit(h);
    nvs_close(h);
    xSemaphoreGive(s_queue_mutex);

    ESP_LOGI(TAG, "Queue push: seq=%u idx=%s fix=%d coords=%d wr=%s commit=%s",
             (unsigned)rec.seq, key, rec.fix, rec.has_coords,
             esp_err_to_name(wr_err), esp_err_to_name(cm_err));
}

uint32_t queue_count_unsent(void)
{
    if (!s_queue_mutex) return 0;
    if (xSemaphoreTake(s_queue_mutex, pdMS_TO_TICKS(200)) != pdTRUE) return 0;
    uint32_t count = 0;
    nvs_handle_t h;
    if (nvs_open(NYS_QUEUE_NS, NVS_READONLY, &h) == ESP_OK) {
        for (uint32_t i = 0; i < NYS_QUEUE_SIZE; i++) {
            nys_queue_rec_t rec;
            if (queue_load_rec_locked(h, i, &rec) && !rec.sent) count++;
        }
        nvs_close(h);
    }
    xSemaphoreGive(s_queue_mutex);
    return count;
}

uint32_t queue_purge_sent(void)
{
    if (!s_queue_mutex) return 0;
    if (xSemaphoreTake(s_queue_mutex, pdMS_TO_TICKS(500)) != pdTRUE) return 0;
    uint32_t purged = 0;
    nvs_handle_t h;
    if (nvs_open(NYS_QUEUE_NS, NVS_READWRITE, &h) == ESP_OK) {
        for (uint32_t i = 0; i < NYS_QUEUE_SIZE; i++) {
            nys_queue_rec_t rec;
            if (queue_load_rec_locked(h, i, &rec) && rec.sent) {
                queue_delete_rec_locked(h, i);
                purged++;
            }
        }
        if (purged > 0) { queue_save_meta_locked(h); (void)nvs_commit(h); }
        nvs_close(h);
    }
    xSemaphoreGive(s_queue_mutex);
    if (purged > 0) ESP_LOGI(TAG, "Purged %u sent records", (unsigned)purged);
    return purged;
}

void queue_drain_step(void)
{
    if (!s_queue_mutex) {
        ESP_LOGW(TAG, "drain: no mutex");
        return;
    }
    if (!(xEventGroupGetBits(s_wifi_event_group) & WIFI_CONNECTED_BIT)) {
        ESP_LOGW(TAG, "drain: WiFi not connected");
        return;
    }
    if (xSemaphoreTake(s_queue_mutex, pdMS_TO_TICKS(200)) != pdTRUE) {
        ESP_LOGW(TAG, "drain: mutex timeout");
        return;
    }

    nvs_handle_t    h;
    uint32_t        best_idx = UINT32_MAX, best_seq = 0;
    nys_queue_rec_t best = {0};

    if (nvs_open(NYS_QUEUE_NS, NVS_READWRITE, &h) != ESP_OK) {
        ESP_LOGE(TAG, "drain: NVS open failed");
        xSemaphoreGive(s_queue_mutex); return;
    }

    for (uint32_t i = 0; i < NYS_QUEUE_SIZE; i++) {
        nys_queue_rec_t cur;
        if (!queue_load_rec_locked(h, i, &cur) || cur.sent) continue;
        if (best_idx == UINT32_MAX || cur.seq < best_seq) {
            best_idx = i; best_seq = cur.seq; best = cur;
        }
    }

    if (best_idx == UINT32_MAX) {
        ESP_LOGI(TAG, "drain: queue empty");
        nvs_close(h); xSemaphoreGive(s_queue_mutex); return;
    }

    ESP_LOGI(TAG, "drain: found seq=%u idx=%u fix=%d coords=%d",
             (unsigned)best.seq, (unsigned)best_idx, best.fix, best.has_coords);

    if (best.queued_at_epoch_s == 0) {
        int64_t now_epoch = time_now_epoch_s();
        if (now_epoch > 0) {
            int64_t age = time_uptime_s() - best.ts_s;
            best.queued_at_epoch_s = now_epoch - (age > 0 ? age : 0);
            char key[8];
            queue_key_for_idx(key, best_idx);
            (void)nvs_set_blob(h, key, &best, sizeof(best));
            (void)nvs_commit(h);
        }
    }

    nvs_close(h);
    xSemaphoreGive(s_queue_mutex);

    ESP_LOGI(TAG, "drain: sending seq=%u to %s", (unsigned)best.seq, s_cfg.api_url);
    esp_err_t send_err = http_send_location_record(&s_cfg, &best);
    if (send_err != ESP_OK) {
        ESP_LOGE(TAG, "drain: HTTP send failed: %s", esp_err_to_name(send_err));
        return;
    }
    ESP_LOGI(TAG, "drain: HTTP send OK for seq=%u", (unsigned)best.seq);

    if (xSemaphoreTake(s_queue_mutex, pdMS_TO_TICKS(200)) != pdTRUE) return;
    if (nvs_open(NYS_QUEUE_NS, NVS_READWRITE, &h) == ESP_OK) {
        nys_queue_rec_t confirm;
        if (queue_load_rec_locked(h, best_idx, &confirm) && confirm.seq == best.seq) {
            queue_delete_rec_locked(h, best_idx);
            queue_save_meta_locked(h);
            (void)nvs_commit(h);
            ESP_LOGI(TAG, "Sent and deleted seq=%u", (unsigned)best.seq);
        }
        nvs_close(h);
    }
    xSemaphoreGive(s_queue_mutex);
}

// ════════════════════════════════════════════════════════════════════════════
// SEND ALL PENDING — used by deep sleep flow (HTTP only)
// ════════════════════════════════════════════════════════════════════════════

void send_all_pending(const nys_cfg_t *cfg)
{
    (void)cfg;

    // Push current GPS sample into the queue
    queue_push_sample();

    // Drain all unsent records via HTTP
    for (int attempt = 0; attempt < NYS_QUEUE_SIZE; attempt++) {
        uint32_t unsent = queue_count_unsent();
        if (unsent == 0) break;

        ESP_LOGI(TAG, "send_all: %u unsent remaining", (unsigned)unsent);
        queue_drain_step();
    }

    uint32_t remaining = queue_count_unsent();
    ESP_LOGI(TAG, "send_all done: %u still unsent", (unsigned)remaining);
}

// ════════════════════════════════════════════════════════════════════════════
// SEND TASK
// ════════════════════════════════════════════════════════════════════════════

static void send_task(void *arg)
{
    (void)arg;
    ESP_LOGI(TAG, "send_task started");
    bool    logged_connected = false;
    int64_t last_hb_us       = 0;

    while (1) {
        bool connected = (xEventGroupGetBits(s_wifi_event_group) & WIFI_CONNECTED_BIT) != 0;

        if (!connected) {
            logged_connected = false;
            ESP_LOGI(TAG, "Not connected — attempting reconnect...");
            connected = wifi_try_reconnect(15000);
            if (!connected) {
                ESP_LOGW(TAG, "Reconnect failed — will retry next cycle");
                vTaskDelay(pdMS_TO_TICKS(10000));
                continue;
            }
        }

        if (!logged_connected) {
            ESP_LOGI(TAG, "WiFi connected — resuming sends");
            logged_connected = true;
        }

        int64_t now_us    = esp_timer_get_time();
        int64_t hb_int_us = (int64_t)(s_cfg.heartbeat_interval_s > 0
                             ? s_cfg.heartbeat_interval_s : 60) * 1000000LL;

        if (last_hb_us == 0 || (now_us - last_hb_us) >= hb_int_us) {
            ESP_LOGI(TAG, "Sending heartbeat...");
            (void)http_send_heartbeat(&s_cfg);
            last_hb_us = now_us;
        }

        uint32_t unsent = queue_count_unsent();
        if (unsent > 0) {
            ESP_LOGI(TAG, "Queue: %u unsent", (unsigned)unsent);
            for (int i = 0; i < 6 && queue_count_unsent() > 0; i++) {
                // LED: fast flash (twice per second) while sending
                gpio_set_level(LED_GPIO, LED_ON);
                queue_drain_step();
                gpio_set_level(LED_GPIO, LED_OFF);
                vTaskDelay(pdMS_TO_TICKS(250));
                gpio_set_level(LED_GPIO, LED_ON);
                vTaskDelay(pdMS_TO_TICKS(250));
                gpio_set_level(LED_GPIO, LED_OFF);
            }
        }

        vTaskDelay(pdMS_TO_TICKS(500));
    }
}

void sender_start_task(void)
{
    xTaskCreate(send_task, "send_task", 6144, NULL, 5, NULL);
}