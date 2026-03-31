idf_component_register(
    SRCS "input.c"
    INCLUDE_DIRS "."
    REQUIRES
        driver
        freertos
        nys_common
        wifi
        comms
        esp_timer
)