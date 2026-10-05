#pragma once
#include <Arduino.h>

/*
  Existing KhanehRemap PCB mapping. Verify against the physical schematic.
*/
#define KR_CAN_TX_PIN            5
#define KR_CAN_RX_PIN            6
#define KR_KLINE_TX_PIN          10
#define KR_KLINE_RX_PIN          7
#define KR_WATER_EXCITE_PIN      1
#define KR_WATER_SENSE_PIN       0
#define KR_BATTERY_SENSE_PIN     3
// No spare buzzer pin is confirmed. Never reuse the K-Line TX pin.
#ifndef KR_BUZZER_PIN
#define KR_BUZZER_PIN            -1
#endif
#ifndef KR_HARDWARE_VERIFIED
#define KR_HARDWARE_VERIFIED     0
#endif
#ifndef KR_ENABLE_MUTATING_COMMANDS
#define KR_ENABLE_MUTATING_COMMANDS 0
#endif
#ifndef KR_KLINE_TX_INVERTED
#define KR_KLINE_TX_INVERTED     0
#endif
#define KR_KLINE_ECU_ADDRESS     0x10
#define KR_COMMAND_MAX_BYTES     512
#define KR_FRAGMENT_TIMEOUT_MS   2000
#define KR_OBD_POLL_MS           100

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
// OTA remains disabled until a trusted signed-image deployment is provided.
#define KR_OTA_ENABLED 0
#define KR_OTA_ALLOW_INSECURE 0

static const char KR_OTA_ROOT_CA[] PROGMEM = R"EOF(
)EOF";

static_assert(KR_CAN_TX_PIN != KR_CAN_RX_PIN, "CAN pin conflict");
static_assert(KR_KLINE_TX_PIN != KR_KLINE_RX_PIN, "K-Line pin conflict");
static_assert(KR_BUZZER_PIN < 0 ||
              (KR_BUZZER_PIN != KR_CAN_TX_PIN && KR_BUZZER_PIN != KR_CAN_RX_PIN &&
               KR_BUZZER_PIN != KR_KLINE_TX_PIN && KR_BUZZER_PIN != KR_KLINE_RX_PIN &&
               KR_BUZZER_PIN != KR_WATER_EXCITE_PIN && KR_BUZZER_PIN != KR_WATER_SENSE_PIN &&
               KR_BUZZER_PIN != KR_BATTERY_SENSE_PIN), "Buzzer pin conflict");
