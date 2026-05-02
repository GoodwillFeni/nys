#pragma once

#include "esp_err.h"
#include "../nys_common/nys_common.h"

/**
 * Initialize and start the BLE GATT config server.
 *
 * Advertises as "NYS-<device_uid>" so the mobile app can verify the device
 * belongs to the user's account before connecting.
 *
 * GATT Service + characteristics: see ble_cfg.c for UUIDs.
 */
esp_err_t ble_cfg_init(const nys_cfg_t *current);

/** Stop advertising and release BLE stack (optional; not typically needed). */
void ble_cfg_stop(void);

/**
 * True while a central (phone) is currently connected over GATT. Used by
 * deep-sleep code to defer entering sleep so a configuration write isn't
 * cut off mid-flight.
 */
bool ble_cfg_peer_connected(void);
