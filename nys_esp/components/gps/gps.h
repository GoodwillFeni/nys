#pragma once
 
#include "esp_err.h"
#include "nys_common.h"
 
// Initialise GPS duty-cycle task (always-on mode).
// Call once from app_main after uart_driver_install.
void gps_init(void);

// Returns true if a valid fix is currently held.
bool gps_get_last_fix(gps_fix_t *out);

// Returns UTC unix epoch from GPS RMC sentences, or 0 if not yet received.
int64_t gps_get_epoch_s(void);

// Persist / restore last fix from NVS.
esp_err_t gps_nvs_load_last_fix(void);
esp_err_t gps_nvs_save_last_fix(const gps_fix_t *fix);

// Convert NMEA fix-quality code to a human-readable string.
const char *gps_fixq_to_str(int fixq);

// NEW: Deep sleep mode — call these from main.c linear flow instead of gps_init()
// Wake GPS module from backup mode
void gps_power_on(void);
// Put GPS module into backup (sleep) mode
void gps_power_off(void);
// Try to get a fix within timeout_s seconds. Returns true if fix obtained.
// Updates internal state + NVS on success.
bool gps_try_get_fix(int timeout_s);