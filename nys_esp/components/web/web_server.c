#include "web_server.h"
#include "wifi.h" 

#include <string.h>
#include <stdlib.h>

#include "freertos/FreeRTOS.h"
#include "freertos/task.h"

#include "esp_log.h"
#include "esp_http_server.h"
#include "nvs.h"
#include "cJSON.h"

#include "nys_common.h"
#include "comms.h"
#include "gps.h"

extern nys_cfg_t         s_cfg;
extern SemaphoreHandle_t s_queue_mutex;
extern uint32_t          s_queue_next_seq;
extern uint32_t          s_queue_widx;

extern bool     queue_load_rec_locked(nvs_handle_t h, uint32_t idx, nys_queue_rec_t *out);
extern void     queue_delete_rec_locked(nvs_handle_t h, uint32_t idx);
extern esp_err_t cfg_save_settings(uint32_t hb, uint32_t loc,
                                    const char *in1_desc, const char *api_url);

static const char *TAG = "WEB";
static httpd_handle_t s_httpd = NULL;

// ─────────────────────────────────────────────────────────────────────────────
// Utility
// ─────────────────────────────────────────────────────────────────────────────
static void url_decode_inplace(char *s)
{
    char *o = s;
    while (*s) {
        if (*s == '+') { *o++ = ' '; s++; }
        else if (*s == '%' && s[1] && s[2]) {
            char hex[3] = { s[1], s[2], 0 };
            *o++ = (char)strtol(hex, NULL, 16);
            s += 3;
        } else { *o++ = *s++; }
    }
    *o = 0;
}

static void extract_field(const char *buf, const char *key, char *out, size_t out_len)
{
    out[0] = 0;

    char *p = strstr(buf, key);
    if (!p) return;

    p += strlen(key);
    char *end = strchr(p, '&');

    size_t len = end ? (size_t)(end - p) : strlen(p);
    if (len >= out_len) len = out_len - 1;

    memcpy(out, p, len);
    out[len] = 0;

    url_decode_inplace(out);
}

// ─────────────────────────────────────────────────────────────────────────────
// GET /
// ─────────────────────────────────────────────────────────────────────────────
static esp_err_t http_root_get(httpd_req_t *req)
{
    nys_network_t nets[NYS_MAX_NETWORKS];
    int net_count = cfg_load_networks(nets);

    char net_list[512] = "";

    for (int i = 0; i < net_count; i++) {
        if (nets[i].ssid[0] == 0) continue;

        char row[256];
        snprintf(row, sizeof(row),
                 "<li>%s "
                 "<a href='/net_delete?ssid=%s' "
                 "onclick=\"return confirm('Delete %s?')\">&#x2715;</a>"
                 "%s</li>",
                 nets[i].ssid, nets[i].ssid, nets[i].ssid,
                 i == 0 ? " <em>(last connected)</em>" : "");

        strncat(net_list, row, sizeof(net_list) - strlen(net_list) - 1);
    }

    if (net_count == 0) {
        strncat(net_list, "<li><em>No networks saved</em></li>",
                sizeof(net_list) - strlen(net_list) - 1);
    }

    // Gather GPS and queue info
    gps_fix_t fix = {0};
    bool has_fix = gps_get_last_fix(&fix);
    uint32_t unsent = queue_count_unsent();
    int64_t uptime = time_uptime_s();

    char *html = malloc(6144);
    if (!html) {
        httpd_resp_send_err(req, HTTPD_500_INTERNAL_SERVER_ERROR, "No mem");
        return ESP_FAIL;
    }

    snprintf(html, 6144,
        "<!doctype html><html><head><meta charset='utf-8'>"
        "<meta name='viewport' content='width=device-width,initial-scale=1'>"
        "<title>NYS Setup</title>"
        "<style>body{font-family:Arial;padding:16px;max-width:480px}"
        "input{width:100%%;padding:8px;margin-bottom:12px}"
        "button{padding:10px 14px}"
        ".status{background:#f0f0f0;padding:12px;border-radius:8px;margin-bottom:16px}"
        ".ok{color:#2a2}.warn{color:#c50}</style>"
        "</head><body>"

        "<h2>NYS Device Setup</h2>"

        "<div class='status'>"
        "<h3>Device Status</h3>"
        "<p><b>UID:</b> %s</p>"
        "<p><b>Uptime:</b> %lldh %lldm %llds</p>"
        "<p><b>GPS:</b> %s</p>"
        "<p><b>Last Fix:</b> %.6f, %.6f (sats=%d, quality=%d)</p>"
        "<p><b>Queue:</b> <span class='%s'>%u unsent</span></p>"
        "</div>"

        "<h3>Device Settings</h3>"
        "<form method='POST' action='/save'>"
        "<input name='api' value='%s'/>"
        "<input name='hb' value='%u'/>"
        "<input name='loc' value='%u'/>"
        "<input name='in1' value='%s'/>"
        "<button type='submit'>Save</button>"
        "</form>"

        "<h3>Saved Networks (%d/%d)</h3>"
        "<ul>%s</ul>"

        "<form method='POST' action='/net_add'>"
        "<input name='ssid' placeholder='SSID'/>"
        "<input name='pass' placeholder='Password'/>"
        "<button>Add</button>"
        "</form>"

        "</body></html>",

        s_cfg.device_uid,
        (long long)(uptime / 3600),
        (long long)((uptime % 3600) / 60),
        (long long)(uptime % 60),
        has_fix ? "<span class='ok'>Fix valid</span>"
                : "<span class='warn'>No fix</span>",
        has_fix ? fix.lat_deg : 0.0,
        has_fix ? fix.lon_deg : 0.0,
        has_fix ? fix.sats_used : 0,
        has_fix ? fix.fix_quality : 0,
        unsent > 0 ? "warn" : "ok",
        (unsigned)unsent,
        s_cfg.api_url,
        (unsigned)s_cfg.heartbeat_interval_s,
        (unsigned)s_cfg.location_interval_s,
        s_cfg.input1_desc,
        net_count, NYS_MAX_NETWORKS,
        net_list);

    httpd_resp_set_type(req, "text/html");
    httpd_resp_send(req, html, HTTPD_RESP_USE_STRLEN);
    free(html);

    return ESP_OK;
}

// ─────────────────────────────────────────────────────────────────────────────
// POST /save
// ─────────────────────────────────────────────────────────────────────────────
static esp_err_t http_save_post(httpd_req_t *req)
{
    int total = req->content_len;
    if (total <= 0 || total > 512) {
        httpd_resp_send_err(req, HTTPD_400_BAD_REQUEST, "Bad request");
        return ESP_FAIL;
    }

    char *buf = calloc(1, total + 1);
    if (!buf) {
        httpd_resp_send_err(req, HTTPD_500_INTERNAL_SERVER_ERROR, "No mem");
        return ESP_FAIL;
    }

    if (httpd_req_recv(req, buf, total) <= 0) {
        free(buf);
        httpd_resp_send_err(req, HTTPD_500_INTERNAL_SERVER_ERROR, "Recv failed");
        return ESP_FAIL;
    }

    char api_url[128] = {0};
    char in1[32] = {0};
    char hb_str[16] = {0};
    char loc_str[16] = {0};
    uint32_t hb = 0, loc = 0;

    extract_field(buf, "api=", api_url, sizeof(api_url));
    extract_field(buf, "in1=", in1, sizeof(in1));
    extract_field(buf, "hb=", hb_str, sizeof(hb_str));
    extract_field(buf, "loc=", loc_str, sizeof(loc_str));

    hb = atoi(hb_str);
    loc = atoi(loc_str);

    free(buf);

    // Save to NVS
    if (cfg_save_settings(hb, loc, in1, api_url) != ESP_OK) {
        ESP_LOGE(TAG, "Failed to save settings to NVS");
        httpd_resp_send_err(req, HTTPD_500_INTERNAL_SERVER_ERROR, "Save failed");
        return ESP_FAIL;
    }

    // Update in-RAM config
    strncpy(s_cfg.api_url, api_url, sizeof(s_cfg.api_url) - 1);
    s_cfg.api_url[sizeof(s_cfg.api_url) - 1] = 0;

    s_cfg.heartbeat_interval_s = hb;
    s_cfg.location_interval_s = loc;

    strncpy(s_cfg.input1_desc, in1, sizeof(s_cfg.input1_desc) - 1);
    s_cfg.input1_desc[sizeof(s_cfg.input1_desc) - 1] = 0;

    ESP_LOGI(TAG, "Settings saved: api=%s, hb=%u, loc=%u, in1=%s",
             s_cfg.api_url, s_cfg.heartbeat_interval_s,
             s_cfg.location_interval_s, s_cfg.input1_desc);

    httpd_resp_set_status(req, "303 See Other");
    httpd_resp_set_hdr(req, "Location", "/");
    return httpd_resp_send(req, NULL, 0);
}

// ─────────────────────────────────────────────────────────────────────────────
// POST /net_add
// ─────────────────────────────────────────────────────────────────────────────
static esp_err_t http_net_add_post(httpd_req_t *req)
{
    int total = req->content_len;

    if (total <= 0 || total > 256) {
        httpd_resp_send_err(req, HTTPD_400_BAD_REQUEST, "Bad request");
        return ESP_FAIL;
    }

    char *buf = calloc(1, total + 1);
    if (!buf) {
        httpd_resp_send_err(req, HTTPD_500_INTERNAL_SERVER_ERROR, "No mem");
        return ESP_FAIL;
    }

    if (httpd_req_recv(req, buf, total) <= 0) {
        free(buf);
        httpd_resp_send_err(req, HTTPD_500_INTERNAL_SERVER_ERROR, "Recv failed");
        return ESP_FAIL;
    }

    char ssid[33] = {0};
    char pass[65] = {0};

    extract_field(buf, "ssid=", ssid, sizeof(ssid));
    extract_field(buf, "pass=", pass, sizeof(pass));

    free(buf);

    if (ssid[0] == 0) {
        httpd_resp_send_err(req, HTTPD_400_BAD_REQUEST, "SSID required");
        return ESP_FAIL;
    }

    // Save
    if (cfg_save_network(ssid, pass) != ESP_OK) {
        httpd_resp_send_err(req, HTTPD_500_INTERNAL_SERVER_ERROR, "Save failed");
        return ESP_FAIL;
    }

    ESP_LOGI(TAG, "Network added: %s", ssid);

    wifi_connect_to_network(ssid, pass);

    httpd_resp_set_status(req, "303 See Other");
    httpd_resp_set_hdr(req, "Location", "/");
    return httpd_resp_send(req, NULL, 0);
}

// ─────────────────────────────────────────────────────────────────────────────
// START SERVER
// ─────────────────────────────────────────────────────────────────────────────
void web_start_server(void)
{
    if (s_httpd) return;

    httpd_config_t config = HTTPD_DEFAULT_CONFIG();
    config.stack_size = 8192;

    if (httpd_start(&s_httpd, &config) != ESP_OK) {
        s_httpd = NULL;
        return;
    }

    httpd_uri_t root = {
        .uri = "/",
        .method = HTTP_GET,
        .handler = http_root_get
    };

    httpd_uri_t add = {
        .uri = "/net_add",
        .method = HTTP_POST,
        .handler = http_net_add_post
    };

    httpd_uri_t save = {
        .uri = "/save",
        .method = HTTP_POST,
        .handler = http_save_post
    };

    httpd_register_uri_handler(s_httpd, &root);
    httpd_register_uri_handler(s_httpd, &add);
    httpd_register_uri_handler(s_httpd, &save);

    ESP_LOGI(TAG, "Web server started");
}

void web_stop_server(void)
{
    if (!s_httpd) return;

    httpd_stop(s_httpd);
    s_httpd = NULL;
}