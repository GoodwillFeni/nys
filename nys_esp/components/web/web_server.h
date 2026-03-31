#pragma once

#include "esp_http_server.h"
#include "../nys_common/nys_common.h"


// Start the setup/config HTTP server (registers /, /save, /queue).
// Safe to call multiple times – only starts once.
void web_start_server(void);

// Stop the HTTP server.
void web_stop_server(void);