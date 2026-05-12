idf_component_register(
    SRCS "comms.c"
    INCLUDE_DIRS "."
    REQUIRES
        esp_http_client
        esp_timer
        esp_wifi
        nvs_flash
        freertos
        json
        mbedtls
        nys_common
        wifi
        gps
    # gsm.h is included only from comms.c (private impl), not from comms.h,
    # so it goes in PRIV_REQUIRES rather than REQUIRES.
    PRIV_REQUIRES
        gsm
)