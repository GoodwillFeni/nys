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
)