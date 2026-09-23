#include <Arduino.h>
#include <NimBLEDevice.h>
#include "driver/twai.h"

// Khaneh Remap SMART OBD - ESP32-C3 TEST firmware
// Prototype only: no Secure Boot / Flash Encryption yet.

static constexpr const char* FW_VERSION = "1.0.0-test-c3";
static constexpr const char* DEVICE_NAME = "KhanehRemap-OBD-C3";

static constexpr gpio_num_t CAN_TX = GPIO_NUM_4;
static constexpr gpio_num_t CAN_RX = GPIO_NUM_5;
static constexpr int KLINE_RX = 6;
static constexpr int KLINE_TX = 7;
static constexpr int COOLANT_PIN = 3;

static constexpr const char* BLE_SERVICE_UUID = "7f640101-7c7d-4f0a-8b6f-4f484f4d4501";
static constexpr const char* BLE_CMD_UUID     = "7f640102-7c7d-4f0a-8b6f-4f484f4d4501";
static constexpr const char* BLE_DATA_UUID    = "7f640103-7c7d-4f0a-8b6f-4f484f4d4501";

NimBLECharacteristic* dataCh = nullptr;
bool canStarted = false;
int canRate = 0;
bool klineConnected = false;
uint32_t lastPid = 0;
uint32_t lastCoolant = 0;
uint8_t dryCount = 0;
bool lowCoolant = false;
HardwareSerial KLine(1);

static void sendLine(const String& s) {
  Serial.println(s);
  if (dataCh) {
    dataCh->setValue(s.c_str());
    dataCh->notify();
  }
}

static bool startCAN(int kbps) {
  if (canStarted) {
    twai_stop();
    twai_driver_uninstall();
    canStarted = false;
  }
  auto g = TWAI_GENERAL_CONFIG_DEFAULT(CAN_TX, CAN_RX, TWAI_MODE_NORMAL);
  g.tx_queue_len = 10;
  g.rx_queue_len = 20;
  twai_timing_config_t t = (kbps == 250) ? TWAI_TIMING_CONFIG_250KBITS() : TWAI_TIMING_CONFIG_500KBITS();
  auto f = TWAI_FILTER_CONFIG_ACCEPT_ALL();
  if (twai_driver_install(&g, &t, &f) != ESP_OK) return false;
  if (twai_start() != ESP_OK) {
    twai_driver_uninstall();
    return false;
  }
  canStarted = true;
  canRate = kbps;
  return true;
}

static void flushCAN() {
  twai_message_t m;
  while (twai_receive(&m, 0) == ESP_OK) {}
}

static bool canRequest(uint8_t mode, uint8_t pid, twai_message_t& out, uint32_t timeout=220) {
  if (!canStarted) return false;
  flushCAN();
  twai_message_t m = {};
  m.identifier = 0x7DF;
  m.data_length_code = 8;
  m.data[0] = 0x02;
  m.data[1] = mode;
  m.data[2] = pid;
  for (int i=3;i<8;i++) m.data[i] = 0x55;
  if (twai_transmit(&m, pdMS_TO_TICKS(50)) != ESP_OK) return false;
  uint32_t start = millis();
  while (millis() - start < timeout) {
    if (twai_receive(&out, pdMS_TO_TICKS(20)) == ESP_OK) {
      if (!out.extd && out.identifier >= 0x7E8 && out.identifier <= 0x7EF &&
          out.data_length_code >= 4 && out.data[1] == uint8_t(mode + 0x40) && out.data[2] == pid) return true;
    }
  }
  return false;
}

static bool detectCAN() {
  twai_message_t r;
  const int rates[] = {500,250};
  for (int rate : rates) {
    if (!startCAN(rate)) continue;
    for (int n=0;n<2;n++) {
      if (canRequest(0x01,0x00,r,250)) {
        sendLine("ECU:CONNECTED,CAN:" + String(rate));
        return true;
      }
      delay(80);
    }
  }
  if (canStarted) {
    twai_stop();
    twai_driver_uninstall();
    canStarted = false;
  }
  canRate = 0;
  return false;
}

static uint8_t ksum(const uint8_t* d, size_t n) {
  uint16_t s=0; for(size_t i=0;i<n;i++) s+=d[i]; return uint8_t(s);
}

static void klineIdle() {
  KLine.end();
  pinMode(KLINE_TX, OUTPUT);
  digitalWrite(KLINE_TX, HIGH);
  delay(300);
}

static void send5Baud(uint8_t b) {
  pinMode(KLINE_TX, OUTPUT);
  digitalWrite(KLINE_TX, LOW); delay(200);
  for (int i=0;i<8;i++) { digitalWrite(KLINE_TX, (b>>i)&1); delay(200); }
  digitalWrite(KLINE_TX, HIGH); delay(200);
}

static bool klineFastInit() {
  klineIdle();
  digitalWrite(KLINE_TX, LOW); delay(25);
  digitalWrite(KLINE_TX, HIGH); delay(25);
  KLine.begin(10400, SERIAL_8N1, KLINE_RX, KLINE_TX);
  while(KLine.available()) KLine.read();
  uint8_t req[5]={0xC1,0x33,0xF1,0x81,0}; req[4]=ksum(req,4);
  KLine.write(req,5); KLine.flush();
  uint8_t r[32]; size_t n=0; uint32_t t=millis(), last=t;
  while(millis()-t<400) {
    while(KLine.available() && n<sizeof(r)) { r[n++]=KLine.read(); last=millis(); }
    if(n && millis()-last>35) break;
    delay(1);
  }
  for(size_t i=0;i<n;i++) if(r[i]==0xC1 || r[i]==0x81) return true;
  KLine.end();
  return false;
}

static bool kline5BaudInit() {
  klineIdle();
  send5Baud(0x33);
  KLine.begin(10400, SERIAL_8N1, KLINE_RX, KLINE_TX);
  uint8_t b[3]={0}; int got=0; uint32_t t=millis();
  while(millis()-t<1800 && got<3) if(KLine.available()) b[got++]=KLine.read();
  if(got<3 || b[0]!=0x55) { KLine.end(); return false; }
  delay(25); KLine.write(uint8_t(~b[2])); KLine.flush(); delay(60);
  while(KLine.available()) KLine.read();
  return true;
}

static bool detectKLine() {
  klineConnected = klineFastInit();
  if (!klineConnected) klineConnected = kline5BaudInit();
  if (klineConnected) sendLine("ECU:CONNECTED,KLINE:10400");
  else sendLine("ECU:NOT_FOUND");
  return klineConnected;
}

static bool klinePid(uint8_t pid, uint8_t* payload, size_t& len) {
  if (!klineConnected) return false;
  while(KLine.available()) KLine.read();
  uint8_t req[6]={0x68,0x6A,0xF1,0x01,pid,0}; req[5]=ksum(req,5);
  KLine.write(req,6); KLine.flush();
  uint8_t r[64]; size_t n=0; uint32_t t=millis(), last=t;
  while(millis()-t<260) {
    while(KLine.available() && n<sizeof(r)) { r[n++]=KLine.read(); last=millis(); }
    if(n && millis()-last>28) break;
    delay(1);
  }
  for(size_t i=0;i+2<n;i++) {
    if(r[i]==0x41 && r[i+1]==pid) {
      len=0;
      for(size_t j=i+2;j<n && len<4;j++) payload[len++]=r[j];
      return len>0;
    }
  }
  return false;
}

static void detectProtocol() {
  sendLine("DETECT:START");
  if (detectCAN()) return;
  detectKLine();
}

static String dtcText(uint8_t a, uint8_t b) {
  const char pfx[]={'P','C','B','U'};
  String s;
  s += pfx[(a>>6)&3];
  s += char('0'+((a>>4)&3));
  const char* hex="0123456789ABCDEF";
  s += hex[a&0x0F];
  s += hex[(b>>4)&0x0F];
  s += hex[b&0x0F];
  return s;
}

static void readDTC_CAN() {
  if (!canStarted) { sendLine("DTC:NO_CAN"); return; }
  flushCAN();
  twai_message_t m={};
  m.identifier=0x7DF; m.data_length_code=8;
  m.data[0]=0x01; m.data[1]=0x03;
  for(int i=2;i<8;i++) m.data[i]=0x55;
  if(twai_transmit(&m,pdMS_TO_TICKS(50))!=ESP_OK) { sendLine("DTC:TX_FAIL"); return; }
  uint32_t t=millis(); bool any=false;
  while(millis()-t<450) {
    twai_message_t r;
    if(twai_receive(&r,pdMS_TO_TICKS(30))==ESP_OK && !r.extd &&
       r.identifier>=0x7E8 && r.identifier<=0x7EF && r.data_length_code>=3 && r.data[1]==0x43) {
      any=true;
      int bytes = min<int>(r.data[0]-1, 6);
      String line="DTC:";
      bool found=false;
      for(int i=0;i+1<bytes;i+=2) {
        uint8_t a=r.data[2+i], b=r.data[3+i];
        if(a==0 && b==0) continue;
        if(found) line += ",";
        line += dtcText(a,b);
        found=true;
      }
      sendLine(found?line:"DTC:NONE");
    }
  }
  if(!any) sendLine("DTC:NO_RESPONSE");
}

static void clearDTC_CAN() {
  if (!canStarted) { sendLine("CLEAR_DTC:NO_CAN"); return; }
  twai_message_t m={};
  m.identifier=0x7DF; m.data_length_code=8;
  m.data[0]=0x01; m.data[1]=0x04;
  for(int i=2;i<8;i++) m.data[i]=0x55;
  sendLine(twai_transmit(&m,pdMS_TO_TICKS(50))==ESP_OK ? "CLEAR_DTC:SENT" : "CLEAR_DTC:FAILED");
}

static void sampleCoolant() {
  pinMode(COOLANT_PIN, INPUT_PULLUP);
  delay(5);
  int wet=0,dry=0;
  for(int i=0;i<9;i++) { digitalRead(COOLANT_PIN)==LOW ? wet++ : dry++; delay(4); }
  pinMode(COOLANT_PIN, INPUT);
  if(wet>dry) {
    dryCount=0;
    if(lowCoolant) { lowCoolant=false; sendLine("COOLANT:OK"); }
  } else {
    if(dryCount<5) dryCount++;
    sendLine("COOLANT:CHECK,"+String(dryCount)+"/5");
    if(dryCount>=5 && !lowCoolant) { lowCoolant=true; sendLine("ALARM:LOW_COOLANT"); }
  }
}

static void sendPidData() {
  if(canStarted) {
    twai_message_t r;
    if(canRequest(0x01,0x0C,r) && r.data_length_code>=5) {
      int rpm=((256*r.data[3])+r.data[4])/4; sendLine("RPM:"+String(rpm));
    }
    if(canRequest(0x01,0x0D,r) && r.data_length_code>=4) sendLine("SPEED:"+String(r.data[3]));
    if(canRequest(0x01,0x05,r) && r.data_length_code>=4) sendLine("ECT:"+String((int)r.data[3]-40));
    if(canRequest(0x01,0x2F,r) && r.data_length_code>=4) sendLine("FUEL:"+String((100.0f*r.data[3])/255.0f,1));
    if(canRequest(0x01,0x42,r) && r.data_length_code>=5) {
      float v=((256*r.data[3])+r.data[4])/1000.0f; sendLine("VOLT:"+String(v,2));
    }
    return;
  }
  if(klineConnected) {
    uint8_t p[4]; size_t n=0;
    if(klinePid(0x0C,p,n) && n>=2) sendLine("RPM:"+String(((256*p[0])+p[1])/4));
    if(klinePid(0x0D,p,n) && n>=1) sendLine("SPEED:"+String(p[0]));
    if(klinePid(0x05,p,n) && n>=1) sendLine("ECT:"+String((int)p[0]-40));
  }
}

static void handleCommand(String c) {
  c.trim(); c.toUpperCase();
  if(c=="PING") sendLine("PONG");
  else if(c=="STATUS") {
    String proto = canStarted ? ("CAN"+String(canRate)) : (klineConnected ? "KLINE" : "NONE");
    sendLine("STATUS,FW:"+String(FW_VERSION)+",PROTO:"+proto+",COOLANT:"+(lowCoolant?String("LOW"):String("OK")));
  }
  else if(c=="REDETECT") detectProtocol();
  else if(c=="READ_DTC") readDTC_CAN();
  else if(c=="CLEAR_DTC") clearDTC_CAN();
}

class CmdCallbacks : public NimBLECharacteristicCallbacks {
  void onWrite(NimBLECharacteristic* ch) override {
    std::string v=ch->getValue();
    handleCommand(String(v.c_str()));
  }
};

static void startBLE() {
  NimBLEDevice::init(DEVICE_NAME);
  NimBLEServer* server=NimBLEDevice::createServer();
  NimBLEService* service=server->createService(BLE_SERVICE_UUID);
  NimBLECharacteristic* cmd=service->createCharacteristic(BLE_CMD_UUID, NIMBLE_PROPERTY::WRITE | NIMBLE_PROPERTY::WRITE_NR);
  dataCh=service->createCharacteristic(BLE_DATA_UUID, NIMBLE_PROPERTY::READ | NIMBLE_PROPERTY::NOTIFY);
  cmd->setCallbacks(new CmdCallbacks());
  dataCh->setValue("BOOT:SMART_OBD_C3");
  service->start();
  NimBLEAdvertising* adv=NimBLEDevice::getAdvertising();
  adv->addServiceUUID(BLE_SERVICE_UUID);
  adv->setScanResponse(true);
  NimBLEDevice::startAdvertising();
}

void setup() {
  Serial.begin(115200);
  delay(250);
  pinMode(COOLANT_PIN, INPUT);
  pinMode(KLINE_TX, OUTPUT); digitalWrite(KLINE_TX,HIGH);
  startBLE();
  sendLine("BOOT:SMART_OBD_C3");
  sampleCoolant();
  detectProtocol();
  lastPid=millis();
  lastCoolant=millis();
}

void loop() {
  uint32_t now=millis();
  if(now-lastPid>=1000) { lastPid=now; sendPidData(); }
  if(now-lastCoolant>=20000) { lastCoolant=now; sampleCoolant(); }
  delay(2);
}
