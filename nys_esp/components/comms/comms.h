#pragma once

#include <stdint.h>
#include <stdbool.h>
#include "esp_err.h"
#include "nvs.h"
#include "nys_common.h"

// ─── Config (NVS) ─────────────────────────────────────────────────────────────
esp_err_t cfg_load(nys_cfg_t *out);
esp_err_t cfg_save_wifi(const char *ssid, const char *password);
esp_err_t cfg_save_settings(uint32_t heartbeat_interval_s,
                             uint32_t location_interval_s,
                             const char *input1_desc,
                             const char *api_url,
                             int deep_sleep_enabled);
void      cfg_ensure_identity(nys_cfg_t *cfg);

// ─── Saved networks ───────────────────────────────────────────────────────────
// Load all saved networks into out[] (up to NYS_MAX_NETWORKS).
// Returns number of networks loaded.
int       cfg_load_networks(nys_network_t out[NYS_MAX_NETWORKS]);

// Save a network. If ssid already exists, updates its password.
// If list is full, overwrites the oldest entry.
esp_err_t cfg_save_network(const char *ssid, const char *password);

// Delete a network by ssid. Returns ESP_OK if found and deleted.
esp_err_t cfg_delete_network(const char *ssid);

// Mark ssid as the last successfully connected network.
// Moves it to index 0 so reconnect tries it first.
esp_err_t cfg_set_last_connected(const char *ssid);

// ─── Time helpers ─────────────────────────────────────────────────────────────
void    time_init(void);
int64_t time_uptime_s(void);
int64_t time_now_epoch_s(void);
void    time_try_update_base_from_system(void);
void    time_maybe_start_sntp(void);
void    time_update_from_gps(int64_t gps_epoch_s);

// ─── Message queue ────────────────────────────────────────────────────────────
void     queue_init(void);
void     queue_push_sample(void);
void     queue_drain_step(void);
uint32_t queue_count_unsent(void);
uint32_t queue_purge_sent(void);
bool     queue_load_rec_locked(nvs_handle_t h, uint32_t idx, nys_queue_rec_t *out);
void     queue_delete_rec_locked(nvs_handle_t h, uint32_t idx);

// ─── HTTP senders ─────────────────────────────────────────────────────────────
esp_err_t http_send_heartbeat(const nys_cfg_t *cfg);
esp_err_t http_send_input_change(const nys_cfg_t *cfg, int level);

// ─── Deep sleep helpers ──────────────────────────────────────────────────────
// Push current sample and drain all queued records via HTTP.
void send_all_pending(const nys_cfg_t *cfg);

// ─── Send task ────────────────────────────────────────────────────────────────
void sender_start_task(void);