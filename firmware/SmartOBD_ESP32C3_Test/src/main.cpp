#include <Arduino.h>
#include <NimBLEDevice.h>
#include "driver/twai.h"
#include <Preferences.h>

// Khaneh Remap SMART OBD - ESP32-C3 TEST firmware
// Prototype only: no Secure Boot / Flash Encryption yet.

static constexpr const char* FW_VERSION = "1.0.0-test-c3";
static constexpr const char* DEVICE_NAME_PREFIX = "KhanehRemap-OBD-C3";
String bleDeviceName = "";

static constexpr gpio_num_t CAN_TX = GPIO_NUM_4;
static constexpr gpio_num_t CAN_RX = GPIO_NUM_5;
static constexpr int KLINE_RX = 6;
static constexpr int KLINE_TX = 7;
static constexpr int COOLANT_PIN = 3;

static constexpr const char* BLE_SERVICE_UUID = "7f640101-7c7d-4f0a-8b6f-4f484f4d4501";
static constexpr const char* BLE_CMD_UUID     = "7f640102-7c7d-4f0a-8b6f-4f484f4d4501";
static constexpr const char* BLE_DATA_UUID    = "7f640103-7c7d-4f0a-8b6f-4f484f4d4501";

// CarLab v1.2 compatibility service. The simulator currently uses these UUIDs.
static constexpr const char* SIM_SERVICE_UUID = "7f640001-7c7d-4f0a-8b6f-4f484f4d4501";
static constexpr const char* SIM_CMD_UUID     = "7f640002-7c7d-4f0a-8b6f-4f484f4d4501";
static constexpr const char* SIM_STATUS_UUID  = "7f640003-7c7d-4f0a-8b6f-4f484f4d4501";
static constexpr const char* SIM_EVENT_UUID   = "7f640004-7c7d-4f0a-8b6f-4f484f4d4501";

NimBLECharacteristic* dataCh = nullptr;
NimBLECharacteristic* simStatusCh = nullptr;
NimBLECharacteristic* simEventCh = nullptr;
NimBLEServer* bleServer = nullptr;

struct BleCommand { char text[96]; };
QueueHandle_t bleCmdQueue = nullptr;
volatile bool bleClientConnected = false;

bool canStarted = false;
int canRate = 0;
bool klineConnected = false;
uint32_t lastPid = 0;
uint32_t lastCoolant = 0;
uint8_t dryCount = 0;
bool lowCoolant = false;
HardwareSerial KLine(1);
Preferences prefs;
String deviceSerial = "";

// Bench simulator state. These values are populated by KhanehRemap-CarLab.html
// using short SIM|... commands over BLE or native USB Serial.
bool simMode = false;
String simProfile = "CAN_OBD2";
int simCanRate = 500;
float simRpm = 820, simSpeed = 0, simEct = 74, simFuel = 55, simVbat = 13.9;
bool simIgnition = true, simEngine = true;
String simDtc = "";

static void sendLine(const String& line) {
  Serial.println(line);
  if (dataCh) {
    dataCh->setValue(line.c_str());
    if (bleClientConnected) dataCh->notify();
  }
  if (simStatusCh) {
    simStatusCh->setValue(line.c_str());
    if (bleClientConnected) simStatusCh->notify();
  }
}

static bool startCAN(int kbps) {
  if (canStarted) {
    twai_stop();
    twai_driver_uninstall();
    canStarted = false;
  }
  twai_general_config_t g = TWAI_GENERAL_CONFIG_DEFAULT(CAN_TX, CAN_RX, TWAI_MODE_NORMAL);
  g.tx_queue_len = 10;
  g.rx_queue_len = 20;
  twai_timing_config_t t;
  if (kbps == 250) {
    twai_timing_config_t t250 = TWAI_TIMING_CONFIG_250KBITS();
    t = t250;
  } else {
    twai_timing_config_t t500 = TWAI_TIMING_CONFIG_500KBITS();
    t = t500;
  }
  twai_filter_config_t f = TWAI_FILTER_CONFIG_ACCEPT_ALL();
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
    sendLine("ECU:SCAN,CAN:" + String(rate));
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
  sendLine("ECU:SCAN,KLINE:FAST");
  klineConnected = klineFastInit();
  if (!klineConnected) {
    sendLine("ECU:SCAN,KLINE:5BAUD");
    klineConnected = kline5BaudInit();
  }
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

static bool validSerial(const String& v) {
  if (v.length() < 8 || v.length() > 32) return false;
  for (size_t i=0;i<v.length();i++) {
    char ch=v[i];
    if (!(isalnum((unsigned char)ch) || ch=='-' || ch=='_')) return false;
  }
  return true;
}


static String simProtocol() {
  String p=simProfile; p.toUpperCase();
  if (p=="CAN_OBD2") return "CAN"+String(simCanRate);
  if (p=="SSAT_GENERIC") {
    // SSAT profile in CarLab can represent either transport; when CAN rate is
    // explicitly selected on the simulator we expose it as CAN for bench use.
    return "CAN"+String(simCanRate);
  }
  return "KLINE";
}

static void publishSimTelemetry() {
  sendLine("RPM:"+String((int)simRpm));
  sendLine("SPEED:"+String((int)simSpeed));
  sendLine("ECT:"+String((int)simEct));
  sendLine("FUEL:"+String(simFuel,1));
  sendLine("VOLT:"+String(simVbat,2));
}

static void simDetect() {
  if (!simIgnition) {
    sendLine("ECU:NOT_FOUND");
    return;
  }
  String proto=simProtocol();
  if (proto.startsWith("CAN")) sendLine("ECU:CONNECTED,CAN:"+String(simCanRate));
  else sendLine("ECU:CONNECTED,KLINE:10400");
  publishSimTelemetry();
}

static void handleSimCommand(const String& original) {
  String c=original; c.trim();
  String upper=c; upper.toUpperCase();

  if (upper=="HELLO|CARLAB|1") {
    sendLine("SIM:HELLO,SMART_OBD_C3");
    return;
  }
  if (upper=="SIM|MODE|ON") {
    simMode=true;
    sendLine("SIM:MODE,ON");
    return;
  }
  if (upper=="SIM|MODE|OFF") {
    simMode=false;
    sendLine("SIM:MODE,OFF");
    return;
  }
  if (!upper.startsWith("SIM|")) return;

  int p1=c.indexOf('|');
  int p2=c.indexOf('|',p1+1);
  int p3=c.indexOf('|',p2+1);
  String group=(p2>p1)?c.substring(p1+1,p2):"";
  String key=(p3>p2)?c.substring(p2+1,p3):((p2>=0)?c.substring(p2+1):"");
  String val=(p3>=0)?c.substring(p3+1):"";
  group.toUpperCase(); key.toUpperCase();

  if (group=="ECU") {
    simProfile=key.length()?key:val;
    simProfile.toUpperCase();
    sendLine("SIM:ECU,"+simProfile);
  } else if (group=="CANRATE") {
    String rate=key.length()?key:val;
    simCanRate=rate.toInt();
    if (simCanRate<=0) simCanRate=500;
    sendLine("SIM:CANRATE,"+String(simCanRate));
  } else if (group=="LIVE") {
    // Compact live snapshot from CarLab:
    // SIM|LIVE|rpm|speed|ect|fuel|vbat
    int a1=c.indexOf('|',4);
    int a2=c.indexOf('|',a1+1);
    int a3=c.indexOf('|',a2+1);
    int a4=c.indexOf('|',a3+1);
    int a5=c.indexOf('|',a4+1);
    if(a1>0&&a2>0&&a3>0&&a4>0&&a5>0){
      simRpm=c.substring(a1+1,a2).toFloat();
      simSpeed=c.substring(a2+1,a3).toFloat();
      simEct=c.substring(a3+1,a4).toFloat();
      simFuel=c.substring(a4+1,a5).toFloat();
      simVbat=c.substring(a5+1).toFloat();
      publishSimTelemetry();
    }
  } else if (group=="SET") {
    float n=val.toFloat();
    if (key=="RPM") { simRpm=n; sendLine("RPM:"+String((int)simRpm)); }
    else if (key=="SPEED") { simSpeed=n; sendLine("SPEED:"+String((int)simSpeed)); }
    else if (key=="ECT") { simEct=n; sendLine("ECT:"+String((int)simEct)); }
    else if (key=="FUEL") { simFuel=n; sendLine("FUEL:"+String(simFuel,1)); }
    else if (key=="VBAT") { simVbat=n; sendLine("VOLT:"+String(simVbat,2)); }
  } else if (group=="ACT") {
    bool on=val.toInt()!=0;
    if (key=="IGNITION") simIgnition=on;
    else if (key=="ENGINE") simEngine=on;
  } else if (group=="DTC") {
    if (key=="ADD") simDtc=val;
    else if (key=="CLEAR") simDtc="";
  } else if (group=="CAN" && key=="SAMPLE") {
    publishSimTelemetry();
  } else if (group=="HEARTBEAT") {
    sendLine("SIM:HEARTBEAT,OK");
    if (simMode) publishSimTelemetry();
  }
}


static void stopPhysicalBus() {
  if (canStarted) {
    twai_stop();
    twai_driver_uninstall();
    canStarted=false;
    canRate=0;
  }
  if (klineConnected) {
    KLine.end();
    klineConnected=false;
  }
}

static bool detectCANManual(int rate, const String& label) {
  stopPhysicalBus();
  sendLine("ECU:SCAN,MANUAL:" + label + ",CAN:" + String(rate));
  if (!startCAN(rate)) {
    sendLine("ECU:NOT_FOUND");
    return false;
  }
  twai_message_t r;
  for (int n=0;n<3;n++) {
    if (canRequest(0x01,0x00,r,300)) {
      sendLine("ECU:CONNECTED,CAN:" + String(rate) + ",MODEL:" + label);
      return true;
    }
    delay(80);
  }
  twai_stop();
  twai_driver_uninstall();
  canStarted=false;
  canRate=0;
  sendLine("ECU:NOT_FOUND");
  return false;
}

static bool detectKLineManual(bool fastOnly, bool fiveOnly, const String& label) {
  stopPhysicalBus();
  bool ok=false;
  if (!fiveOnly) {
    sendLine("ECU:SCAN,MANUAL:" + label + ",KLINE:FAST");
    ok=klineFastInit();
  }
  if (!ok && !fastOnly) {
    sendLine("ECU:SCAN,MANUAL:" + label + ",KLINE:5BAUD");
    ok=kline5BaudInit();
  }
  klineConnected=ok;
  if (ok) sendLine("ECU:CONNECTED,KLINE:10400,MODEL:" + label);
  else sendLine("ECU:NOT_FOUND");
  return ok;
}

static void detectManualProfile(String profile) {
  profile.trim();
  profile.toUpperCase();

  if (simMode) {
    // Keep CarLab and the phone/web selector in sync.
    if (profile=="SSAT_CAN500") { simProfile="SSAT_GENERIC"; simCanRate=500; }
    else if (profile=="SSAT_CAN250") { simProfile="SSAT_GENERIC"; simCanRate=250; }
    else if (profile=="CAN500") { simProfile="CAN_OBD2"; simCanRate=500; }
    else if (profile=="CAN250") { simProfile="CAN_OBD2"; simCanRate=250; }
    else simProfile=profile;
    simDetect();
    return;
  }

  if (profile=="CAN500" || profile=="GENERIC_CAN500") { detectCANManual(500,"Generic CAN OBD-II"); return; }
  if (profile=="CAN250" || profile=="GENERIC_CAN250") { detectCANManual(250,"Generic CAN OBD-II"); return; }
  if (profile=="SSAT_CAN500") { detectCANManual(500,"SSAT"); return; }
  if (profile=="SSAT_CAN250") { detectCANManual(250,"SSAT"); return; }
  if (profile=="KLINE_FAST") { detectKLineManual(true,false,"K-Line Fast"); return; }
  if (profile=="KLINE_5BAUD") { detectKLineManual(false,true,"ISO9141 5-Baud"); return; }
  if (profile=="SSAT_KLINE") { detectKLineManual(false,false,"SSAT"); return; }

  if (profile=="VALEO_S2000" || profile=="SAGEM_S2000") {
    detectKLineManual(false,true,profile);
    return;
  }
  if (profile=="VALEO_J34P" || profile=="SIEMENS_EMS3132") {
    detectKLineManual(true,false,profile);
    return;
  }
  if (profile=="BOSCH_ME744" || profile=="BOSCH_ME745" || profile=="BOSCH_ME749" || profile=="EASYU_SAIPA") {
    detectKLineManual(false,false,profile);
    return;
  }

  sendLine("ECU:NOT_FOUND");
}

static void handleCommand(String c) {
  c.trim();
  String upper=c; upper.toUpperCase();

  if(upper=="PING") sendLine("PONG");
  else if(upper=="HELLO|CARLAB|1" || upper.startsWith("SIM|")) handleSimCommand(c);
  else if(upper=="GET_SERIAL") {
    sendLine(deviceSerial.length() ? ("SERIAL:"+deviceSerial) : "SERIAL:UNSET");
  }
  else if(upper.startsWith("SET_SERIAL:")) {
    String candidate=c.substring(c.indexOf(':')+1);
    candidate.trim(); candidate.toUpperCase();
    if (!validSerial(candidate)) {
      sendLine("SERIAL:INVALID");
    } else if (deviceSerial.length() && deviceSerial != candidate) {
      sendLine("SERIAL:LOCKED");
    } else {
      prefs.begin("khanehremap", false);
      prefs.putString("serial", candidate);
      prefs.end();
      deviceSerial=candidate;
      sendLine("SERIAL:SET,"+deviceSerial);
    }
  }
  else if(upper=="STATUS") {
    String proto = simMode ? simProtocol() : (canStarted ? ("CAN"+String(canRate)) : (klineConnected ? "KLINE" : "NONE"));
    sendLine("STATUS,FW:"+String(FW_VERSION)+",PROTO:"+proto+",COOLANT:"+(lowCoolant?String("LOW"):String("OK"))+",SIM:"+(simMode?String("1"):String("0")));
    if (simMode) publishSimTelemetry();
  }
  else if(upper=="REDETECT") {
    if (simMode) simDetect();
    else detectProtocol();
  }
  else if(upper.startsWith("ECU_SELECT:")) {
    String profile=c.substring(c.indexOf(':')+1);
    detectManualProfile(profile);
  }
  else if(upper=="READ_DTC") {
    if (simMode) sendLine(simDtc.length()?("DTC:"+simDtc):"DTC:NONE");
    else readDTC_CAN();
  }
  else if(upper=="CLEAR_DTC") {
    if (simMode) { simDtc=""; sendLine("CLEAR_DTC:SENT"); }
    else clearDTC_CAN();
  }
}

class ServerCallbacks : public NimBLEServerCallbacks {
  void onConnect(NimBLEServer* server) override {
    bleClientConnected = true;
    // Put the serial in the readable characteristic immediately. This makes
    // activation robust even if the first notification is missed by Android.
    if (dataCh) {
      String id = deviceSerial.length() ? ("SERIAL:" + deviceSerial) : String("SERIAL:UNSET");
      dataCh->setValue(id.c_str());
    }
  }

  void onDisconnect(NimBLEServer* server) override {
    bleClientConnected = false;
    delay(20);
    NimBLEDevice::startAdvertising();
  }
};

class CmdCallbacks : public NimBLECharacteristicCallbacks {
  void onWrite(NimBLECharacteristic* ch) override {
    // IMPORTANT: never run CAN/K-Line detection inside the NimBLE callback.
    // REDETECT can take seconds (especially 5-baud K-Line init) and doing it
    // here can starve the BLE host task and drop the GATT connection.
    std::string v = ch->getValue();
    if (v.empty() || !bleCmdQueue) return;
    BleCommand m{};
    size_t n = v.size();
    if (n >= sizeof(m.text)) n = sizeof(m.text) - 1;
    memcpy(m.text, v.data(), n);
    m.text[n] = 0;
    xQueueSend(bleCmdQueue, &m, 0);
  }
};

static String hardwareSuffix() {
  uint64_t mac = ESP.getEfuseMac();
  char b[7];
  snprintf(b, sizeof(b), "%06llX", (unsigned long long)(mac & 0xFFFFFFULL));
  return String(b);
}

static void startBLE() {
  bleDeviceName = String(DEVICE_NAME_PREFIX) + "_" + hardwareSuffix();
  NimBLEDevice::init(bleDeviceName.c_str());
  NimBLEDevice::setPower(ESP_PWR_LVL_P9);
  bleServer=NimBLEDevice::createServer();
  bleServer->setCallbacks(new ServerCallbacks());
  NimBLEService* service=bleServer->createService(BLE_SERVICE_UUID);
  NimBLECharacteristic* cmd=service->createCharacteristic(BLE_CMD_UUID, NIMBLE_PROPERTY::WRITE | NIMBLE_PROPERTY::WRITE_NR);
  dataCh=service->createCharacteristic(BLE_DATA_UUID, NIMBLE_PROPERTY::READ | NIMBLE_PROPERTY::NOTIFY);
  cmd->setCallbacks(new CmdCallbacks());
  dataCh->setValue(deviceSerial.length() ? ("SERIAL:"+deviceSerial).c_str() : "SERIAL:UNSET");
  service->start();

  // Second service keeps CarLab v1.2 compatible without changing its file.
  NimBLEService* simSvc=bleServer->createService(SIM_SERVICE_UUID);
  NimBLECharacteristic* simCmd=simSvc->createCharacteristic(SIM_CMD_UUID, NIMBLE_PROPERTY::WRITE | NIMBLE_PROPERTY::WRITE_NR);
  simStatusCh=simSvc->createCharacteristic(SIM_STATUS_UUID, NIMBLE_PROPERTY::READ | NIMBLE_PROPERTY::NOTIFY);
  simEventCh=simSvc->createCharacteristic(SIM_EVENT_UUID, NIMBLE_PROPERTY::READ | NIMBLE_PROPERTY::NOTIFY);
  simCmd->setCallbacks(new CmdCallbacks());
  simStatusCh->setValue("SIM:READY");
  simEventCh->setValue("SIM:READY");
  simSvc->start();

  NimBLEAdvertising* adv=NimBLEDevice::getAdvertising();
  adv->addServiceUUID(BLE_SERVICE_UUID);
  adv->addServiceUUID(SIM_SERVICE_UUID);
  adv->setScanResponse(true);
  adv->setMinPreferred(0x06);
  adv->setMaxPreferred(0x12);
  NimBLEDevice::startAdvertising();
}

void setup() {
  Serial.begin(115200);
  delay(250);

  bleCmdQueue = xQueueCreate(6, sizeof(BleCommand));

  prefs.begin("khanehremap", true);
  deviceSerial = prefs.getString("serial", "");
  prefs.end();

  pinMode(COOLANT_PIN, INPUT);
  pinMode(KLINE_TX, OUTPUT); digitalWrite(KLINE_TX,HIGH);
  startBLE();
  sendLine("BOOT:SMART_OBD_C3");
  sendLine(deviceSerial.length()?("SERIAL:"+deviceSerial):"SERIAL:UNSET");
  sampleCoolant();

  // Keep BLE responsive immediately after boot. ECU detection is started
  // only after the web client sends REDETECT.
  sendLine("ECU:READY");
  lastPid=millis();
  lastCoolant=millis();
}

void loop() {
  // Execute commands in the Arduino loop task, not in the BLE callback.
  // This keeps the GATT link alive during long CAN/K-Line detection.
  if (bleCmdQueue) {
    BleCommand m{};
    while (xQueueReceive(bleCmdQueue, &m, 0) == pdTRUE) {
      handleCommand(String(m.text));
      delay(1);
    }
  }

  // CarLab can also drive the exact same bench protocol over native USB Serial.
  static String usbLine="";
  while (Serial.available()) {
    char ch=(char)Serial.read();
    if (ch=='\n' || ch=='\r') {
      if (usbLine.length()) { handleCommand(usbLine); usbLine=""; }
    } else if (usbLine.length()<120) usbLine+=ch;
  }

  uint32_t now=millis();
  if(now-lastPid>=700) { lastPid=now; if(simMode) publishSimTelemetry(); else sendPidData(); }
  if(now-lastCoolant>=20000) { lastCoolant=now; sampleCoolant(); }
  delay(2);
}
