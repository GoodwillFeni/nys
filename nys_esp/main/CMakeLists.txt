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
        ble_cfg
        gsm
)
# Note: gsm_test.c is kept in this folder as a reference for the SIM800L
# bring-up sequence (USSD, SMS, network registration). Not built. To run
# the bring-up test, temporarily swap "main.c" → "gsm_test.c" above, flash,
# read the USB console, then revert.
