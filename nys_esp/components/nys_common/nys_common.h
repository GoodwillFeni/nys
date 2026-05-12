#pragma once

#include <stdint.h>
#include <stdbool.h>
#include "driver/gpio.h"
#include "driver/uart.h"

// ─── Hardware pins ────────────────────────────────────────────────────────────
#define GPS_UART        1       // UART_NUM_1
#define GPS_BAUD_RATE   9600
#define GPS_RX_GPIO     4
#define GPS_TX_GPIO     5
#define GPS_SWAP_RX_TX  0

#define TEST_INPUT_GPIO  1
#define TEST_OUTPUT_GPIO 2

// Built-in LED on ESP32-C3 Super Mini V2 (active LOW)
#define LED_GPIO         8
#define LED_ON           0
#define LED_OFF          1

// ─── GSM modem (SIM800L) pins — mirror gsm_test.c bring-up wiring ────────────
#define GSM_UART        0       // UART_NUM_0
#define GSM_RX_GPIO     10
#define GSM_TX_GPIO     7
#define GSM_BAUD_RATE   115200
#define GSM_RX_BUF      1024

// ─── Application constants ───────────────────────────────────────────────────
// Production endpoint. BLE-configured api_url overrides at runtime.
#define NYS_API_URL             "http://www.nkunziyenungu.co.za/api/device/message"
#define NYS_FIRMWARE_VERSION    "1.0.0"
#define NYS_WIFI_AP_SSID_PREFIX "NYS_"
#define NYS_WIFI_AP_PASS        "Goodwill@123"

#define WIFI_CONNECTED_BIT  BIT0

#define NYS_QUEUE_NS    "q"
#define NYS_QUEUE_SIZE  100
#define NYS_QUEUE_MAGIC 0x4E595331u

#define NYS_TIME_NS              "time"
#define NYS_TIME_VALID_EPOCH_MIN  1700000000

#define NYS_AP_WINDOW_MS         60000
#define NYS_BLE_WAKE_WINDOW_MS   60000   // initial post-wake window so a phone can discover + connect
#define NYS_BLE_MAX_HOLD_MS      300000  // hard cap: never hold awake more than 5 min (prevents stuck connections from draining battery)
#define INPUT_DEBOUNCE_MS        2000
#define GPS_FIX_TIMEOUT_S    300     // 5 minutes — used when NO previous fix
#define GPS_FIX_TIMEOUT_SHORT_S 60  // 1 minute — used when previous fix exists

// ─── Deep sleep ──────────────────────────────────────────────────────────────
#define NYS_DEEP_SLEEP_DEFAULT_S  60  // default sleep interval if not configured

// ─── Saved networks ───────────────────────────────────────────────────────────
#define NYS_MAX_NETWORKS  3
#define NYS_NETWORKS_NS   "nets"

typedef struct {
    char ssid[33];
    char password[65];
} nys_network_t;

// ─── GPS types ───────────────────────────────────────────────────────────────
typedef struct {
    double lat_deg;
    double lon_deg;
    int    fix_quality;
    int    sats_used;
    bool   has_fix;
} gps_fix_t;

typedef struct {
    double   lat_deg;
    double   lon_deg;
    int32_t  fix_quality;
    int32_t  sats_used;
} gps_fix_nvs_v1_t;

// ─── RTC memory — survives deep sleep ────────────────────────────────────────
typedef struct {
    float    lat;
    float    lng;
    bool     has_fix;
    uint32_t boot_count;
    int64_t  last_heartbeat_epoch;  // epoch of last heartbeat sent
    uint8_t  last_input1_level;     // last known GPIO input state
    uint8_t  last_input2_level;     // last known GPIO output state
} nys_rtc_data_t;

// ─── Transport mode ──────────────────────────────────────────────────────────
// BLE-configurable at runtime (characteristic ...0a). No compile-time lock.
typedef enum {
    NYS_TX_WIFI_ONLY      = 0,
    NYS_TX_GSM_ONLY       = 1,
    NYS_TX_WIFI_THEN_GSM  = 2,   // default at first boot — try WiFi, fall back to GSM
} nys_transport_mode_t;

// ─── Config type ─────────────────────────────────────────────────────────────
typedef struct {
    char     ssid[33];           // last connected SSID (index 0 in network list)
    char     password[65];
    bool     has_wifi;
    char     api_url[128];
    char     device_uid[32];
    char     device_key[128];
    uint32_t heartbeat_interval_s;
    uint32_t location_interval_s;
    char     input1_desc[64];
    bool     deep_sleep_enabled; // true = deep sleep mode, false = always-on

    // ── GSM / transport (Section B of pre-deploy plan) ───────────────────
    uint8_t  transport_mode;            // nys_transport_mode_t
    char     apn[64];                   // GPRS APN, e.g. "internet"
    char     apn_user[32];              // GPRS user (usually empty)
    char     apn_pass[32];              // GPRS password (usually empty)
    char     ussd_balance[16];          // e.g. "*131#"
    uint32_t gsm_idle_sleep_s;          // sleep modem after this many seconds idle
    uint32_t gsm_gprs_idle_detach_s;    // detach GPRS after this many seconds idle
    uint32_t balance_check_interval_s;  // re-query USSD at most this often (default 86400 = 24h)

    // Runtime balance cache — sent with every heartbeat when transport involves GSM
    char     last_balance[32];          // last fetched balance string, e.g. "R12.34"
    int64_t  last_balance_ts;           // epoch when last_balance was set
} nys_cfg_t;

// ─── Queue record ─────────────────────────────────────────────────────────────
typedef struct {
    uint32_t magic;
    uint32_t seq;
    int64_t  ts_s;
    int64_t  queued_at_epoch_s;
    int32_t  lat_e6;
    int32_t  lon_e6;
    int16_t  fix_quality;
    int16_t  sats_used;
    uint8_t  has_coords;
    uint8_t  fix;
    uint8_t  last_known;
    uint8_t  sent;
} nys_queue_rec_t;