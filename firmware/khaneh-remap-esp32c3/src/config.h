#pragma once
#define FW_VERSION "0.9.0-prototype"
#define DEVICE_NAME_PREFIX "KHANEH_REMAP_CC"

static constexpr int PIN_OUT_CLEANER = 0;
static constexpr int PIN_OUT_HYDROGEN = 1;
static constexpr int PIN_OUT_VACUUM = 3;
static constexpr bool OUTPUT_ACTIVE_HIGH = true;

static constexpr int PIN_I2C_SDA = 4;
static constexpr int PIN_I2C_SCL = 5;
static constexpr int PIN_KLINE_RX = 6;
static constexpr int PIN_KLINE_TX = 7;
static constexpr int PIN_RGB_DATA = 10;
static constexpr int RGB_PIXEL_COUNT = 10;
static constexpr int RGB_BRIGHTNESS = 90;
static constexpr int PIN_HX_DOUT = 20;
static constexpr int PIN_HX_SCK = 21;
static constexpr int PIN_BUTTON_ADC = 2;
static constexpr int BTN_ADC_CLEANER_DEFAULT = 650;
static constexpr int BTN_ADC_H2_DEFAULT = 1750;
static constexpr int BTN_ADC_VAC_DEFAULT = 2850;
static constexpr int BTN_ADC_TOLERANCE = 300;
static constexpr int PIN_CAN_RX_FUTURE = 18;
static constexpr int PIN_CAN_TX_FUTURE = 19;

static constexpr float TILT_WARN_DEG = 8.0f;
static constexpr float TILT_BLOCK_DEG = 12.0f;
static constexpr uint32_t H2_ARM_WINDOW_MS = 30000;
static constexpr uint32_t H2_LONG_PRESS_MS = 1800;
static constexpr uint32_t STATUS_PERIOD_MS = 750;
static constexpr uint32_t OBD_POLL_PERIOD_MS = 150;

static constexpr const char* BLE_SERVICE_UUID = "7f640001-7c7d-4f0a-8b6f-4f484f4d4501";
static constexpr const char* BLE_CMD_UUID = "7f640002-7c7d-4f0a-8b6f-4f484f4d4501";
static constexpr const char* BLE_STATUS_UUID = "7f640003-7c7d-4f0a-8b6f-4f484f4d4501";
static constexpr const char* BLE_EVENT_UUID = "7f640004-7c7d-4f0a-8b6f-4f484f4d4501";
