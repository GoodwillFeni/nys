// SIM800L GSM driver — HTTP-over-GPRS, USSD query, sleep/wake, attach/detach.
//
// Promoted from the standalone bring-up harness in main/gsm_test.c so the
// rest of the firmware can use the modem as a transport. The bring-up file
// stays as reference; this component is the production code path.
//
// Lifecycle:
//   gsm_init()              once at boot. Configures UART.
//   gsm_wake()              wake from sleep before any TX.
//   gsm_gprs_attach()       attach GPRS once per "burst" of HTTP requests.
//   gsm_http_post_json()    one POST. Repeat as needed while attached.
//   gsm_gprs_detach()       optional — detach to save power between bursts.
//   gsm_sleep()             optional — sleep the modem when idle.
//
// All functions are blocking. Caller decides timeouts. None of this runs in
// an ISR or fast path — the dispatcher in comms.c handles task scheduling.

#pragma once

#include <stdbool.h>
#include <stddef.h>
#include "esp_err.h"

#ifdef __cplusplus
extern "C" {
#endif

// One-time UART + state init. Safe to call multiple times — no-op if already inited.
esp_err_t gsm_init(int uart_num, int tx_pin, int rx_pin, int baud);

// GPRS attach with the given APN. Blocks up to timeout_ms. ESP_OK if attached,
// ESP_FAIL otherwise. Caller should check signal first if it cares about WHY.
esp_err_t gsm_gprs_attach(const char *apn, const char *user, const char *pass, int timeout_ms);
esp_err_t gsm_gprs_detach(void);

// POST a JSON body to `url` over GPRS. Returns ESP_OK on HTTP 2xx, ESP_FAIL
// on anything else (network error, non-2xx status, timeout). Assumes GPRS
// is already attached — won't call gsm_gprs_attach for you.
esp_err_t gsm_http_post_json(const char *url, const char *json_body, int timeout_ms);

// Issue a USSD code and capture the network's reply text. Returns ESP_OK
// when a +CUSD: line was received within timeout_ms, ESP_FAIL otherwise.
// `out` is NUL-terminated on success; may contain trailing AT noise — caller
// trims if it cares.
esp_err_t gsm_ussd_query(const char *code, char *out, size_t out_len, int timeout_ms);

// Sleep the modem (AT+CSCLK=2). Wake with gsm_wake() before next TX.
esp_err_t gsm_sleep(void);
// Wake from sleep. Sends AT until OK or times out. ~50ms typical.
esp_err_t gsm_wake(void);

bool gsm_is_attached(void);
bool gsm_is_awake(void);

#ifdef __cplusplus
}
#endif
