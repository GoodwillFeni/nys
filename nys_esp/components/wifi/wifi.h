#pragma once

#include <stdbool.h>
#include <stdint.h>
#include "freertos/FreeRTOS.h"
#include "freertos/event_groups.h"
#include "nys_common.h"

// Shared event group – other components read WIFI_CONNECTED_BIT from this.
extern EventGroupHandle_t s_wifi_event_group;

// Initialise WiFi drivers (call once before any other wifi_ function).
void wifi_ensure_inited(bool need_sta, bool need_ap);

// Connect to a specific network and start a temporary AP portal window.
void wifi_connect_to_network(const char *ssid, const char *password);

// Attempt to connect to a saved network, blocking for up to timeout_ms.
// Cycles through saved networks with last connected first.
// Returns true if connected.
bool wifi_try_reconnect(uint32_t timeout_ms);

// Return the current STA IP as a static string (empty string if not connected).
const char *wifi_get_ip_str(void);

// Auto-connect to saved network on boot, or start AP portal if none found.
void wifi_auto_connect_on_boot(void);