#include "gps.h"

#include <math.h>
#include <string.h>
#include <stdlib.h>
#include <time.h>

#include "freertos/FreeRTOS.h"
#include "freertos/task.h"
#include "freertos/event_groups.h"

#include "driver/uart.h"
#include "driver/gpio.h"
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

// GSV Parser – satellites in view (debug only)
// Returns the "satellites in view" count from the message, or -1 if not GSV.
// GSV format: $XXGSV,<#msgs>,<msg#>,<sats_in_view>,<sat info...>*CS
// Also captures the highest SNR seen across the visible birds — that's the
// single most useful "is the antenna working" signal. SNR>30 indoors is good,
// 20–30 is marginal, <20 will likely never lock.
static int nmea_parse_gsv(char *line, int *best_snr_out)
{
    if (!line) return -1;
    // Match GPGSV / GLGSV / GAGSV / BDGSV / GBGSV / GNGSV (constellation prefix varies)
    if (strlen(line) < 7) return -1;
    if (line[0] != '$' || line[3] != 'G' || line[4] != 'S' || line[5] != 'V' || line[6] != ',') return -1;
    if (!nmea_checksum_ok(line)) return -1;

    // Make a working copy because strtok_r mutates.
    char work[128];
    strncpy(work, line, sizeof(work) - 1);
    work[sizeof(work) - 1] = 0;

    char *save = NULL;
    strtok_r(work, ",", &save);                 // $XXGSV
    (void)strtok_r(NULL, ",", &save);           // total msgs
    (void)strtok_r(NULL, ",", &save);           // this msg #
    const char *inview_s = strtok_r(NULL, ",", &save);
    int inview = (inview_s && inview_s[0]) ? atoi(inview_s) : 0;

    // Each sat block = 4 fields: id, elevation, azimuth, snr.
    // Walk them and track the max snr we've seen.
    if (best_snr_out) {
        int best = *best_snr_out;
        for (int i = 0; i < 4; i++) {
            (void)strtok_r(NULL, ",", &save);   // id
            (void)strtok_r(NULL, ",", &save);   // elev
            (void)strtok_r(NULL, ",", &save);   // azim
            const char *snr_s = strtok_r(NULL, ",", &save);
            if (!snr_s || !snr_s[0]) continue;
            // SNR field may have a *checksum tail on the last sat — strtol ignores it.
            int snr = atoi(snr_s);
            if (snr > best) best = snr;
        }
        *best_snr_out = best;
    }
    return inview;
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

// ────────── UBX Helpers (ZOE-M8Q) ──────────

// Compute UBX Fletcher checksum over class, id, length, and payload
static void ubx_checksum(const uint8_t *data, size_t len, uint8_t *ck_a, uint8_t *ck_b)
{
    uint8_t a = 0, b = 0;
    for (size_t i = 0; i < len; i++) {
        a += data[i];
        b += a;
    }
    *ck_a = a;
    *ck_b = b;
}

// Send a raw UBX command on GPS_UART
static esp_err_t gps_send_ubx(uint8_t cls, uint8_t id,
                               const uint8_t *payload, uint16_t payload_len)
{
    // Header: sync(2) + class(1) + id(1) + length(2) + payload + checksum(2)
    size_t frame_len = 2 + 1 + 1 + 2 + payload_len + 2;
    uint8_t *frame = malloc(frame_len);
    if (!frame) return ESP_ERR_NO_MEM;

    frame[0] = 0xB5;  // sync char 1
    frame[1] = 0x62;  // sync char 2
    frame[2] = cls;
    frame[3] = id;
    frame[4] = (uint8_t)(payload_len & 0xFF);         // length LSB
    frame[5] = (uint8_t)((payload_len >> 8) & 0xFF);   // length MSB

    if (payload && payload_len > 0) {
        memcpy(&frame[6], payload, payload_len);
    }

    uint8_t ck_a, ck_b;
    ubx_checksum(&frame[2], 4 + payload_len, &ck_a, &ck_b);
    frame[6 + payload_len]     = ck_a;
    frame[6 + payload_len + 1] = ck_b;

    int written = uart_write_bytes(GPS_UART, frame, frame_len);
    free(frame);

    if (written < 0 || (size_t)written != frame_len) {
        ESP_LOGE(TAG, "UBX write failed");
        return ESP_FAIL;
    }

    ESP_LOGI(TAG, "UBX sent: class=0x%02X id=0x%02X len=%u", cls, id, payload_len);
    return ESP_OK;
}

// Put ZOE-M8Q into backup (sleep) mode via UBX-RXM-PMREQ
// Duration=0 means indefinite — wakes on UART activity or EXTINT
static esp_err_t gps_enter_backup(void)
{
    uint8_t payload[8] = {
        0x00, 0x00, 0x00, 0x00,   // duration = 0 (indefinite)
        0x02, 0x00, 0x00, 0x00,   // flags = 0x02 (backup)
    };

    esp_err_t err = gps_send_ubx(0x02, 0x41, payload, sizeof(payload));
    if (err == ESP_OK) {
        ESP_LOGI(TAG, "GPS entering backup (sleep) mode");
    }
    return err;
}

// Wake GPS by sending bytes on UART — ZOE-M8Q wakes on any UART activity
static void gps_wake(void)
{
    uint8_t wake_bytes[4] = { 0xFF, 0xFF, 0xFF, 0xFF };
    uart_write_bytes(GPS_UART, wake_bytes, sizeof(wake_bytes));
    ESP_LOGI(TAG, "GPS wake signal sent");
    uart_flush_input(GPS_UART);
    vTaskDelay(pdMS_TO_TICKS(1000));
}

// ────────── NEW: Public power on/off for deep sleep mode ──────────
void gps_power_on(void)  { gps_wake(); }
void gps_power_off(void) { gps_enter_backup(); vTaskDelay(pdMS_TO_TICKS(100)); }

// NEW: Try to obtain GPS fix within timeout_s. Returns true if fix obtained.
// Reads NMEA in a blocking loop — designed for linear deep sleep flow.
bool gps_try_get_fix(int timeout_s)
{
    uint8_t *buf = malloc(512);
    if (!buf) { ESP_LOGE(TAG, "No memory for GPS buffer"); return false; }

    bool     got_fix    = false;
    int64_t  start_us   = esp_timer_get_time();
    int64_t  timeout_us = (int64_t)timeout_s * 1000000LL;
    char     line[128];
    size_t   line_len   = 0;
    gps_fix_t fix       = {0};

    ESP_LOGI(TAG, "GPS: waiting for fix (timeout %ds)...", timeout_s);

    // Same debug counters as the duty-cycle task.
    uint32_t total_bytes  = 0;
    uint32_t total_lines  = 0;
    uint32_t bad_checksum = 0;
    uint32_t gga_seen     = 0;
    uint32_t gga_with_fix = 0;
    int      last_fixq    = -1;
    int      last_sats    = -1;
    int      sats_in_view = 0;
    int      best_snr     = 0;
    int64_t  last_summary_us = esp_timer_get_time();

    while (!got_fix) {
        int64_t now_us     = esp_timer_get_time();
        int64_t elapsed_us = now_us - start_us;
        if (elapsed_us >= timeout_us) {
            ESP_LOGW(TAG, "GPS fix timeout after %ds", timeout_s);
            break;
        }

        // LED: slow flash while searching
        static int64_t last_toggle = 0;
        static bool led_on = false;
        if ((now_us - last_toggle) >= 1000000LL) {
            led_on = !led_on;
            gpio_set_level(LED_GPIO, led_on ? LED_ON : LED_OFF);
            last_toggle = now_us;
        }

        // Periodic summary every 5s.
        if ((now_us - last_summary_us) >= 5000000LL) {
            ESP_LOGI(TAG,
                     "[t=%llds] bytes=%u lines=%u badcs=%u gga=%u(fix=%u) lastfixq=%d lastsats=%d view=%d snr_max=%d",
                     (long long)(elapsed_us / 1000000LL),
                     (unsigned)total_bytes, (unsigned)total_lines,
                     (unsigned)bad_checksum, (unsigned)gga_seen, (unsigned)gga_with_fix,
                     last_fixq, last_sats, sats_in_view, best_snr);
            last_summary_us = now_us;
        }

        int len = uart_read_bytes(GPS_UART, buf, 512, pdMS_TO_TICKS(250));
        if (len <= 0) continue;
        total_bytes += (uint32_t)len;

        for (int i = 0; i < len; i++) {
            uint8_t c = buf[i];
            if (c == '\r') continue;
            if (c == '\n') {
                if (line_len == 0) continue;
                line[line_len] = 0;
                total_lines++;
                ESP_LOGD(TAG, "NMEA: %.100s", line);

                if (line[0] == '$' && !nmea_checksum_ok(line)) bad_checksum++;

                {
                    char work[128];
                    strncpy(work, line, sizeof(work) - 1);
                    work[sizeof(work) - 1] = 0;
                    int inview = nmea_parse_gsv(work, &best_snr);
                    if (inview > sats_in_view) sats_in_view = inview;
                }

                char work[128];
                strncpy(work, line, sizeof(work) - 1);
                work[sizeof(work) - 1] = 0;

                bool was_gga = (strncmp(work, "$GPGGA,", 7) == 0 || strncmp(work, "$GNGGA,", 7) == 0);
                if (nmea_parse_gga(work, &fix)) {
                    gga_seen++;
                    last_fixq = fix.fix_quality;
                    last_sats = fix.sats_used;
                    if (fix.has_fix) {
                        gga_with_fix++;
                        s_last_fix         = fix;
                        s_last_fix_valid   = true;
                        s_last_fix_time_us = esp_timer_get_time();
                        got_fix            = true;

                        gps_nvs_save_last_fix(&fix);
                        s_last_fix_nvs_save_us = s_last_fix_time_us;

                        ESP_LOGI(TAG, "FIX: %.6f,%.6f sats=%d fixq=%s",
                                 fix.lat_deg, fix.lon_deg, fix.sats_used,
                                 gps_fixq_to_str(fix.fix_quality));
                    } else {
                        ESP_LOGI(TAG, "GGA no-fix: fixq=%d sats=%d",
                                 fix.fix_quality, fix.sats_used);
                    }
                } else if (was_gga) {
                    ESP_LOGW(TAG, "GGA parse failed (bad checksum?)");
                }

                // Extract time from RMC
                strncpy(work, line, sizeof(work) - 1);
                work[sizeof(work) - 1] = 0;
                int64_t gps_epoch = 0;
                if (nmea_parse_rmc(work, &gps_epoch)) {
                    bool first = !s_gps_epoch_valid;
                    s_gps_epoch_s     = gps_epoch;
                    s_gps_epoch_valid = true;
                    time_update_from_gps(gps_epoch);
                    if (first) ESP_LOGI(TAG, "GPS time acquired: epoch=%lld",
                                        (long long)gps_epoch);
                }

                line_len = 0;
                continue;
            }
            if (line_len < sizeof(line) - 1) line[line_len++] = (char)c;
            else line_len = 0;
        }
    }

    ESP_LOGI(TAG,
             "GPS try done: got_fix=%d bytes=%u lines=%u badcs=%u gga=%u(fix=%u) view=%d snr_max=%d",
             got_fix, (unsigned)total_bytes, (unsigned)total_lines,
             (unsigned)bad_checksum, (unsigned)gga_seen, (unsigned)gga_with_fix,
             sats_in_view, best_snr);

    gpio_set_level(LED_GPIO, LED_OFF);
    free(buf);
    return got_fix;
}

// ────────── GPS Duty-Cycle Task ──────────
// Replaces the old always-on gps_uart_task + gps_sample_task.
// Cycle: wake GPS → read NMEA until fix or 5min timeout → queue sample → sleep GPS
static void gps_duty_cycle_task(void *arg)
{
    (void)arg;
    uint8_t *buf = malloc(512);
    if (!buf) { ESP_LOGE(TAG, "No memory for GPS buffer"); vTaskDelete(NULL); return; }

    while (1) {
        // ── Phase 1: Wake GPS ────────────────────────────────────────────────
        gps_wake();

        // ── Phase 2: Read NMEA until fix or timeout ──────────────────────────
        bool     got_fix    = false;
        int64_t  start_us   = esp_timer_get_time();
        int64_t  timeout_us = (int64_t)GPS_FIX_TIMEOUT_S * 1000000LL;
        char     line[128];
        size_t   line_len   = 0;
        gps_fix_t fix       = {0};

        ESP_LOGI(TAG, "GPS awake — waiting for fix (timeout %ds)...", GPS_FIX_TIMEOUT_S);

        int64_t last_led_toggle_us = 0;
        bool    led_state          = false;

        // ── Debug counters (reset each acquisition cycle) ─────────────────
        // total_bytes:    raw bytes received over UART. 0 = wiring/UART issue.
        // total_lines:    NMEA lines parsed. 0 with bytes>0 = baud/garbage.
        // bad_checksum:   lines that failed checksum. High = noise / wrong baud.
        // gga_seen:       how many GGA sentences arrived (any quality).
        // gga_with_fix:   how many had fix_quality>0 (i.e. we COULD have locked).
        // last_fixq/sats: most recent GGA's reported fix quality + sats.
        // sats_in_view:   max "satellites in view" from any GSV — antenna sees N.
        // best_snr:       best SNR across all GSV — antenna signal strength.
        uint32_t total_bytes  = 0;
        uint32_t total_lines  = 0;
        uint32_t bad_checksum = 0;
        uint32_t gga_seen     = 0;
        uint32_t gga_with_fix = 0;
        int      last_fixq    = -1;
        int      last_sats    = -1;
        int      sats_in_view = 0;
        int      best_snr     = 0;
        int64_t  last_summary_us = esp_timer_get_time();

        while (!got_fix) {
            int64_t now_us     = esp_timer_get_time();
            int64_t elapsed_us = now_us - start_us;
            if (elapsed_us >= timeout_us) {
                ESP_LOGW(TAG, "GPS fix timeout after %ds", GPS_FIX_TIMEOUT_S);
                break;
            }

            // LED: flash once every 2 seconds (1s on, 1s off) while searching
            if ((now_us - last_led_toggle_us) >= 1000000LL) {
                led_state = !led_state;
                gpio_set_level(LED_GPIO, led_state ? LED_ON : LED_OFF);
                last_led_toggle_us = now_us;
            }

            // Periodic summary every 5s — tells you at a glance whether
            // the GPS is alive, whether the antenna sees birds, and why
            // we don't yet have a fix.
            if ((now_us - last_summary_us) >= 5000000LL) {
                ESP_LOGI(TAG,
                         "[t=%llds] bytes=%u lines=%u badcs=%u gga=%u(fix=%u) lastfixq=%d lastsats=%d view=%d snr_max=%d",
                         (long long)(elapsed_us / 1000000LL),
                         (unsigned)total_bytes, (unsigned)total_lines,
                         (unsigned)bad_checksum, (unsigned)gga_seen, (unsigned)gga_with_fix,
                         last_fixq, last_sats, sats_in_view, best_snr);
                last_summary_us = now_us;
            }

            int len = uart_read_bytes(GPS_UART, buf, 512, pdMS_TO_TICKS(250));
            if (len <= 0) continue;
            total_bytes += (uint32_t)len;

            for (int i = 0; i < len; i++) {
                uint8_t c = buf[i];
                if (c == '\r') continue;
                if (c == '\n') {
                    if (line_len == 0) continue;
                    line[line_len] = 0;
                    total_lines++;

                    // Print every NMEA sentence as DEBUG so you can see what
                    // the receiver is actually outputting. Truncated to 100
                    // chars so the log stays usable.
                    ESP_LOGD(TAG, "NMEA: %.100s", line);

                    // Track checksum failures separately — high counts mean
                    // wrong UART baud or electrical noise on the line.
                    if (line[0] == '$' && !nmea_checksum_ok(line)) {
                        bad_checksum++;
                    }

                    // GSV: how many sats the antenna SEES (separate from used-in-fix).
                    {
                        char work[128];
                        strncpy(work, line, sizeof(work) - 1);
                        work[sizeof(work) - 1] = 0;
                        int inview = nmea_parse_gsv(work, &best_snr);
                        if (inview > sats_in_view) sats_in_view = inview;
                    }

                    char work[128];
                    strncpy(work, line, sizeof(work) - 1);
                    work[sizeof(work) - 1] = 0;

                    bool was_gga = (strncmp(work, "$GPGGA,", 7) == 0 || strncmp(work, "$GNGGA,", 7) == 0);
                    if (nmea_parse_gga(work, &fix)) {
                        gga_seen++;
                        last_fixq = fix.fix_quality;
                        last_sats = fix.sats_used;
                        if (fix.has_fix) {
                            gga_with_fix++;
                            s_last_fix         = fix;
                            s_last_fix_valid   = true;
                            s_last_fix_time_us = esp_timer_get_time();
                            got_fix            = true;

                            gps_nvs_save_last_fix(&fix);
                            s_last_fix_nvs_save_us = s_last_fix_time_us;

                            ESP_LOGI(TAG, "FIX: %.6f,%.6f sats=%d fixq=%s",
                                     fix.lat_deg, fix.lon_deg, fix.sats_used,
                                     gps_fixq_to_str(fix.fix_quality));
                        } else {
                            // No-fix GGA still tells us a lot: the receiver
                            // is alive and tracking, just can't lock yet.
                            ESP_LOGI(TAG, "GGA no-fix: fixq=%d sats=%d",
                                     fix.fix_quality, fix.sats_used);
                        }
                    } else if (was_gga) {
                        // GGA sentence arrived but failed checksum or parse.
                        ESP_LOGW(TAG, "GGA parse failed (bad checksum?)");
                    }

                    // Also try to extract time from RMC
                    strncpy(work, line, sizeof(work) - 1);
                    work[sizeof(work) - 1] = 0;
                    int64_t gps_epoch = 0;
                    if (nmea_parse_rmc(work, &gps_epoch)) {
                        bool first = !s_gps_epoch_valid;
                        s_gps_epoch_s     = gps_epoch;
                        s_gps_epoch_valid = true;
                        time_update_from_gps(gps_epoch);
                        if (first) ESP_LOGI(TAG, "GPS time acquired: epoch=%lld",
                                            (long long)gps_epoch);
                    }

                    line_len = 0;
                    continue;
                }
                if (line_len < sizeof(line) - 1) line[line_len++] = (char)c;
                else line_len = 0;
            }
        }

        // Final summary — useful even on timeout, especially on timeout.
        ESP_LOGI(TAG,
                 "GPS cycle done: got_fix=%d bytes=%u lines=%u badcs=%u gga=%u(fix=%u) view=%d snr_max=%d",
                 got_fix, (unsigned)total_bytes, (unsigned)total_lines,
                 (unsigned)bad_checksum, (unsigned)gga_seen, (unsigned)gga_with_fix,
                 sats_in_view, best_snr);

        // LED off after search phase
        gpio_set_level(LED_GPIO, LED_OFF);

        // ── Phase 3: LED feedback ────────────────────────────────────────────
        if (got_fix) {
            ESP_LOGI(TAG, "Queuing fresh GPS fix");
            // 3 rapid blinks = fix found
            for (int b = 0; b < 3; b++) {
                gpio_set_level(LED_GPIO, LED_ON);
                vTaskDelay(pdMS_TO_TICKS(100));
                gpio_set_level(LED_GPIO, LED_OFF);
                vTaskDelay(pdMS_TO_TICKS(100));
            }
        } else {
            if (s_last_fix_valid) {
                ESP_LOGW(TAG, "No fix — queuing last known location");
            } else {
                ESP_LOGW(TAG, "No fix and no last known — queuing empty sample");
            }
            // 1 long blink = no fix
            gpio_set_level(LED_GPIO, LED_ON);
            vTaskDelay(pdMS_TO_TICKS(1000));
            gpio_set_level(LED_GPIO, LED_OFF);
        }

        // ── Phase 4: Queue and immediately try to send ───────────────────────
        queue_push_sample();
        for (int i = 0; i < 3 && queue_count_unsent() > 0; i++) {
            gpio_set_level(LED_GPIO, LED_ON);
            queue_drain_step();
            gpio_set_level(LED_GPIO, LED_OFF);
            vTaskDelay(pdMS_TO_TICKS(250));
        }

        // ── Phase 5: Put GPS to sleep ────────────────────────────────────────
        gps_enter_backup();
        vTaskDelay(pdMS_TO_TICKS(100));  // let the command flush

        // ── Phase 6: Sleep until next cycle ──────────────────────────────────
        uint32_t interval_s = s_cfg.location_interval_s > 0
                              ? s_cfg.location_interval_s : 60;
        ESP_LOGI(TAG, "GPS sleeping for %lus", (unsigned long)interval_s);
        vTaskDelay(pdMS_TO_TICKS(interval_s * 1000));
    }
}

// ────────── Public Init ──────────
void gps_init(void)
{
    xTaskCreate(gps_duty_cycle_task, "gps_duty_cycle", 5120, NULL, 5, NULL);
}
