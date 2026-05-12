<?php

/**
 * Thresholds for the Device Dashboard (Section E of the pre-deploy plan).
 * Edit any of these via .env and clear bootstrap/cache/config.php to apply
 * without a code release.
 *
 *   DEVICE_LATEST_FIRMWARE   — what counts as "current" firmware. Heartbeats
 *                              reporting any other version flag as outdated.
 *                              Bump this each time you ship a new firmware.
 *   DEVICE_LOW_BALANCE_R     — balance threshold in rand. Devices reporting
 *                              below this on their last heartbeat show up
 *                              under "Devices with low balance".
 *   DEVICE_STALE_HOURS       — devices whose last_seen_at is older than this
 *                              many hours show up under "Devices not reporting".
 */
return [
    'latest_firmware'        => env('DEVICE_LATEST_FIRMWARE', '1.0.0'),
    'low_balance_threshold'  => (float) env('DEVICE_LOW_BALANCE_R', 5.0),
    'stale_report_hours'     => (int)   env('DEVICE_STALE_HOURS', 24),
];
