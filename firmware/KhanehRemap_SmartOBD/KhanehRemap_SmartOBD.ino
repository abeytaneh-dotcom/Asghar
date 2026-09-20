/*
  Khaneh Remap Smart OBD - ESP32
  Target: ESP32-WROOM-32 / Arduino-ESP32 3.x
  CAN physical layer: SN65HVD230
  Water probe: stainless probe to GPIO27; coolant/chassis = GND
*/
#include <Arduino.h>
#include "BluetoothSerial.h"
#include "driver/twai.h"

BluetoothSerial SerialBT;

static constexpr gpio_num_t CAN_TX = GPIO_NUM_21;
static constexpr gpio_num_t CAN_RX = GPIO_NUM_22;
static constexpr uint8_t WATER_PIN = 27;

static constexpr uint32_t WATER_PERIOD_MS = 20000;
static constexpr uint8_t WATER_FAIL_LIMIT = 5;
static constexpr uint32_t PID_PERIOD_MS = 1000;

uint8_t waterFails = 0;
bool lowWaterAlarm = false;
uint32_t lastWater = 0, lastPid = 0;
bool twaiStarted = false;
int canRate = 0;

void btLine(const String &s) {
  Serial.println(s);
  if (SerialBT.hasClient()) SerialBT.println(s);
}

bool installCAN(int kbps) {
  if (twaiStarted) {
    twai_stop();
    twai_driver_uninstall();
    twaiStarted = false;
  }
  twai_general_config_t g = TWAI_GENERAL_CONFIG_DEFAULT(CAN_TX, CAN_RX, TWAI_MODE_NORMAL);
  g.tx_queue_len = 10;
  g.rx_queue_len = 20;
  g.alerts_enabled = TWAI_ALERT_NONE;
  twai_timing_config_t t;\n  if (kbps == 250) {\n    twai_timing_config_t cfg = TWAI_TIMING_CONFIG_250KBITS();\n    t = cfg;\n  } else {\n    twai_timing_config_t cfg = TWAI_TIMING_CONFIG_500KBITS();\n    t = cfg;\n  }
  twai_filter_config_t f = TWAI_FILTER_CONFIG_ACCEPT_ALL();
  if (twai_driver_install(&g, &t, &f) != ESP_OK) return false;
  if (twai_start() != ESP_OK) {
    twai_driver_uninstall();
    return false;
  }
  twaiStarted = true;
  canRate = kbps;
  return true;
}

bool sendOBD(uint8_t mode, uint8_t pid) {
  if (!twaiStarted) return false;
  twai_message_t m = {};
  m.identifier = 0x7DF;
  m.data_length_code = 8;
  m.data[0] = 0x02; m.data[1] = mode; m.data[2] = pid;
  for (int i=3;i<8;i++) m.data[i]=0x55;
  return twai_transmit(&m, pdMS_TO_TICKS(50)) == ESP_OK;
}

bool waitOBD(uint8_t mode, uint8_t pid, twai_message_t &r, uint32_t timeoutMs=180) {
  uint32_t start=millis();
  while (millis()-start < timeoutMs) {
    if (twai_receive(&r, pdMS_TO_TICKS(20)) == ESP_OK) {
      if (!r.extd && r.identifier >= 0x7E8 && r.identifier <= 0x7EF &&
          r.data_length_code >= 4 && r.data[1] == (uint8_t)(mode+0x40) && r.data[2] == pid) return true;
    }
  }
  return false;
}

bool queryPID(uint8_t pid, twai_message_t &r) {
  while (twai_receive(&r, 0) == ESP_OK) {}
  if (!sendOBD(0x01,pid)) return false;
  return waitOBD(0x01,pid,r);
}

bool detectCAN() {
  twai_message_t r;
  const int rates[2]={500,250};
  for (int rate: rates) {
    if (!installCAN(rate)) continue;
    for (int n=0;n<2;n++) {
      if (queryPID(0x00,r)) {
        btLine("ECU:CONNECTED,CAN:" + String(rate));
        return true;
      }
      delay(80);
    }
  }
  btLine("ECU:NOT_FOUND");
  return false;
}

bool waterPresentSample() {
  pinMode(WATER_PIN, INPUT_PULLUP);
  delay(5);
  uint8_t wet=0, dry=0;
  for (int i=0;i<9;i++) {
    if (digitalRead(WATER_PIN)==LOW) wet++; else dry++;
    delay(5);
  }
  pinMode(WATER_PIN, INPUT); // high impedance between measurements
  return wet > dry;
}

void checkWater() {
  bool present=waterPresentSample();
  if (present) {
    waterFails=0;
    if (lowWaterAlarm) {
      lowWaterAlarm=false;
      btLine("COOLANT:OK");
    }
  } else {
    if (waterFails < WATER_FAIL_LIMIT) waterFails++;
    btLine("COOLANT:CHECK," + String(waterFails) + "/" + String(WATER_FAIL_LIMIT));
    if (waterFails >= WATER_FAIL_LIMIT && !lowWaterAlarm) {
      lowWaterAlarm=true;
      btLine("ALARM:LOW_COOLANT");
    }
  }
}

void sendLiveData() {
  if (!twaiStarted) return;
  twai_message_t r;
  if (queryPID(0x0C,r) && r.data_length_code>=5) {
    float rpm=((256.0f*r.data[3])+r.data[4])/4.0f;
    btLine("RPM:"+String(rpm,0));
  }
  if (queryPID(0x0D,r) && r.data_length_code>=4) btLine("SPEED:"+String(r.data[3]));
  if (queryPID(0x05,r) && r.data_length_code>=4) btLine("ECT:"+String((int)r.data[3]-40));
}

void clearDTC() {
  if (!twaiStarted) { btLine("CLEAR_DTC:NO_CAN"); return; }
  twai_message_t m={};
  m.identifier=0x7DF; m.data_length_code=8;
  m.data[0]=0x01; m.data[1]=0x04;
  for(int i=2;i<8;i++) m.data[i]=0x55;
  if (twai_transmit(&m,pdMS_TO_TICKS(50))==ESP_OK) btLine("CLEAR_DTC:SENT");
  else btLine("CLEAR_DTC:FAILED");
}

void handleCommand(String c) {
  c.trim(); c.toUpperCase();
  if (c=="PING") btLine("PONG");
  else if (c=="STATUS") btLine("STATUS,CAN:"+String(canRate)+",COOLANT:"+(lowWaterAlarm?String("LOW"):String("OK")));
  else if (c=="CLEAR_DTC") clearDTC();
  else if (c=="REDETECT") detectCAN();
}

void setup() {
  Serial.begin(115200);
  pinMode(WATER_PIN, INPUT);
  SerialBT.begin("KhanehRemap-OBD");
  btLine("BOOT:SMART_OBD_V2");
  checkWater();
  detectCAN();
  lastWater=millis();
  lastPid=millis();
}

void loop() {
  if (SerialBT.available()) handleCommand(SerialBT.readStringUntil('\n'));
  uint32_t now=millis();
  if (now-lastWater >= WATER_PERIOD_MS) { lastWater=now; checkWater(); }
  if (now-lastPid >= PID_PERIOD_MS) { lastPid=now; sendLiveData(); }
  delay(2);
}
