#include "gps.h"

#include <math.h>
#include <string.h>
#include <stdlib.h>
#include <time.h>

#include "freertos/FreeRTOS.h"
#include "freertos/task.h"
#include "freertos/event_groups.h"

#include "driver/uart.h"
#include "esp_log.h"
#include "esp_timer.h"
#include "nvs.h"
#include "nvs_flash.h"

#include "nys_common.h"
#include "wifi.h"
#include "comms.h"

extern nys_cfg_t s_cfg;
extern EventGroupHandle_t s_wifi_event_group;

static const char *TAG = "GPS";

// Module-level GPS state
static gps_fix_t s_last_fix;
static bool      s_last_fix_valid;
static int64_t   s_last_fix_time_us;
static int64_t   s_last_fix_nvs_save_us;

// GPS time state (from RMC sentences)
static int64_t s_gps_epoch_s;
static bool    s_gps_epoch_valid;

// ────────── Public Accessors ──────────
bool gps_get_last_fix(gps_fix_t *out)
{
    if (!out) return false;
    *out = s_last_fix;
    return s_last_fix_valid;
}

int64_t gps_get_epoch_s(void)
{
    return s_gps_epoch_valid ? s_gps_epoch_s : 0;
}

// ────────── NVS Persistence ──────────
esp_err_t gps_nvs_load_last_fix(void)
{
    nvs_handle_t h;
    esp_err_t err = nvs_open("gps", NVS_READONLY, &h);
    if (err != ESP_OK) return err;

    gps_fix_nvs_v1_t stored;
    size_t len = sizeof(stored);
    err = nvs_get_blob(h, "last_fix", &stored, &len);
    nvs_close(h);

    if (err != ESP_OK || len != sizeof(stored)) return err;

    s_last_fix.lat_deg     = stored.lat_deg;
    s_last_fix.lon_deg     = stored.lon_deg;
    s_last_fix.fix_quality = (int)stored.fix_quality;
    s_last_fix.sats_used   = (int)stored.sats_used;
    s_last_fix.has_fix     = true;
    s_last_fix_valid       = true;
    s_last_fix_time_us     = esp_timer_get_time();
    return ESP_OK;
}

esp_err_t gps_nvs_save_last_fix(const gps_fix_t *fix)
{
    if (!fix || !fix->has_fix) return ESP_ERR_INVALID_ARG;

    nvs_handle_t h;
    esp_err_t err = nvs_open("gps", NVS_READWRITE, &h);
    if (err != ESP_OK) return err;

    gps_fix_nvs_v1_t stored = {
        .lat_deg     = fix->lat_deg,
        .lon_deg     = fix->lon_deg,
        .fix_quality = (int32_t)fix->fix_quality,
        .sats_used   = (int32_t)fix->sats_used,
    };

    err = nvs_set_blob(h, "last_fix", &stored, sizeof(stored));
    if (err == ESP_OK) err = nvs_commit(h);
    nvs_close(h);
    return err;
}

// ────────── Fix-quality Helper ──────────
const char *gps_fixq_to_str(int fixq)
{
    switch (fixq) {
    case 0: return "INVALID";
    case 1: return "GPS";
    case 2: return "DGPS";
    case 4: return "RTK_FIXED";
    case 5: return "RTK_FLOAT";
    case 6: return "DR";
    default: return "OTHER";
    }
}

// ────────── NMEA Helpers ──────────
static bool nmea_checksum_ok(const char *s)
{
    const char *star = strchr(s, '*');
    if (!star || star == s) return false;
    uint8_t calc = 0;
    for (const char *p = s + 1; p < star; p++) calc ^= (uint8_t)(*p);
    if (!star[1] || !star[2]) return false;
    char hex[3] = { star[1], star[2], 0 };
    char *end = NULL;
    return (uint8_t)strtol(hex, &end, 16) == calc && end && *end == 0;
}

static double nmea_degmin_to_deg(const char *dm)
{
    if (!dm || !dm[0]) return NAN;
    double v = atof(dm);
    int deg  = (int)(v / 100.0);
    return (double)deg + (v - (deg * 100.0)) / 60.0;
}

// GGA Parser – Position
static bool nmea_parse_gga(char *line, gps_fix_t *out)
{
    if (!out) return false;
    if (strncmp(line, "$GPGGA,", 7) != 0 && strncmp(line, "$GNGGA,", 7) != 0) return false;
    if (!nmea_checksum_ok(line)) return false;

    char *save = NULL;
    if (!strtok_r(line, ",", &save)) return false;
    (void)strtok_r(NULL, ",", &save);
    const char *lat_s  = strtok_r(NULL, ",", &save);
    const char *lat_h  = strtok_r(NULL, ",", &save);
    const char *lon_s  = strtok_r(NULL, ",", &save);
    const char *lon_h  = strtok_r(NULL, ",", &save);
    const char *fix_s  = strtok_r(NULL, ",", &save);
    const char *sats_s = strtok_r(NULL, ",", &save);

    double lat = nmea_degmin_to_deg(lat_s);
    double lon = nmea_degmin_to_deg(lon_s);
    if (lat_h && (lat_h[0] == 'S' || lat_h[0] == 's')) lat = -lat;
    if (lon_h && (lon_h[0] == 'W' || lon_h[0] == 'w')) lon = -lon;

    int fixq = (fix_s  && fix_s[0])  ? atoi(fix_s)  : 0;
    int sats = (sats_s && sats_s[0]) ? atoi(sats_s) : 0;

    out->lat_deg     = lat;
    out->lon_deg     = lon;
    out->fix_quality = fixq;
    out->sats_used   = sats;
    out->has_fix     = (fixq > 0) && !isnan(lat) && !isnan(lon);
    return true;
}

// RMC Parser – Date + Time
static bool nmea_parse_rmc(char *line, int64_t *epoch_out)
{
    if (!epoch_out) return false;
    if (strncmp(line, "$GPRMC,", 7) != 0 && strncmp(line, "$GNRMC,", 7) != 0) return false;
    if (!nmea_checksum_ok(line)) return false;

    char *save = NULL;
    strtok_r(line, ",", &save); // $GPRMC
    const char *time_s = strtok_r(NULL, ",", &save);
    const char *status = strtok_r(NULL, ",", &save);
    strtok_r(NULL, ",", &save); // lat
    strtok_r(NULL, ",", &save); // N/S
    strtok_r(NULL, ",", &save); // lon
    strtok_r(NULL, ",", &save); // E/W
    strtok_r(NULL, ",", &save); // speed
    strtok_r(NULL, ",", &save); // course
    const char *date_s = strtok_r(NULL, ",", &save);

    if (!status || status[0] != 'A') return false;
    if (!time_s || strlen(time_s) < 6) return false;
    if (!date_s || strlen(date_s) < 6) return false;

    int hh = (time_s[0]-'0')*10 + (time_s[1]-'0');
    int mm = (time_s[2]-'0')*10 + (time_s[3]-'0');
    int ss = (time_s[4]-'0')*10 + (time_s[5]-'0');
    int day = (date_s[0]-'0')*10 + (date_s[1]-'0');
    int mon = (date_s[2]-'0')*10 + (date_s[3]-'0');
    int year = (date_s[4]-'0')*10 + (date_s[5]-'0') + 2000;

    if (hh > 23 || mm > 59 || ss > 59) return false;
    if (day < 1 || day > 31 || mon < 1 || mon > 12) return false;

    struct tm t = {
        .tm_sec = ss,
        .tm_min = mm,
        .tm_hour = hh,
        .tm_mday = day,
        .tm_mon = mon - 1,
        .tm_year = year - 1900,
        .tm_isdst = 0,
    };

    time_t epoch = mktime(&t);
    if (epoch < 0) return false;

    *epoch_out = (int64_t)epoch;
    return true;
}

// ────────── UART Reader Task ──────────
static void gps_uart_task(void *arg)
{
    (void)arg;
    uint8_t *buf = malloc(512);
    if (!buf) { ESP_LOGE(TAG, "No memory for GPS buffer"); vTaskDelete(NULL); return; }

    int64_t no_data_ticks = 0;
    char line[128];
    size_t line_len = 0;
    gps_fix_t fix = {0};

    while (1) {
        int len = uart_read_bytes(GPS_UART, buf, 512, pdMS_TO_TICKS(250));
        if (len <= 0) {
            if (++no_data_ticks >= 4) {
                size_t buffered = 0;
                uart_get_buffered_data_len(GPS_UART, &buffered);
                ESP_LOGW(TAG, "GPS: no data (rx buffered=%u)", (unsigned)buffered);
                no_data_ticks = 0;
            }
            continue;
        }
        no_data_ticks = 0;

        for (int i = 0; i < len; i++) {
            uint8_t c = buf[i];
            if (c == '\r') continue;
            if (c == '\n') {
                if (line_len == 0) continue;
                line[line_len] = 0;

                char work[128];
                strncpy(work, line, sizeof(work)-1);
                work[sizeof(work)-1] = 0;

                if (nmea_parse_gga(work, &fix)) {
                    if (fix.has_fix) {
                        s_last_fix       = fix;
                        s_last_fix_valid = true;
                        s_last_fix_time_us = esp_timer_get_time();

                        if ((s_last_fix_time_us - s_last_fix_nvs_save_us) > 30000000) {
                            if (gps_nvs_save_last_fix(&fix) == ESP_OK)
                                s_last_fix_nvs_save_us = s_last_fix_time_us;
                        }

                        EventBits_t bits = xEventGroupGetBits(s_wifi_event_group);
                        if (bits & WIFI_CONNECTED_BIT) {
                            ESP_LOGI(TAG, "FIX: %.6f,%.6f sats=%d fixq=%s | http://%s/",
                                     fix.lat_deg, fix.lon_deg, fix.sats_used,
                                     gps_fixq_to_str(fix.fix_quality), wifi_get_ip_str());
                        } else {
                            ESP_LOGI(TAG, "FIX: %.6f,%.6f sats=%d fixq=%s",
                                     fix.lat_deg, fix.lon_deg, fix.sats_used,
                                     gps_fixq_to_str(fix.fix_quality));
                        }
                    } else {
                        if (s_last_fix_valid) {
                            int64_t age_s = (esp_timer_get_time() - s_last_fix_time_us)/1000000;
                            ESP_LOGI(TAG, "NO FIX: sats=%d fixq=%s | last fix age=%llds",
                                     fix.sats_used, gps_fixq_to_str(fix.fix_quality),
                                     (long long)age_s);
                        } else {
                            ESP_LOGI(TAG, "NO FIX: sats=%d fixq=%s",
                                     fix.sats_used, gps_fixq_to_str(fix.fix_quality));
                        }
                    }
                }

                strncpy(work, line, sizeof(work)-1);
                work[sizeof(work)-1] = 0;
                int64_t gps_epoch = 0;
                if (nmea_parse_rmc(work, &gps_epoch)) {
                    bool first_fix = !s_gps_epoch_valid;
                    s_gps_epoch_s = gps_epoch;
                    s_gps_epoch_valid = true;
                    time_update_from_gps(gps_epoch);
                    if (first_fix) ESP_LOGI(TAG, "GPS time acquired: epoch=%lld", (long long)gps_epoch);
                }

                line_len = 0;
                continue;
            }
            if (line_len < sizeof(line)-1) line[line_len++] = (char)c;
            else line_len = 0;
        }
    }
}

// ────────── GPS Sampling Task ──────────
static void gps_sample_task(void *arg)
{
    (void)arg;
    int64_t last_sample_us = esp_timer_get_time();

    while (1) {
        uint32_t interval_s = s_cfg.location_interval_s > 0 ? s_cfg.location_interval_s : 60;
        int64_t now_us = esp_timer_get_time();

        if (s_last_fix_valid && (now_us - last_sample_us) >= (int64_t)interval_s * 1000000) {
            queue_push_sample(); // just call it
            ESP_LOGI(TAG, "Location sample queued at %lld us", (long long)now_us);
            last_sample_us = now_us;
        }

        vTaskDelay(pdMS_TO_TICKS(500)); // check twice per second
    }
}

// ────────── Public Init ──────────
void gps_init(void)
{
    xTaskCreate(gps_uart_task,   "gps_uart_task",   4096, NULL, 5, NULL);
    xTaskCreate(gps_sample_task, "gps_sample_task", 4096, NULL, 5, NULL);
}