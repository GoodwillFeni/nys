idf_component_register(
    SRCS "gsm.c"
    INCLUDE_DIRS "."
    REQUIRES
        driver
        freertos
        esp_timer
        nys_common
)
