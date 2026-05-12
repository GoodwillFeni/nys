// SIM800L driver — see gsm.h for the public contract.
//
// Implementation notes:
//   - All AT exchanges use a single 1KB scratch buffer; concurrent calls
//     from multiple tasks are NOT supported (caller serializes via the
//     dispatcher in comms.c).
//   - Terminator detection looks for "\r\nOK\r\n" / "\r\nERROR\r\n" /
//     "+CME ERROR" / "+CMS ERROR" — same set as gsm_test.c so behavior is
//     identical to the bring-up harness.
//   - All bound-checks treat `out_sz - 1` as max to leave room for NUL.

#include "gsm.h"

#include <string.h>
#include <stdio.h>
#include "freertos/FreeRTOS.h"
#include "freertos/task.h"
#include "driver/uart.h"
#include "esp_log.h"
#include "esp_timer.h"

static const char *TAG = "GSM";

static int  s_uart  = -1;
static bool s_inited   = false;
static bool s_attached = false;
static bool s_awake    = true;

// ─── Low-level AT exchange ──────────────────────────────────────────────────

// Send `cmd` + CRLF, read until a terminator or timeout. Returns bytes captured
// (>0) or -1 on timeout. `out` is NUL-terminated.
static int at_send(const char *cmd, char *out, size_t out_sz, int timeout_ms)
{
    if (s_uart < 0 || !cmd || !out || out_sz == 0) return -1;
    uart_flush_input((uart_port_t)s_uart);
    uart_write_bytes((uart_port_t)s_uart, cmd, strlen(cmd));
    uart_write_bytes((uart_port_t)s_uart, "\r\n", 2);

    int got = 0, waited = 0;
    const int slice = 100;
    out[0] = '\0';

    while (waited < timeout_ms) {
        int n = uart_read_bytes((uart_port_t)s_uart,
                                (uint8_t *)out + got,
                                out_sz - 1 - got,
                                pdMS_TO_TICKS(slice));
        if (n > 0) {
            got += n;
            out[got] = '\0';
            if (strstr(out, "\r\nOK\r\n")    ||
                strstr(out, "\r\nERROR\r\n") ||
                strstr(out, "+CME ERROR")    ||
                strstr(out, "+CMS ERROR")) {
                return got;
            }
            if ((size_t)got >= out_sz - 1) return got;
        }
        waited += slice;
    }
    return got > 0 ? got : -1;
}

// ─── Init / power / sleep ───────────────────────────────────────────────────

esp_err_t gsm_init(int uart_num, int tx_pin, int rx_pin, int baud)
{
    if (s_inited && s_uart == uart_num) return ESP_OK;

    // Re-install driver if we're switching UARTs or baud rates
    if (s_uart >= 0) uart_driver_delete((uart_port_t)s_uart);

    s_uart = uart_num;

    uart_config_t cfg = {
        .baud_rate = baud,
        .data_bits = UART_DATA_8_BITS,
        .parity    = UART_PARITY_DISABLE,
        .stop_bits = UART_STOP_BITS_1,
        .flow_ctrl = UART_HW_FLOWCTRL_DISABLE,
        .source_clk = UART_SCLK_DEFAULT,
    };
    esp_err_t err = uart_driver_install((uart_port_t)uart_num, 1024, 1024, 0, NULL, 0);
    if (err != ESP_OK) { ESP_LOGE(TAG, "uart_driver_install: %s", esp_err_to_name(err)); return err; }
    err = uart_param_config((uart_port_t)uart_num, &cfg);
    if (err != ESP_OK) return err;
    err = uart_set_pin((uart_port_t)uart_num, tx_pin, rx_pin,
                       UART_PIN_NO_CHANGE, UART_PIN_NO_CHANGE);
    if (err != ESP_OK) return err;

    s_inited = true;
    s_awake  = true;
    s_attached = false;

    // Probe — three AT pings to let auto-baud settle
    char buf[128];
    bool ok = false;
    for (int i = 0; i < 3; i++) {
        if (at_send("AT", buf, sizeof(buf), 1000) > 0 && strstr(buf, "OK")) { ok = true; break; }
        vTaskDelay(pdMS_TO_TICKS(300));
    }
    if (!ok) {
        ESP_LOGW(TAG, "Modem did not reply to AT at %d baud", baud);
        // Still return OK — caller may try a different baud or treat as transient.
    } else {
        // Quiet down: verbose CME errors + no echo. Same as gsm_test.c run_bringup.
        (void)at_send("AT+CMEE=2", buf, sizeof(buf), 500);
        (void)at_send("ATE0",      buf, sizeof(buf), 500);
        ESP_LOGI(TAG, "Modem online at %d baud on UART%d (TX=%d RX=%d)", baud, uart_num, tx_pin, rx_pin);
    }
    return ESP_OK;
}

esp_err_t gsm_sleep(void)
{
    if (!s_inited) return ESP_ERR_INVALID_STATE;
    char buf[64];
    // CSCLK=2 puts the modem into automatic slow-clock sleep; DTR controls.
    // We don't toggle DTR here (would need an extra GPIO) — instead we rely
    // on the modem entering sleep when UART is idle. Wake by sending AT.
    int n = at_send("AT+CSCLK=2", buf, sizeof(buf), 1000);
    s_awake = false;
    ESP_LOGD(TAG, "gsm_sleep: %s", (n > 0 && strstr(buf, "OK")) ? "OK" : "no-OK");
    return ESP_OK;
}

esp_err_t gsm_wake(void)
{
    if (!s_inited) return ESP_ERR_INVALID_STATE;
    if (s_awake) return ESP_OK;
    char buf[64];
    // First AT may be ignored while waking — send up to 3.
    for (int i = 0; i < 3; i++) {
        if (at_send("AT", buf, sizeof(buf), 500) > 0 && strstr(buf, "OK")) {
            // Disable sleep so the next commands aren't dropped.
            (void)at_send("AT+CSCLK=0", buf, sizeof(buf), 500);
            s_awake = true;
            ESP_LOGD(TAG, "gsm_wake: awake on attempt %d", i + 1);
            return ESP_OK;
        }
        vTaskDelay(pdMS_TO_TICKS(100));
    }
    ESP_LOGW(TAG, "gsm_wake: no OK after 3 attempts");
    return ESP_FAIL;
}

bool gsm_is_attached(void) { return s_attached; }
bool gsm_is_awake(void)    { return s_awake; }

// ─── GPRS attach/detach ─────────────────────────────────────────────────────

esp_err_t gsm_gprs_attach(const char *apn, const char *user, const char *pass, int timeout_ms)
{
    if (!s_inited || !apn) return ESP_ERR_INVALID_STATE;
    if (s_attached) return ESP_OK;

    char buf[256], cmd[128];

    // Check signal — anything below 10 is going to struggle, but don't fail here.
    (void)at_send("AT+CSQ",     buf, sizeof(buf), 1000);
    (void)at_send("AT+CGATT?",  buf, sizeof(buf), 2000);

    // SAPBR (bearer profile) approach — most reliable on SIM800L.
    (void)at_send("AT+SAPBR=3,1,\"Contype\",\"GPRS\"", buf, sizeof(buf), 1000);

    snprintf(cmd, sizeof(cmd), "AT+SAPBR=3,1,\"APN\",\"%s\"", apn);
    (void)at_send(cmd, buf, sizeof(buf), 1000);

    if (user && user[0]) {
        snprintf(cmd, sizeof(cmd), "AT+SAPBR=3,1,\"USER\",\"%s\"", user);
        (void)at_send(cmd, buf, sizeof(buf), 1000);
    }
    if (pass && pass[0]) {
        snprintf(cmd, sizeof(cmd), "AT+SAPBR=3,1,\"PWD\",\"%s\"", pass);
        (void)at_send(cmd, buf, sizeof(buf), 1000);
    }

    // Open bearer — this is the slow one (can take 5–15 s on first attach)
    int n = at_send("AT+SAPBR=1,1", buf, sizeof(buf), timeout_ms);
    if (n <= 0 || !strstr(buf, "OK")) {
        ESP_LOGE(TAG, "SAPBR open failed: %s", buf);
        return ESP_FAIL;
    }

    // Confirm — AT+SAPBR=2,1 returns "+SAPBR: 1,1,<ip>"
    n = at_send("AT+SAPBR=2,1", buf, sizeof(buf), 2000);
    if (n > 0 && strstr(buf, "+SAPBR: 1,1,")) {
        s_attached = true;
        ESP_LOGI(TAG, "GPRS attached");
        return ESP_OK;
    }
    ESP_LOGE(TAG, "GPRS attach confirm failed: %s", buf);
    return ESP_FAIL;
}

esp_err_t gsm_gprs_detach(void)
{
    if (!s_inited) return ESP_ERR_INVALID_STATE;
    if (!s_attached) return ESP_OK;
    char buf[128];
    (void)at_send("AT+SAPBR=0,1", buf, sizeof(buf), 5000);
    s_attached = false;
    ESP_LOGI(TAG, "GPRS detached");
    return ESP_OK;
}

// ─── HTTP POST over GPRS ────────────────────────────────────────────────────

esp_err_t gsm_http_post_json(const char *url, const char *json_body, int timeout_ms)
{
    if (!s_inited || !s_attached || !url || !json_body) return ESP_ERR_INVALID_STATE;
    char buf[512], cmd[256];

    (void)at_send("AT+HTTPTERM",  buf, sizeof(buf), 500);   // safe even if not init
    int n = at_send("AT+HTTPINIT", buf, sizeof(buf), 2000);
    if (n <= 0 || !strstr(buf, "OK")) { ESP_LOGE(TAG, "HTTPINIT failed: %s", buf); return ESP_FAIL; }

    (void)at_send("AT+HTTPPARA=\"CID\",1",                            buf, sizeof(buf), 1000);
    snprintf(cmd, sizeof(cmd), "AT+HTTPPARA=\"URL\",\"%s\"", url);
    (void)at_send(cmd,                                                buf, sizeof(buf), 1000);
    (void)at_send("AT+HTTPPARA=\"CONTENT\",\"application/json\"",     buf, sizeof(buf), 1000);

    // HTTPDATA: tell modem we'll send N bytes, wait up to M ms for input prompt.
    int body_len = (int)strlen(json_body);
    snprintf(cmd, sizeof(cmd), "AT+HTTPDATA=%d,%d", body_len, 10000);

    uart_flush_input((uart_port_t)s_uart);
    uart_write_bytes((uart_port_t)s_uart, cmd, strlen(cmd));
    uart_write_bytes((uart_port_t)s_uart, "\r\n", 2);

    // Wait for "DOWNLOAD"
    int got = 0, waited = 0;
    bool got_dl = false;
    while (!got_dl && waited < 2000) {
        int r = uart_read_bytes((uart_port_t)s_uart,
                                (uint8_t *)buf + got, sizeof(buf) - 1 - got,
                                pdMS_TO_TICKS(200));
        if (r > 0) {
            got += r; buf[got] = '\0';
            if (strstr(buf, "DOWNLOAD")) got_dl = true;
            if (strstr(buf, "ERROR"))    break;
        }
        waited += 200;
    }
    if (!got_dl) {
        ESP_LOGE(TAG, "HTTPDATA no DOWNLOAD: %s", buf);
        (void)at_send("AT+HTTPTERM", buf, sizeof(buf), 500);
        return ESP_FAIL;
    }

    // Send the body, then wait for OK
    uart_write_bytes((uart_port_t)s_uart, json_body, body_len);
    n = at_send("", buf, sizeof(buf), 5000);  // empty command — just wait for the OK
    if (n <= 0 || !strstr(buf, "OK")) {
        ESP_LOGE(TAG, "HTTPDATA body write no OK: %s", buf);
        (void)at_send("AT+HTTPTERM", buf, sizeof(buf), 500);
        return ESP_FAIL;
    }

    // Action 1 = POST. Returns OK immediately, then "+HTTPACTION: 1,<status>,<bytes>"
    // when the server has responded.
    n = at_send("AT+HTTPACTION=1", buf, sizeof(buf), 2000);
    if (n <= 0 || !strstr(buf, "OK")) {
        ESP_LOGE(TAG, "HTTPACTION no OK: %s", buf);
        (void)at_send("AT+HTTPTERM", buf, sizeof(buf), 500);
        return ESP_FAIL;
    }

    // Wait for +HTTPACTION: 1,<status>,<bytes>
    got = 0; waited = 0;
    bool got_action = false;
    int status = 0;
    while (!got_action && waited < timeout_ms) {
        int r = uart_read_bytes((uart_port_t)s_uart,
                                (uint8_t *)buf + got, sizeof(buf) - 1 - got,
                                pdMS_TO_TICKS(500));
        if (r > 0) {
            got += r; buf[got] = '\0';
            char *p = strstr(buf, "+HTTPACTION: 1,");
            if (p) {
                p += strlen("+HTTPACTION: 1,");
                status = atoi(p);
                got_action = true;
            }
        }
        waited += 500;
    }

    (void)at_send("AT+HTTPTERM", buf, sizeof(buf), 500);

    if (!got_action) {
        ESP_LOGE(TAG, "HTTPACTION no response within %dms", timeout_ms);
        return ESP_FAIL;
    }
    ESP_LOGI(TAG, "GSM POST status=%d", status);
    return (status >= 200 && status < 300) ? ESP_OK : ESP_FAIL;
}

// ─── USSD ───────────────────────────────────────────────────────────────────

esp_err_t gsm_ussd_query(const char *code, char *out, size_t out_len, int timeout_ms)
{
    if (!s_inited || !code || !out || out_len < 16) return ESP_ERR_INVALID_STATE;
    out[0] = '\0';

    char tmp[512], cmd[64];

    // Enable USSD result reporting (no-op if already on)
    (void)at_send("AT+CUSD=1", tmp, sizeof(tmp), 1000);

    snprintf(cmd, sizeof(cmd), "AT+CUSD=1,\"%s\",15", code);
    int got = at_send(cmd, tmp, sizeof(tmp), 1500);
    if (got <= 0) return ESP_FAIL;

    // Now wait for the asynchronous +CUSD: line
    bool got_cusd = strstr(tmp, "+CUSD:") != NULL;
    int waited = 0;
    const int slice = 500;
    while (!got_cusd && waited < timeout_ms) {
        int r = uart_read_bytes((uart_port_t)s_uart,
                                (uint8_t *)tmp + got, sizeof(tmp) - 1 - got,
                                pdMS_TO_TICKS(slice));
        if (r > 0) {
            got += r; tmp[got] = '\0';
            if (strstr(tmp, "+CUSD:")) got_cusd = true;
            if ((size_t)got >= sizeof(tmp) - 1) break;
        }
        waited += slice;
    }

    if (!got_cusd) return ESP_FAIL;

    // Extract the reply text between the first " and the next "
    char *q1 = strchr(strstr(tmp, "+CUSD:"), '"');
    if (q1) {
        char *q2 = strchr(q1 + 1, '"');
        if (q2 && (size_t)(q2 - q1 - 1) < out_len) {
            memcpy(out, q1 + 1, q2 - q1 - 1);
            out[q2 - q1 - 1] = '\0';
            return ESP_OK;
        }
    }
    // Fallback — copy the whole +CUSD line if we couldn't parse quotes
    char *line = strstr(tmp, "+CUSD:");
    strncpy(out, line, out_len - 1);
    out[out_len - 1] = '\0';
    return ESP_OK;
}
