idf_component_register(
    SRCS "main.c"
    INCLUDE_DIRS "."
    REQUIRES
        nvs_flash
        esp_netif
        esp_event
        esp_hw_support
        driver
        freertos
        nys_common
        comms
        wifi
        gps
        input
        web
        ble_cfg
)