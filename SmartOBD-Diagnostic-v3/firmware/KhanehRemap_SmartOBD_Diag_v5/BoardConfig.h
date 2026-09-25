#pragma once
#include <Arduino.h>

/*
  KHANEH REMAP - ESP32-C3 Super Mini reference pin map
*/
#define KR_CAN_TX_PIN            6
#define KR_CAN_RX_PIN            7
#define KR_KLINE_TX_PIN          20
#define KR_KLINE_RX_PIN          21
#define KR_WATER_EXCITE_PIN      1
#define KR_WATER_SENSE_PIN       0
#define KR_BATTERY_SENSE_PIN     3
#define KR_BUZZER_PIN            10

#define KR_WATER_LOW_PHASE_MAX       550
#define KR_WATER_WET_MIN_RAW         850
#define KR_WATER_WET_FULL_RAW        2800
#define KR_WATER_SHORT_HIGH_RAW      4000
#define KR_WATER_BAD_SAMPLES         3
#define KR_WATER_GOOD_SAMPLES        10
#define KR_WATER_SAMPLE_MS           180
#define KR_BATTERY_DIVIDER_RATIO     5.70f
#define KR_FAST_TELEMETRY_MS         50
#define KR_FULL_TELEMETRY_MS         200

#define KR_OTA_URL "https://achinu.ir/ediags/firmware/firmware.bin"
#define KR_OTA_ALLOW_INSECURE 1

static const char KR_OTA_ROOT_CA[] PROGMEM = R"EOF(
)EOF";
