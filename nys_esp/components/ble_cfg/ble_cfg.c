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
 * Chars:   ...02 (device_uid, R) ...03 (ssid, W) ...04 (password, W)
 *          ...05 (api_url, W) ...06 (hb_interval, W) ...07 (loc_interval, W)
 *          ...08 (input1_desc, W) ...09 (commit, W)
 */
#define BLE_UUID128_INIT_NYS(last)                                            \
    BLE_UUID128_INIT(0x00,0x00,0x00,0x00, 0x00,0x00, 0x00,0x00,               \
                     0x53,0x59,0x4e, 0x00,0x00, 0xe8,0xa7, (last))

static const ble_uuid128_t SVC_UUID      = BLE_UUID128_INIT_NYS(0x01);
static const ble_uuid128_t CHR_UID_UUID  = BLE_UUID128_INIT_NYS(0x02);
static const ble_uuid128_t CHR_SSID_UUID = BLE_UUID128_INIT_NYS(0x03);
static const ble_uuid128_t CHR_PASS_UUID = BLE_UUID128_INIT_NYS(0x04);
static const ble_uuid128_t CHR_URL_UUID  = BLE_UUID128_INIT_NYS(0x05);
static const ble_uuid128_t CHR_HB_UUID   = BLE_UUID128_INIT_NYS(0x06);
static const ble_uuid128_t CHR_LOC_UUID  = BLE_UUID128_INIT_NYS(0x07);
static const ble_uuid128_t CHR_IN1_UUID  = BLE_UUID128_INIT_NYS(0x08);
static const ble_uuid128_t CHR_CMT_UUID  = BLE_UUID128_INIT_NYS(0x09);

/* ── staged config (buffered until commit) ───────────────────────────────── */
typedef struct {
    char     ssid[33];
    char     password[65];
    char     api_url[128];
    uint32_t hb_s;
    uint32_t loc_s;
    char     in1_desc[64];
    bool     ssid_set, pass_set, url_set, hb_set, loc_set, in1_set;
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

/* ── characteristic access callbacks ─────────────────────────────────────── */
static int access_uid(uint16_t ch, uint16_t ah, struct ble_gatt_access_ctxt *ctxt, void *arg)
{
    if (ctxt->op != BLE_GATT_ACCESS_OP_READ_CHR) return BLE_ATT_ERR_UNLIKELY;
    return os_mbuf_append(ctxt->om, s_current.device_uid, strlen(s_current.device_uid))
         == 0 ? 0 : BLE_ATT_ERR_INSUFFICIENT_RES;
}

static int access_ssid(uint16_t ch, uint16_t ah, struct ble_gatt_access_ctxt *ctxt, void *arg)
{
    if (ctxt->op != BLE_GATT_ACCESS_OP_WRITE_CHR) return BLE_ATT_ERR_UNLIKELY;
    int rc = copy_str_from_ctxt(ctxt, s_staged.ssid, sizeof(s_staged.ssid));
    if (rc == 0) { s_staged.ssid_set = true; ESP_LOGI(TAG, "staged ssid='%s'", s_staged.ssid); }
    return rc;
}

static int access_pass(uint16_t ch, uint16_t ah, struct ble_gatt_access_ctxt *ctxt, void *arg)
{
    if (ctxt->op != BLE_GATT_ACCESS_OP_WRITE_CHR) return BLE_ATT_ERR_UNLIKELY;
    int rc = copy_str_from_ctxt(ctxt, s_staged.password, sizeof(s_staged.password));
    if (rc == 0) { s_staged.pass_set = true; ESP_LOGI(TAG, "staged password (len=%d)", (int)strlen(s_staged.password)); }
    return rc;
}

static int access_url(uint16_t ch, uint16_t ah, struct ble_gatt_access_ctxt *ctxt, void *arg)
{
    if (ctxt->op != BLE_GATT_ACCESS_OP_WRITE_CHR) return BLE_ATT_ERR_UNLIKELY;
    int rc = copy_str_from_ctxt(ctxt, s_staged.api_url, sizeof(s_staged.api_url));
    if (rc == 0) { s_staged.url_set = true; ESP_LOGI(TAG, "staged api_url='%s'", s_staged.api_url); }
    return rc;
}

static int access_hb(uint16_t ch, uint16_t ah, struct ble_gatt_access_ctxt *ctxt, void *arg)
{
    if (ctxt->op != BLE_GATT_ACCESS_OP_WRITE_CHR) return BLE_ATT_ERR_UNLIKELY;
    int rc = copy_u32_from_ctxt(ctxt, &s_staged.hb_s);
    if (rc == 0) { s_staged.hb_set = true; ESP_LOGI(TAG, "staged hb=%u", (unsigned)s_staged.hb_s); }
    return rc;
}

static int access_loc(uint16_t ch, uint16_t ah, struct ble_gatt_access_ctxt *ctxt, void *arg)
{
    if (ctxt->op != BLE_GATT_ACCESS_OP_WRITE_CHR) return BLE_ATT_ERR_UNLIKELY;
    int rc = copy_u32_from_ctxt(ctxt, &s_staged.loc_s);
    if (rc == 0) { s_staged.loc_set = true; ESP_LOGI(TAG, "staged loc=%u", (unsigned)s_staged.loc_s); }
    return rc;
}

static int access_in1(uint16_t ch, uint16_t ah, struct ble_gatt_access_ctxt *ctxt, void *arg)
{
    if (ctxt->op != BLE_GATT_ACCESS_OP_WRITE_CHR) return BLE_ATT_ERR_UNLIKELY;
    int rc = copy_str_from_ctxt(ctxt, s_staged.in1_desc, sizeof(s_staged.in1_desc));
    if (rc == 0) { s_staged.in1_set = true; ESP_LOGI(TAG, "staged in1='%s'", s_staged.in1_desc); }
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

    vTaskDelay(pdMS_TO_TICKS(500));
    esp_restart();
    return 0;
}

/* ── GATT service definition ─────────────────────────────────────────────── */
static const struct ble_gatt_svc_def s_gatt_svcs[] = {
    {
        .type = BLE_GATT_SVC_TYPE_PRIMARY,
        .uuid = &SVC_UUID.u,
        .characteristics = (struct ble_gatt_chr_def[]) {
            { .uuid = &CHR_UID_UUID.u,  .access_cb = access_uid,    .flags = BLE_GATT_CHR_F_READ },
            { .uuid = &CHR_SSID_UUID.u, .access_cb = access_ssid,   .flags = BLE_GATT_CHR_F_WRITE },
            { .uuid = &CHR_PASS_UUID.u, .access_cb = access_pass,   .flags = BLE_GATT_CHR_F_WRITE },
            { .uuid = &CHR_URL_UUID.u,  .access_cb = access_url,    .flags = BLE_GATT_CHR_F_WRITE },
            { .uuid = &CHR_HB_UUID.u,   .access_cb = access_hb,     .flags = BLE_GATT_CHR_F_WRITE },
            { .uuid = &CHR_LOC_UUID.u,  .access_cb = access_loc,    .flags = BLE_GATT_CHR_F_WRITE },
            { .uuid = &CHR_IN1_UUID.u,  .access_cb = access_in1,    .flags = BLE_GATT_CHR_F_WRITE },
            { .uuid = &CHR_CMT_UUID.u,  .access_cb = access_commit, .flags = BLE_GATT_CHR_F_WRITE | BLE_GATT_CHR_F_WRITE_NO_RSP },
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
