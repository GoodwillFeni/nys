/*
 * SIM800L bring-up smoke test.
 *
 * Standalone — does NOT depend on the rest of the firmware. Drop-in replace
 * main.c with this for a one-shot test of the GSM module, flash, watch the
 * USB console, then restore main.c.
 *
 * Wiring assumed:
 *   ESP32-C3 GPIO 6 (RX, UART0)  ←  SIM800L TXD
 *   ESP32-C3 GPIO 7 (TX, UART0)  →  SIM800L RXD   (3.3V→2.8V level OK in practice)
 *   ESP32-C3 GND                 ←→ SIM800L GND   (mandatory common ground)
 *   SIM800L VBAT  ← separate ~4.0V supply with ≥1000µF cap on the rail
 *
 * Console output assumed routed to USB Serial/JTAG (menuconfig). If console
 * is still on UART0, the SIM800L responses and ESP-IDF logs will collide on
 * the same wire and you'll see garbage.
 *
 * Runs the no-SIM bring-up sequence:
 *   AT   ATE0   AT+GMM   AT+GSN   AT+CFUN?   AT+CBC   AT+CPIN?
 *
 * Auto-detects baud (tries 115200, then 9600). Reports PASS/FAIL per step.
 */

#include <string.h>
#include <stdio.h>
#include "freertos/FreeRTOS.h"
#include "freertos/task.h"
#include "driver/uart.h"
#include "esp_log.h"

#define GSM_UART      UART_NUM_0
#define GSM_RX_GPIO   10        // was 8 — moved off LED pin
#define GSM_TX_GPIO   7
#define GSM_RX_BUF    1024

static const char *TAG = "GSM_TEST";

/* Send a string, then read until we see "OK", "ERROR", "+CME ERROR", or
 * timeout. Stores the raw response (NUL-terminated) in `out`. Returns:
 *    > 0 = number of bytes captured
 *    -1  = timeout / no response
 */
static int gsm_send(const char *cmd, char *out, size_t out_sz, int timeout_ms)
{
    uart_flush_input(GSM_UART);
    uart_write_bytes(GSM_UART, cmd, strlen(cmd));
    uart_write_bytes(GSM_UART, "\r\n", 2);

    int got = 0;
    int waited = 0;
    const int slice = 100;  // ms

    while (waited < timeout_ms) {
        int n = uart_read_bytes(GSM_UART,
                                (uint8_t *)out + got,
                                out_sz - 1 - got,
                                pdMS_TO_TICKS(slice));
        if (n > 0) {
            got += n;
            out[got] = '\0';
            // Look for terminator strings
            if (strstr(out, "\r\nOK\r\n")          ||
                strstr(out, "\r\nERROR\r\n")        ||
                strstr(out, "+CME ERROR")           ||
                strstr(out, "+CMS ERROR")) {
                return got;
            }
            if ((size_t)got >= out_sz - 1) return got;
        }
        waited += slice;
    }
    return got > 0 ? got : -1;
}

/* Strip trailing \r\n and leading echo line (the command we sent comes back
 * before the response when echo is on). For readability only. */
static void log_response(const char *label, const char *resp)
{
    if (!resp || !*resp) {
        ESP_LOGW(TAG, "%-12s: <no reply>", label);
        return;
    }
    // Replace \r and \n with spaces for single-line logging
    char clean[512] = {0};
    size_t j = 0;
    for (size_t i = 0; resp[i] && j < sizeof(clean) - 1; i++) {
        char c = resp[i];
        if (c == '\r' || c == '\n') {
            if (j > 0 && clean[j - 1] != ' ') clean[j++] = ' ';
        } else {
            clean[j++] = c;
        }
    }
    clean[j] = '\0';
    ESP_LOGI(TAG, "%-12s: %s", label, clean);
}

/* Try one baud rate. Returns true if AT got an OK response. */
static bool try_baud(int baud)
{
    uart_driver_delete(GSM_UART);  // safe even if not installed

    uart_config_t cfg = {
        .baud_rate = baud,
        .data_bits = UART_DATA_8_BITS,
        .parity    = UART_PARITY_DISABLE,
        .stop_bits = UART_STOP_BITS_1,
        .flow_ctrl = UART_HW_FLOWCTRL_DISABLE,
    };
    ESP_ERROR_CHECK(uart_driver_install(GSM_UART, GSM_RX_BUF, GSM_RX_BUF, 0, NULL, 0));
    ESP_ERROR_CHECK(uart_param_config(GSM_UART, &cfg));
    ESP_ERROR_CHECK(uart_set_pin(GSM_UART, GSM_RX_GPIO, GSM_TX_GPIO,
                                  UART_PIN_NO_CHANGE, UART_PIN_NO_CHANGE));

    ESP_LOGI(TAG, "Trying %d baud on RX=%d TX=%d ...", baud, GSM_RX_GPIO, GSM_TX_GPIO);

    char buf[256];
    // Three quick AT pings — first one often fails as autobaud syncs.
    for (int attempt = 1; attempt <= 3; attempt++) {
        memset(buf, 0, sizeof(buf));
        int n = gsm_send("AT", buf, sizeof(buf), 1000);
        if (n > 0 && strstr(buf, "OK")) {
            ESP_LOGI(TAG, "  AT replied OK at %d baud (attempt %d)", baud, attempt);
            return true;
        }
        ESP_LOGW(TAG, "  AT attempt %d at %d baud — no OK", attempt, baud);
        vTaskDelay(pdMS_TO_TICKS(300));
    }
    return false;
}

/*
 * USSD balance check.
 *
 * USSD is asynchronous: AT+CUSD=1,"*135#",15 returns OK immediately, then
 * the network pushes "+CUSD: <m>,<str>,<dcs>" some seconds later when it
 * has answered. We need to keep reading the UART after OK is received.
 *
 * <m>:
 *   0 = no further action required (final answer)
 *   1 = further action required (a menu — would need to send digits back)
 *   2 = terminated by network
 *
 * <dcs> 15 in the request = "default GSM 7-bit, no language indication".
 * In the reply, dcs is the network's chosen encoding; for Vodacom SA's
 * plain-text balance reply, this is usually 15 → plain ASCII.
 *
 * Some plans answer *135# directly with a balance string. Others send a
 * menu and require you to reply "1" (or whatever) via AT+CUSD=1,"1",15.
 * This test does only the first hop; if the reply has m=1 (menu), the
 * code prints it so you know to extend.
 */
static void run_ussd_balance(const char *code)
{
    char buf[1024];

    ESP_LOGI(TAG, "─── USSD balance check: %s ───", code);

    // Enable USSD result reporting (some firmwares default it off).
    memset(buf, 0, sizeof(buf));
    gsm_send("AT+CUSD=1", buf, sizeof(buf), 1000);
    log_response("AT+CUSD=1", buf);

    // Send the USSD. The 15 at the end = dcs (default GSM-7).
    char cmd[64];
    snprintf(cmd, sizeof(cmd), "AT+CUSD=1,\"%s\",15", code);

    // Initial command — we expect OK quickly. Then we keep reading until
    // we see "+CUSD:" or hit a timeout.
    memset(buf, 0, sizeof(buf));
    int got = gsm_send(cmd, buf, sizeof(buf), 1500);
    log_response("AT+CUSD=…", buf);

    if (got <= 0) {
        ESP_LOGW(TAG, "USSD: no initial OK from module");
        return;
    }

    // Now wait up to 30s for the asynchronous +CUSD: line. We append to
    // the existing buffer so the final log_response shows everything.
    int waited = 0;
    const int slice = 500;
    const int total_wait = 30000;
    bool got_cusd = strstr(buf, "+CUSD:") != NULL;

    while (!got_cusd && waited < total_wait) {
        int n = uart_read_bytes(GSM_UART,
                                (uint8_t *)buf + got,
                                sizeof(buf) - 1 - got,
                                pdMS_TO_TICKS(slice));
        if (n > 0) {
            got += n;
            buf[got] = '\0';
            if (strstr(buf, "+CUSD:")) got_cusd = true;
            if ((size_t)got >= sizeof(buf) - 1) break;
        }
        waited += slice;
    }

    if (got_cusd) {
        log_response("USSD reply", buf);
        if (strstr(buf, "+CUSD: 1")) {
            ESP_LOGW(TAG, "USSD reply has m=1 (menu) — would need to send a "
                          "digit back via AT+CUSD=1,\"1\",15 to drill in.");
        } else if (strstr(buf, "+CUSD: 0")) {
            ESP_LOGI(TAG, "USSD reply has m=0 (final). Look for the balance string above.");
        }
    } else {
        ESP_LOGW(TAG, "USSD: no reply within %ds. Try a different code "
                      "(*111#, *136#) or check signal/coverage.", total_wait / 1000);
        log_response("USSD final buf", buf);
    }
}

/*
 * Send an SMS in text mode.
 *
 * SMS send is a 2-step interactive command, NOT a single AT exchange:
 *   1. We send: AT+CMGS="<number>"
 *   2. Module replies with a "> " prompt (not OK)
 *   3. We send: <body> + Ctrl-Z (0x1A) to commit, or ESC (0x1B) to abort
 *   4. Module sends the message, replies with "+CMGS: <ref>" then "OK"
 *
 * Default body coding is GSM-7 (160 chars per SMS). For accented/Unicode
 * we'd need AT+CSCS="UCS2" and hex-encode the body — not needed here.
 *
 * Number format: international (+CC...) is the most reliable. SA = +27.
 * 0630218822 → +27630218822.
 */
static void run_send_sms(const char *number, const char *text)
{
    char buf[1024];

    ESP_LOGI(TAG, "─── Send SMS to %s ───", number);

    // Re-check signal right before sending. CSQ <10 is unreliable for SMS;
    // CSQ <6 will almost certainly fail. This tells us "what was the signal
    // when the SMS was submitted" — useful when chasing intermittent failures.
    memset(buf, 0, sizeof(buf));
    gsm_send("AT+CSQ", buf, sizeof(buf), 1000);
    log_response("AT+CSQ pre", buf);

    // Switch to text mode (default is PDU; PDU is 8x harder to format)
    memset(buf, 0, sizeof(buf));
    gsm_send("AT+CMGF=1", buf, sizeof(buf), 1000);
    log_response("AT+CMGF=1", buf);

    // Plain ASCII / GSM-7 character set
    memset(buf, 0, sizeof(buf));
    gsm_send("AT+CSCS=\"GSM\"", buf, sizeof(buf), 1000);
    log_response("AT+CSCS=GSM", buf);

    // Step 1: open the SMS — we expect a "> " prompt, NOT "OK"
    char cmd[64];
    snprintf(cmd, sizeof(cmd), "AT+CMGS=\"%s\"", number);

    uart_flush_input(GSM_UART);
    uart_write_bytes(GSM_UART, cmd, strlen(cmd));
    uart_write_bytes(GSM_UART, "\r\n", 2);

    // Wait up to 5s for the "> " prompt
    memset(buf, 0, sizeof(buf));
    int got = 0;
    int waited = 0;
    bool got_prompt = false;
    while (!got_prompt && waited < 5000) {
        int n = uart_read_bytes(GSM_UART,
                                (uint8_t *)buf + got,
                                sizeof(buf) - 1 - got,
                                pdMS_TO_TICKS(200));
        if (n > 0) {
            got += n;
            buf[got] = '\0';
            if (strchr(buf, '>')) got_prompt = true;
            if (strstr(buf, "ERROR")) break;
        }
        waited += 200;
    }

    if (!got_prompt) {
        ESP_LOGE(TAG, "SMS: no '>' prompt within 5s — destination invalid or module busy");
        log_response("CMGS prompt", buf);
        return;
    }

    ESP_LOGI(TAG, "SMS: got '>' prompt, writing body (%d chars)", (int)strlen(text));

    // Step 2: write the body, then Ctrl-Z to commit
    uart_write_bytes(GSM_UART, text, strlen(text));
    const char ctrl_z = 0x1A;
    uart_write_bytes(GSM_UART, &ctrl_z, 1);

    // Step 3: wait up to 60s for "+CMGS: <ref>" (success token, unique to
    // a delivered SMS) or any error. We KEEP the existing buffer contents
    // — any bytes that arrived during the "> " detection or while we were
    // writing the body/Ctrl-Z are still useful context.
    waited = 0;
    bool sent  = false;
    bool failed = false;
    while (!sent && !failed && waited < 60000) {
        int n = uart_read_bytes(GSM_UART,
                                (uint8_t *)buf + got,
                                sizeof(buf) - 1 - got,
                                pdMS_TO_TICKS(500));
        if (n > 0) {
            got += n;
            buf[got] = '\0';
            // +CMGS: <ref> only appears on successful submission. That's
            // our success-detector — don't wait for a separate OK.
            if (strstr(buf, "+CMGS:")) sent = true;
            // Any of these mean "the network or module rejected the SMS".
            if (strstr(buf, "+CMS ERROR") ||
                strstr(buf, "+CME ERROR") ||
                strstr(buf, "\r\nERROR\r\n")) failed = true;
            if ((size_t)got >= sizeof(buf) - 1) break;
        }
        waited += 500;
    }

    log_response("CMGS final", buf);

    if (sent) {
        ESP_LOGI(TAG, "✓ SMS SENT — check the recipient phone");
    } else if (strstr(buf, "+CMS ERROR: 304")) {
        ESP_LOGE(TAG, "✗ SMS failed: invalid PDU mode parameter (CMS 304)");
    } else if (strstr(buf, "+CMS ERROR: 38")) {
        ESP_LOGE(TAG, "✗ SMS failed: network out of order (CMS 38) — bad signal");
    } else if (strstr(buf, "+CMS ERROR: 21")) {
        ESP_LOGE(TAG, "✗ SMS failed: short message transfer rejected (CMS 21) — wrong SMSC?");
    } else if (strstr(buf, "+CMS ERROR")) {
        ESP_LOGE(TAG, "✗ SMS failed — see CMS code above (lookup at simcom.ee/documents/SIM800/...)");
    } else {
        // The SMS may actually have been delivered (you'll see it on the
        // recipient phone) — we just didn't catch the +CMGS reply in time
        // or it got lost in UART contamination. Treat as ambiguous, not
        // necessarily a failure.
        ESP_LOGW(TAG, "? SMS reply not captured in 60s — check the recipient phone. "
                      "If it arrived, this is a parser-side issue, not a real failure.");
    }
}

/* Run the full bring-up + network registration sequence. */
static void run_bringup(void)
{
    char buf[512];

    // Verbose error reporting — turns "ERROR" into "+CME ERROR: <code>"
    // which actually tells you what went wrong.
    memset(buf, 0, sizeof(buf));
    gsm_send("AT+CMEE=2", buf, sizeof(buf), 500);
    log_response("AT+CMEE=2", buf);

    // ATE0 — turn off command echo so the rest of the log is clean
    memset(buf, 0, sizeof(buf));
    gsm_send("ATE0", buf, sizeof(buf), 500);
    log_response("ATE0", buf);

    struct {
        const char *cmd;
        const char *label;
        int         timeout_ms;
    } steps[] = {
        { "AT",        "AT",        1000 },
        { "AT+GMM",    "AT+GMM",    1500 },   // module model
        { "AT+CGMR",   "AT+CGMR",   1500 },   // firmware revision
        { "AT+GSN",    "AT+GSN",    1500 },   // IMEI
        { "AT+CFUN?",  "AT+CFUN?",  1500 },   // functionality level
        { "AT+CBC",    "AT+CBC",    1500 },   // battery / supply voltage
        { "AT+CPIN?",  "AT+CPIN?",  2000 },   // SIM status (expect READY)
        { "AT+CCID",   "AT+CCID",   2000 },   // SIM hardware serial — proves SIM is read
        { "AT+CSQ",    "AT+CSQ",    1500 },   // signal quality (need ≥10 for usable)
        // ── Network registration ───────────────────────────────────────
        { "AT+CREG?",  "AT+CREG?",  2000 },   // home-network registration status
        { "AT+COPS?",  "AT+COPS?",  5000 },   // current operator (slow — talks to network)
        // ── GPRS / data path ───────────────────────────────────────────
        { "AT+CGATT?", "AT+CGATT?", 2000 },   // GPRS attach status (1 = attached)
        { "AT+CGREG?", "AT+CGREG?", 2000 },   // GPRS registration status
    };

    int passed = 0;
    int total  = sizeof(steps) / sizeof(steps[0]);

    for (int i = 0; i < total; i++) {
        memset(buf, 0, sizeof(buf));
        int n = gsm_send(steps[i].cmd, buf, sizeof(buf), steps[i].timeout_ms);
        if (n > 0 && (strstr(buf, "OK") || strstr(buf, "+CME ERROR: 10"))) {
            // CME ERROR: 10 = "SIM not inserted" — counts as PASS for AT+CPIN?
            // because it proves the module + SIM-slot pins are functional.
            passed++;
        }
        log_response(steps[i].label, buf);
        vTaskDelay(pdMS_TO_TICKS(200));
    }

    ESP_LOGI(TAG, "=========================================");
    ESP_LOGI(TAG, "Bring-up summary: %d / %d steps passed", passed, total);
    ESP_LOGI(TAG, "=========================================");
    if (passed == total) {
        ESP_LOGI(TAG, "ALL %d CHECKS PASSED. Module is on the network and ready for data.", total);
    } else if (passed >= total - 2) {
        ESP_LOGW(TAG, "Mostly OK (%d/%d) — review the failed steps above.", passed, total);
    } else {
        ESP_LOGE(TAG, "Multiple failures (%d/%d) — check power, ground, baud, or wiring.",
                 passed, total);
    }
}

void app_main(void)
{
    ESP_LOGI(TAG, "SIM800L bring-up test starting");
    ESP_LOGI(TAG, "Waiting 5s for module to power on (NET LED should start blinking)...");
    vTaskDelay(pdMS_TO_TICKS(5000));

    if (try_baud(115200) || try_baud(9600)) {
        run_bringup();
        // Try the legacy Vodacom self-service code first — it's usually
        // the fastest direct-balance reply. If it returns a menu (m=1),
        // try *111# or *136# next.
        run_ussd_balance("*135#");
        // SMS test — sends to a SA number in international format.
        // Costs airtime; comment out once verified to avoid spamming.
        run_send_sms("+27630218822", "NYS unit test SMS — please ignore.");
    } else {
        ESP_LOGE(TAG, "No response at 115200 or 9600 baud.");
        ESP_LOGE(TAG, "Check (in order):");
        ESP_LOGE(TAG, "  1. NET LED on the module — is it blinking? If not → power issue.");
        ESP_LOGE(TAG, "  2. VBAT voltage under load (must stay 3.5-4.4V even during TX).");
        ESP_LOGE(TAG, "  3. TX/RX swapped — desolder one end and swap.");
        ESP_LOGE(TAG, "  4. Common ground between ESP32 and module — must be tied.");
        ESP_LOGE(TAG, "  5. PWRKEY pin — most HKD breakouts auto-start, bare modules need a 1s pulse to GND.");
    }

    // Idle forever so the log stays visible.
    while (1) vTaskDelay(pdMS_TO_TICKS(60000));
}
