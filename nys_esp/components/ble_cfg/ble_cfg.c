#include "ble_cfg.h"

#include <string.h>
#include <stdlib.h>

#include "esp_log.h"
#include "esp_err.h"
#include "nvs_flash.h"

#include "nimble/nimble_port.h"
#include "nimble/nimble_port_freertos.h"
#include "host/ble_hs.h"
#include "host/ble_uuid.h"
#include "host/util/util.h"
#include "services/gap/ble_svc_gap.h"
#include "services/gatt/ble_svc_gatt.h"

#include "../comms/comms.h"

static const char *TAG = "BLE_CFG";

/*
 * Custom 128-bit UUIDs for NYS config service.
 * Base:   a7e80000-4e4e-5953-0000-000000000000  ("NYS" in ascii = 4e 59 53)
 * Service: a7e80000-4e4e-5953-0000-000000000001
 * Chars:   ...02 (device_uid, R)  ...03 (ssid, W)  ...04 (password, W)
 *          ...05 (api_url, RW)    ...06 (hb_interval, RW)
 *          ...07 (loc_interval, RW)  ...08 (input1_desc, RW)
 *          ...09 (commit, W)
 *          Section B additions:
 *          ...0a (transport_mode, RW)  ...0b (apn, RW)
 *          ...0c (apn_creds "user:pass", RW)
 *          ...0d (ussd_balance, RW)    ...0e (gsm_idle_sleep_s, RW)
 *          ...0f (gsm_gprs_idle_detach_s, RW)
 *          ...10 (balance_check_interval_s, RW)
 */
#define BLE_UUID128_INIT_NYS(last)                                            \
    BLE_UUID128_INIT(0x00,0x00,0x00,0x00, 0x00,0x00, 0x00,0x00,               \
                     0x53,0x59,0x4e, 0x00,0x00, 0xe8,0xa7, (last))

static const ble_uuid128_t SVC_UUID         = BLE_UUID128_INIT_NYS(0x01);
static const ble_uuid128_t CHR_UID_UUID     = BLE_UUID128_INIT_NYS(0x02);
static const ble_uuid128_t CHR_SSID_UUID    = BLE_UUID128_INIT_NYS(0x03);
static const ble_uuid128_t CHR_PASS_UUID    = BLE_UUID128_INIT_NYS(0x04);
static const ble_uuid128_t CHR_URL_UUID     = BLE_UUID128_INIT_NYS(0x05);
static const ble_uuid128_t CHR_HB_UUID      = BLE_UUID128_INIT_NYS(0x06);
static const ble_uuid128_t CHR_LOC_UUID     = BLE_UUID128_INIT_NYS(0x07);
static const ble_uuid128_t CHR_IN1_UUID     = BLE_UUID128_INIT_NYS(0x08);
static const ble_uuid128_t CHR_CMT_UUID     = BLE_UUID128_INIT_NYS(0x09);
// Section B additions
static const ble_uuid128_t CHR_TXMODE_UUID  = BLE_UUID128_INIT_NYS(0x0a);
static const ble_uuid128_t CHR_APN_UUID     = BLE_UUID128_INIT_NYS(0x0b);
static const ble_uuid128_t CHR_APNCR_UUID   = BLE_UUID128_INIT_NYS(0x0c);
static const ble_uuid128_t CHR_USSD_UUID    = BLE_UUID128_INIT_NYS(0x0d);
static const ble_uuid128_t CHR_GSMSLP_UUID  = BLE_UUID128_INIT_NYS(0x0e);
static const ble_uuid128_t CHR_GPRSIDL_UUID = BLE_UUID128_INIT_NYS(0x0f);
static const ble_uuid128_t CHR_BALIVL_UUID  = BLE_UUID128_INIT_NYS(0x10);

/* ── staged config (buffered until commit) ───────────────────────────────── */
typedef struct {
    char     ssid[33];
    char     password[65];
    char     api_url[128];
    uint32_t hb_s;
    uint32_t loc_s;
    char     in1_desc[64];

    // Section B additions
    uint8_t  tx_mode;
    char     apn[64];
    char     apn_user[32];
    char     apn_pass[32];
    char     ussd[16];
    uint32_t gsm_idle_s;
    uint32_t gprs_idle_s;
    uint32_t bal_ivl_s;

    bool     ssid_set, pass_set, url_set, hb_set, loc_set, in1_set;
    bool     txmode_set, apn_set, apncr_set, ussd_set, gsmslp_set, gprsidl_set, balivl_set;
} staged_t;

static staged_t s_staged;
static nys_cfg_t s_current;
static uint8_t s_own_addr_type;
static volatile bool s_peer_connected = false;

bool ble_cfg_peer_connected(void) { return s_peer_connected; }

/* ── helpers ─────────────────────────────────────────────────────────────── */
static int copy_str_from_ctxt(struct ble_gatt_access_ctxt *ctxt, char *out, size_t out_sz)
{
    uint16_t len = OS_MBUF_PKTLEN(ctxt->om);
    if (len == 0 || len >= out_sz) return BLE_ATT_ERR_INVALID_ATTR_VALUE_LEN;
    int rc = ble_hs_mbuf_to_flat(ctxt->om, out, out_sz - 1, &len);
    if (rc != 0) return BLE_ATT_ERR_UNLIKELY;
    out[len] = '\0';
    return 0;
}

static int copy_u32_from_ctxt(struct ble_gatt_access_ctxt *ctxt, uint32_t *out)
{
    char buf[16];
    int rc = copy_str_from_ctxt(ctxt, buf, sizeof(buf));
    if (rc != 0) return rc;
    char *end = NULL;
    unsigned long v = strtoul(buf, &end, 10);
    if (end == buf) return BLE_ATT_ERR_INVALID_ATTR_VALUE_LEN;
    *out = (uint32_t)v;
    return 0;
}

/* ── helpers for read responses ──────────────────────────────────────────── */
/* Send back a NUL-terminated string, or "" if the source is empty/NULL. */
static int append_str(struct ble_gatt_access_ctxt *ctxt, const char *s)
{
    if (!s) s = "";
    return os_mbuf_append(ctxt->om, s, strlen(s)) == 0 ? 0 : BLE_ATT_ERR_INSUFFICIENT_RES;
}

/* Send back a uint32 as ASCII decimal — matches writeUintChar on app side. */
static int append_u32(struct ble_gatt_access_ctxt *ctxt, uint32_t v)
{
    char buf[12];
    int n = snprintf(buf, sizeof(buf), "%u", (unsigned)v);
    if (n < 0) return BLE_ATT_ERR_UNLIKELY;
    return os_mbuf_append(ctxt->om, buf, (size_t)n) == 0 ? 0 : BLE_ATT_ERR_INSUFFICIENT_RES;
}

/* ── characteristic access callbacks ─────────────────────────────────────── */
/* All config chars are READ+WRITE so the app can prefill its config form
 * with what the device actually has, instead of falling back to hardcoded
 * defaults. Reads return s_current (the snapshot loaded from NVS at boot);
 * writes go to s_staged and only flush to NVS when access_commit() fires. */

static int access_uid(uint16_t ch, uint16_t ah, struct ble_gatt_access_ctxt *ctxt, void *arg)
{
    if (ctxt->op != BLE_GATT_ACCESS_OP_READ_CHR) return BLE_ATT_ERR_UNLIKELY;
    return append_str(ctxt, s_current.device_uid);
}

static int access_ssid(uint16_t ch, uint16_t ah, struct ble_gatt_access_ctxt *ctxt, void *arg)
{
    if (ctxt->op == BLE_GATT_ACCESS_OP_READ_CHR) {
        return append_str(ctxt, s_current.ssid);
    }
    if (ctxt->op != BLE_GATT_ACCESS_OP_WRITE_CHR) return BLE_ATT_ERR_UNLIKELY;
    int rc = copy_str_from_ctxt(ctxt, s_staged.ssid, sizeof(s_staged.ssid));
    if (rc == 0) { s_staged.ssid_set = true; ESP_LOGI(TAG, "staged ssid='%s'", s_staged.ssid); }
    return rc;
}

/* Password is intentionally write-only — never expose stored credentials over
 * an unauthenticated BLE read. App side leaves the field blank to keep
 * existing creds; the commit handler treats !pass_set as "don't change". */
static int access_pass(uint16_t ch, uint16_t ah, struct ble_gatt_access_ctxt *ctxt, void *arg)
{
    if (ctxt->op != BLE_GATT_ACCESS_OP_WRITE_CHR) return BLE_ATT_ERR_UNLIKELY;
    int rc = copy_str_from_ctxt(ctxt, s_staged.password, sizeof(s_staged.password));
    if (rc == 0) { s_staged.pass_set = true; ESP_LOGI(TAG, "staged password (len=%d)", (int)strlen(s_staged.password)); }
    return rc;
}

static int access_url(uint16_t ch, uint16_t ah, struct ble_gatt_access_ctxt *ctxt, void *arg)
{
    if (ctxt->op == BLE_GATT_ACCESS_OP_READ_CHR) {
        return append_str(ctxt, s_current.api_url);
    }
    if (ctxt->op != BLE_GATT_ACCESS_OP_WRITE_CHR) return BLE_ATT_ERR_UNLIKELY;
    int rc = copy_str_from_ctxt(ctxt, s_staged.api_url, sizeof(s_staged.api_url));
    if (rc == 0) { s_staged.url_set = true; ESP_LOGI(TAG, "staged api_url='%s'", s_staged.api_url); }
    return rc;
}

static int access_hb(uint16_t ch, uint16_t ah, struct ble_gatt_access_ctxt *ctxt, void *arg)
{
    if (ctxt->op == BLE_GATT_ACCESS_OP_READ_CHR) {
        return append_u32(ctxt, s_current.heartbeat_interval_s);
    }
    if (ctxt->op != BLE_GATT_ACCESS_OP_WRITE_CHR) return BLE_ATT_ERR_UNLIKELY;
    int rc = copy_u32_from_ctxt(ctxt, &s_staged.hb_s);
    if (rc == 0) { s_staged.hb_set = true; ESP_LOGI(TAG, "staged hb=%u", (unsigned)s_staged.hb_s); }
    return rc;
}

static int access_loc(uint16_t ch, uint16_t ah, struct ble_gatt_access_ctxt *ctxt, void *arg)
{
    if (ctxt->op == BLE_GATT_ACCESS_OP_READ_CHR) {
        return append_u32(ctxt, s_current.location_interval_s);
    }
    if (ctxt->op != BLE_GATT_ACCESS_OP_WRITE_CHR) return BLE_ATT_ERR_UNLIKELY;
    int rc = copy_u32_from_ctxt(ctxt, &s_staged.loc_s);
    if (rc == 0) { s_staged.loc_set = true; ESP_LOGI(TAG, "staged loc=%u", (unsigned)s_staged.loc_s); }
    return rc;
}

static int access_in1(uint16_t ch, uint16_t ah, struct ble_gatt_access_ctxt *ctxt, void *arg)
{
    if (ctxt->op == BLE_GATT_ACCESS_OP_READ_CHR) {
        return append_str(ctxt, s_current.input1_desc);
    }
    if (ctxt->op != BLE_GATT_ACCESS_OP_WRITE_CHR) return BLE_ATT_ERR_UNLIKELY;
    int rc = copy_str_from_ctxt(ctxt, s_staged.in1_desc, sizeof(s_staged.in1_desc));
    if (rc == 0) { s_staged.in1_set = true; ESP_LOGI(TAG, "staged in1='%s'", s_staged.in1_desc); }
    return rc;
}

// ── Section B characteristics ──────────────────────────────────────────────

static int access_txmode(uint16_t ch, uint16_t ah, struct ble_gatt_access_ctxt *ctxt, void *arg)
{
    if (ctxt->op == BLE_GATT_ACCESS_OP_READ_CHR) return append_u32(ctxt, s_current.transport_mode);
    if (ctxt->op != BLE_GATT_ACCESS_OP_WRITE_CHR) return BLE_ATT_ERR_UNLIKELY;
    uint32_t v;
    int rc = copy_u32_from_ctxt(ctxt, &v);
    if (rc == 0) {
        if (v > NYS_TX_WIFI_THEN_GSM) return BLE_ATT_ERR_INVALID_ATTR_VALUE_LEN;
        s_staged.tx_mode = (uint8_t)v;
        s_staged.txmode_set = true;
        ESP_LOGI(TAG, "staged tx_mode=%u", (unsigned)v);
    }
    return rc;
}

static int access_apn(uint16_t ch, uint16_t ah, struct ble_gatt_access_ctxt *ctxt, void *arg)
{
    if (ctxt->op == BLE_GATT_ACCESS_OP_READ_CHR) return append_str(ctxt, s_current.apn);
    if (ctxt->op != BLE_GATT_ACCESS_OP_WRITE_CHR) return BLE_ATT_ERR_UNLIKELY;
    int rc = copy_str_from_ctxt(ctxt, s_staged.apn, sizeof(s_staged.apn));
    if (rc == 0) { s_staged.apn_set = true; ESP_LOGI(TAG, "staged apn='%s'", s_staged.apn); }
    return rc;
}

// apn_creds is formatted "user:pass". We split on the first ':' to populate
// apn_user and apn_pass separately. Empty string clears both. Reading returns
// just the user portion (we never expose the password over BLE reads, same
// reason the WiFi password char is write-only).
static int access_apncr(uint16_t ch, uint16_t ah, struct ble_gatt_access_ctxt *ctxt, void *arg)
{
    if (ctxt->op == BLE_GATT_ACCESS_OP_READ_CHR) return append_str(ctxt, s_current.apn_user);
    if (ctxt->op != BLE_GATT_ACCESS_OP_WRITE_CHR) return BLE_ATT_ERR_UNLIKELY;
    char tmp[64] = {0};
    int rc = copy_str_from_ctxt(ctxt, tmp, sizeof(tmp));
    if (rc != 0) return rc;
    char *colon = strchr(tmp, ':');
    if (colon) {
        *colon = '\0';
        strncpy(s_staged.apn_user, tmp,        sizeof(s_staged.apn_user) - 1);
        strncpy(s_staged.apn_pass, colon + 1,  sizeof(s_staged.apn_pass) - 1);
    } else {
        strncpy(s_staged.apn_user, tmp, sizeof(s_staged.apn_user) - 1);
        s_staged.apn_pass[0] = '\0';
    }
    s_staged.apncr_set = true;
    ESP_LOGI(TAG, "staged apn_user='%s' apn_pass=(len=%d)", s_staged.apn_user, (int)strlen(s_staged.apn_pass));
    return 0;
}

static int access_ussd(uint16_t ch, uint16_t ah, struct ble_gatt_access_ctxt *ctxt, void *arg)
{
    if (ctxt->op == BLE_GATT_ACCESS_OP_READ_CHR) return append_str(ctxt, s_current.ussd_balance);
    if (ctxt->op != BLE_GATT_ACCESS_OP_WRITE_CHR) return BLE_ATT_ERR_UNLIKELY;
    int rc = copy_str_from_ctxt(ctxt, s_staged.ussd, sizeof(s_staged.ussd));
    if (rc == 0) { s_staged.ussd_set = true; ESP_LOGI(TAG, "staged ussd='%s'", s_staged.ussd); }
    return rc;
}

static int access_gsmslp(uint16_t ch, uint16_t ah, struct ble_gatt_access_ctxt *ctxt, void *arg)
{
    if (ctxt->op == BLE_GATT_ACCESS_OP_READ_CHR) return append_u32(ctxt, s_current.gsm_idle_sleep_s);
    if (ctxt->op != BLE_GATT_ACCESS_OP_WRITE_CHR) return BLE_ATT_ERR_UNLIKELY;
    int rc = copy_u32_from_ctxt(ctxt, &s_staged.gsm_idle_s);
    if (rc == 0) { s_staged.gsmslp_set = true; ESP_LOGI(TAG, "staged gsm_idle=%u", (unsigned)s_staged.gsm_idle_s); }
    return rc;
}

static int access_gprsidl(uint16_t ch, uint16_t ah, struct ble_gatt_access_ctxt *ctxt, void *arg)
{
    if (ctxt->op == BLE_GATT_ACCESS_OP_READ_CHR) return append_u32(ctxt, s_current.gsm_gprs_idle_detach_s);
    if (ctxt->op != BLE_GATT_ACCESS_OP_WRITE_CHR) return BLE_ATT_ERR_UNLIKELY;
    int rc = copy_u32_from_ctxt(ctxt, &s_staged.gprs_idle_s);
    if (rc == 0) { s_staged.gprsidl_set = true; ESP_LOGI(TAG, "staged gprs_idle=%u", (unsigned)s_staged.gprs_idle_s); }
    return rc;
}

static int access_balivl(uint16_t ch, uint16_t ah, struct ble_gatt_access_ctxt *ctxt, void *arg)
{
    if (ctxt->op == BLE_GATT_ACCESS_OP_READ_CHR) return append_u32(ctxt, s_current.balance_check_interval_s);
    if (ctxt->op != BLE_GATT_ACCESS_OP_WRITE_CHR) return BLE_ATT_ERR_UNLIKELY;
    int rc = copy_u32_from_ctxt(ctxt, &s_staged.bal_ivl_s);
    if (rc == 0) { s_staged.balivl_set = true; ESP_LOGI(TAG, "staged bal_ivl=%u", (unsigned)s_staged.bal_ivl_s); }
    return rc;
}

static int access_commit(uint16_t ch, uint16_t ah, struct ble_gatt_access_ctxt *ctxt, void *arg)
{
    if (ctxt->op != BLE_GATT_ACCESS_OP_WRITE_CHR) return BLE_ATT_ERR_UNLIKELY;

    ESP_LOGI(TAG, "COMMIT received — applying staged config and restarting");

    if (s_staged.ssid_set) {
        cfg_save_wifi(s_staged.ssid, s_staged.pass_set ? s_staged.password : "");
    }

    uint32_t hb  = s_staged.hb_set  ? s_staged.hb_s  : s_current.heartbeat_interval_s;
    uint32_t loc = s_staged.loc_set ? s_staged.loc_s : s_current.location_interval_s;
    const char *in1 = s_staged.in1_set ? s_staged.in1_desc : s_current.input1_desc;
    const char *url = s_staged.url_set ? s_staged.api_url  : s_current.api_url;

    cfg_save_settings(hb, loc, in1, url, s_current.deep_sleep_enabled ? 1 : 0);

    // Section B saves — only persist if the app touched the char (mirrors the
    // _set guards above). Saves write to NVS keys read by cfg_load() at boot.
    if (s_staged.txmode_set) cfg_save_transport_mode(s_staged.tx_mode);

    if (s_staged.apn_set || s_staged.apncr_set) {
        cfg_save_apn(s_staged.apn_set ? s_staged.apn : s_current.apn,
                     s_staged.apncr_set ? s_staged.apn_user : s_current.apn_user,
                     s_staged.apncr_set ? s_staged.apn_pass : s_current.apn_pass);
    }
    if (s_staged.ussd_set) cfg_save_ussd_balance(s_staged.ussd);

    if (s_staged.gsmslp_set || s_staged.gprsidl_set || s_staged.balivl_set) {
        cfg_save_gsm_power(
            s_staged.gsmslp_set  ? s_staged.gsm_idle_s  : s_current.gsm_idle_sleep_s,
            s_staged.gprsidl_set ? s_staged.gprs_idle_s : s_current.gsm_gprs_idle_detach_s,
            s_staged.balivl_set  ? s_staged.bal_ivl_s   : s_current.balance_check_interval_s);
    }

    vTaskDelay(pdMS_TO_TICKS(500));
    esp_restart();
    return 0;
}

/* ── GATT service definition ─────────────────────────────────────────────── */
/* Config chars are READ+WRITE so the app can prefill its form with the
 * current device state. Password is intentionally write-only (never expose
 * stored creds over BLE). Commit is write-only by design. */
static const struct ble_gatt_svc_def s_gatt_svcs[] = {
    {
        .type = BLE_GATT_SVC_TYPE_PRIMARY,
        .uuid = &SVC_UUID.u,
        .characteristics = (struct ble_gatt_chr_def[]) {
            { .uuid = &CHR_UID_UUID.u,     .access_cb = access_uid,     .flags = BLE_GATT_CHR_F_READ },
            { .uuid = &CHR_SSID_UUID.u,    .access_cb = access_ssid,    .flags = BLE_GATT_CHR_F_READ | BLE_GATT_CHR_F_WRITE },
            { .uuid = &CHR_PASS_UUID.u,    .access_cb = access_pass,    .flags = BLE_GATT_CHR_F_WRITE },
            { .uuid = &CHR_URL_UUID.u,     .access_cb = access_url,     .flags = BLE_GATT_CHR_F_READ | BLE_GATT_CHR_F_WRITE },
            { .uuid = &CHR_HB_UUID.u,      .access_cb = access_hb,      .flags = BLE_GATT_CHR_F_READ | BLE_GATT_CHR_F_WRITE },
            { .uuid = &CHR_LOC_UUID.u,     .access_cb = access_loc,     .flags = BLE_GATT_CHR_F_READ | BLE_GATT_CHR_F_WRITE },
            { .uuid = &CHR_IN1_UUID.u,     .access_cb = access_in1,     .flags = BLE_GATT_CHR_F_READ | BLE_GATT_CHR_F_WRITE },
            // Section B characteristics
            { .uuid = &CHR_TXMODE_UUID.u,  .access_cb = access_txmode,  .flags = BLE_GATT_CHR_F_READ | BLE_GATT_CHR_F_WRITE },
            { .uuid = &CHR_APN_UUID.u,     .access_cb = access_apn,     .flags = BLE_GATT_CHR_F_READ | BLE_GATT_CHR_F_WRITE },
            { .uuid = &CHR_APNCR_UUID.u,   .access_cb = access_apncr,   .flags = BLE_GATT_CHR_F_READ | BLE_GATT_CHR_F_WRITE },
            { .uuid = &CHR_USSD_UUID.u,    .access_cb = access_ussd,    .flags = BLE_GATT_CHR_F_READ | BLE_GATT_CHR_F_WRITE },
            { .uuid = &CHR_GSMSLP_UUID.u,  .access_cb = access_gsmslp,  .flags = BLE_GATT_CHR_F_READ | BLE_GATT_CHR_F_WRITE },
            { .uuid = &CHR_GPRSIDL_UUID.u, .access_cb = access_gprsidl, .flags = BLE_GATT_CHR_F_READ | BLE_GATT_CHR_F_WRITE },
            { .uuid = &CHR_BALIVL_UUID.u,  .access_cb = access_balivl,  .flags = BLE_GATT_CHR_F_READ | BLE_GATT_CHR_F_WRITE },
            // Commit stays last — write triggers persist + reboot
            { .uuid = &CHR_CMT_UUID.u,     .access_cb = access_commit,  .flags = BLE_GATT_CHR_F_WRITE | BLE_GATT_CHR_F_WRITE_NO_RSP },
            { 0 }
        },
    },
    { 0 },
};

/* Forward declarations */
static void start_advertising(void);

/* ── GAP event callback — track connect/disconnect for sleep deferral ──── */
static int gap_event_cb(struct ble_gap_event *event, void *arg)
{
    switch (event->type) {
    case BLE_GAP_EVENT_CONNECT:
        if (event->connect.status == 0) {
            s_peer_connected = true;
            ESP_LOGI(TAG, "Peer connected (handle=%d)", event->connect.conn_handle);
        } else {
            ESP_LOGW(TAG, "Connect failed status=%d, restarting advertising", event->connect.status);
            start_advertising();
        }
        break;

    case BLE_GAP_EVENT_DISCONNECT:
        s_peer_connected = false;
        ESP_LOGI(TAG, "Peer disconnected (reason=%d), restarting advertising", event->disconnect.reason);
        start_advertising();
        break;

    case BLE_GAP_EVENT_ADV_COMPLETE:
        ESP_LOGI(TAG, "Adv complete, restarting");
        start_advertising();
        break;

    default:
        break;
    }
    return 0;
}

/* ── advertising ─────────────────────────────────────────────────────────── */
static void start_advertising(void)
{
    char name[40];
    snprintf(name, sizeof(name), "NYS-%s", s_current.device_uid);
    ble_svc_gap_device_name_set(name);

    // Advertising packet: flags + name only (max 31 bytes)
    struct ble_hs_adv_fields fields = {0};
    fields.flags = BLE_HS_ADV_F_DISC_GEN | BLE_HS_ADV_F_BREDR_UNSUP;
    fields.name = (uint8_t *)name;
    fields.name_len = strlen(name);
    fields.name_is_complete = 1;
    int rc = ble_gap_adv_set_fields(&fields);
    if (rc != 0) { ESP_LOGE(TAG, "adv_set_fields rc=%d", rc); return; }

    // Put the 128-bit service UUID in the scan response (separate 31-byte packet)
    struct ble_hs_adv_fields rsp = {0};
    rsp.uuids128 = (ble_uuid128_t *)&SVC_UUID;
    rsp.num_uuids128 = 1;
    rsp.uuids128_is_complete = 1;
    rc = ble_gap_adv_rsp_set_fields(&rsp);
    if (rc != 0) { ESP_LOGW(TAG, "adv_rsp_set_fields rc=%d (non-fatal)", rc); }

    struct ble_gap_adv_params adv_params = {0};
    adv_params.conn_mode = BLE_GAP_CONN_MODE_UND;
    adv_params.disc_mode = BLE_GAP_DISC_MODE_GEN;
    rc = ble_gap_adv_start(s_own_addr_type, NULL, BLE_HS_FOREVER,
                           &adv_params, gap_event_cb, NULL);
    if (rc != 0) ESP_LOGE(TAG, "adv_start rc=%d", rc);
    else         ESP_LOGI(TAG, "BLE advertising as '%s'", name);
}

/* ── host callbacks ──────────────────────────────────────────────────────── */
static void on_sync(void)
{
    int rc = ble_hs_id_infer_auto(0, &s_own_addr_type);
    if (rc != 0) { ESP_LOGE(TAG, "infer_auto rc=%d", rc); return; }
    start_advertising();
}

static void on_reset(int reason) { ESP_LOGW(TAG, "BLE reset, reason=%d", reason); }

static void host_task(void *arg)
{
    nimble_port_run();
    nimble_port_freertos_deinit();
}

/* ── public API ──────────────────────────────────────────────────────────── */
esp_err_t ble_cfg_init(const nys_cfg_t *current)
{
    if (!current) return ESP_ERR_INVALID_ARG;
    memcpy(&s_current, current, sizeof(s_current));
    memset(&s_staged, 0, sizeof(s_staged));

    esp_err_t err = nimble_port_init();
    if (err != ESP_OK) { ESP_LOGE(TAG, "nimble_port_init failed: %d", err); return err; }

    ble_hs_cfg.sync_cb  = on_sync;
    ble_hs_cfg.reset_cb = on_reset;

    ble_svc_gap_init();
    ble_svc_gatt_init();

    int rc = ble_gatts_count_cfg(s_gatt_svcs);
    if (rc != 0) { ESP_LOGE(TAG, "gatts_count_cfg rc=%d", rc); return ESP_FAIL; }
    rc = ble_gatts_add_svcs(s_gatt_svcs);
    if (rc != 0) { ESP_LOGE(TAG, "gatts_add_svcs rc=%d", rc); return ESP_FAIL; }

    nimble_port_freertos_init(host_task);
    return ESP_OK;
}

void ble_cfg_stop(void)
{
    ble_gap_adv_stop();
    nimble_port_stop();
}
