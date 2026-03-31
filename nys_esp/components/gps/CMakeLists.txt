idf_component_register(
    SRCS "gps.c"
    INCLUDE_DIRS "."
    REQUIRES
        driver
        nvs_flash
        freertos
        nys_common
        wifi
        comms
)