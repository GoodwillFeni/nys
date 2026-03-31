# The following five lines of boilerplate have to be in your project's
# CMakeLists in this exact order for cmake to work correctly
cmake_minimum_required(VERSION 3.16)
set(EXTRA_COMPONENT_DIRS
    ${CMAKE_SOURCE_DIR}/components/nys_common
    ${CMAKE_SOURCE_DIR}/components/comms
    ${CMAKE_SOURCE_DIR}/components/gps
    ${CMAKE_SOURCE_DIR}/components/input
    ${CMAKE_SOURCE_DIR}/components/web
    ${CMAKE_SOURCE_DIR}/components/wifi
)

include($ENV{IDF_PATH}/tools/cmake/project.cmake)
project(nys_esp)
