idf_component_register(
    SRCS "ble_cfg.c"
    INCLUDE_DIRS "."
    REQUIRES
        nvs_flash
        bt
        nys_common
        comms
)
