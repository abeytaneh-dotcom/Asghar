#include <Arduino.h>
#include <Wire.h>
#include <WiFi.h>
#include <WiFiClient.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <Update.h>
#include <Preferences.h>
#include <HX711.h>
#include <Adafruit_MPU6050.h>
#include <Adafruit_Sensor.h>
#include <Adafruit_NeoPixel.h>
#include <RTClib.h>
#include <NimBLEDevice.h>
#include <math.h>
#include "config.h"
#include "KLineOBD.h"

Preferences prefs;
HX711 scale;
Adafruit_MPU6050 mpu;
RTC_DS3231 rtc;
Adafruit_NeoPixel rgb(RGB_PIXEL_COUNT,PIN_RGB_DATA,NEO_GRB+NEO_KHZ800);
KLineOBD obd(PIN_KLINE_RX,PIN_KLINE_TX);

NimBLECharacteristic* chStatus=nullptr;
NimBLECharacteristic* chEvent=nullptr;
NimBLECharacteristic* chCmd=nullptr;

struct ChannelState { bool on=false,timerPending=false; uint32_t startAt=0,stopAt=0; };
ChannelState cleaner,hydrogen,vacuumCh;

bool mpuOk=false,rtcOk=false,hxOk=false,bleClientConnected=false;
float calFactor=1.0f,emptyMassG=0.0f,fullMassG=5000.0f,lastWeightG=0.0f,lastLevelPct=0.0f;
float tiltZeroRoll=0.0f,tiltZeroPitch=0.0f,tiltDeg=0.0f;
bool tiltMoving=false;

int btnCleanerAdc=BTN_ADC_CLEANER_DEFAULT;
int btnH2Adc=BTN_ADC_H2_DEFAULT;
int btnVacAdc=BTN_ADC_VAC_DEFAULT;

uint32_t h2ArmedUntil=0,h2PressStart=0;
bool h2LongPressHandled=false;
String deviceSerial,wifiSsid,wifiPass,otaUrl,currentJob,lastError;
OBDSnapshot snapBefore,snapAfter;
bool snapshotBusy=false;
uint32_t lastStatusMs=0,lastButtonMs=0;
int lastButton=0;

static String fnum(float v,uint8_t decimals=2){
  if(isnan(v)||isinf(v)) return "null";
  return String(v,decimals);
}

static void emitEvent(const String& s){
  if(chEvent){ chEvent->setValue(s.c_str()); chEvent->notify(); }
  Serial.println(String("EVT ")+s);
}

static void setOutputPin(int pin,bool on){
  digitalWrite(pin,OUTPUT_ACTIVE_HIGH?(on?HIGH:LOW):(on?LOW:HIGH));
}

static bool lockActive(){
  bool lockEnabled=prefs.getBool("lockEn",false);
  if(!lockEnabled) return false;
  uint32_t dueDay=prefs.getUInt("dueDay",0);
  if(!dueDay) return false;
  if(!rtcOk) return true;
  return (rtc.now().unixtime()/86400UL)>dueDay;
}

static bool startAllowed(bool forHydrogen=false){
  if(lockActive()){ lastError="DEVICE_LOCKED"; return false; }
  if(tiltDeg>TILT_BLOCK_DEG||tiltMoving){ lastError="TILT_OR_MOVING"; return false; }
  if(snapshotBusy){ lastError="OBD_TEST_BUSY"; return false; }
  if(forHydrogen && (int32_t)(h2ArmedUntil-millis())<=0){
    lastError="H2_LOCAL_ARM_REQUIRED"; return false;
  }
  lastError=""; return true;
}

static void applyChannel(const String& name,bool on,bool fromLocal=false){
  ChannelState* st=nullptr; int pin=-1;
  if(name=="CLEANER"){ st=&cleaner; pin=PIN_OUT_CLEANER; }
  else if(name=="HYDROGEN"){ st=&hydrogen; pin=PIN_OUT_HYDROGEN; }
  else if(name=="VACUUM"){ st=&vacuumCh; pin=PIN_OUT_VACUUM; }
  else return;

  if(on){
    if(!startAllowed(name=="HYDROGEN")){
      emitEvent("DENY|"+name+"|"+lastError); return;
    }
    if(name=="HYDROGEN") h2ArmedUntil=0;
  }
  st->on=on;
  if(!on){ st->timerPending=false; st->startAt=st->stopAt=0; }
  setOutputPin(pin,on);
  emitEvent(String("STATE|")+name+"|"+(on?"ON":"OFF")+(fromLocal?"|LOCAL":""));
}

static void safeAllOff(){
  cleaner=ChannelState{}; hydrogen=ChannelState{}; vacuumCh=ChannelState{};
  setOutputPin(PIN_OUT_CLEANER,false);
  setOutputPin(PIN_OUT_HYDROGEN,false);
  setOutputPin(PIN_OUT_VACUUM,false);
}

static int classifyButton(int raw){
  if(abs(raw-btnCleanerAdc)<=BTN_ADC_TOLERANCE) return 1;
  if(abs(raw-btnH2Adc)<=BTN_ADC_TOLERANCE) return 2;
  if(abs(raw-btnVacAdc)<=BTN_ADC_TOLERANCE) return 3;
  return 0;
}

static void handleButtons(){
  if(millis()-lastButtonMs<25) return;
  lastButtonMs=millis();
  int b=classifyButton(analogRead(PIN_BUTTON_ADC));

  if(b!=lastButton){
    if(lastButton==2 && h2PressStart && !h2LongPressHandled){
      if(hydrogen.on) applyChannel("HYDROGEN",false,true);
    }
    lastButton=b;
    if(b==2){ h2PressStart=millis(); h2LongPressHandled=false; }
    else { h2PressStart=0; h2LongPressHandled=false; }

    if(b==1){
      if(cleaner.on) applyChannel("CLEANER",false,true); else applyChannel("CLEANER",true,true);
    } else if(b==3){
      if(vacuumCh.on) applyChannel("VACUUM",false,true); else applyChannel("VACUUM",true,true);
    }
  }

  if(b==2 && h2PressStart && !h2LongPressHandled && millis()-h2PressStart>=H2_LONG_PRESS_MS){
    h2LongPressHandled=true;
    if(!hydrogen.on){
      if(tiltDeg<=TILT_BLOCK_DEG && !tiltMoving && !lockActive()){
        h2ArmedUntil=millis()+H2_ARM_WINDOW_MS;
        emitEvent("H2|ARMED|30S");
      } else emitEvent("H2|ARM_DENIED");
    }
  }
}

static void updateTimers(){
  uint32_t now=millis();
  auto tick=[&](const String& name,ChannelState& s){
    if(s.timerPending && (int32_t)(now-s.startAt)>=0){ s.timerPending=false; applyChannel(name,true); }
    if(s.on && s.stopAt && (int32_t)(now-s.stopAt)>=0) applyChannel(name,false);
  };
  tick("CLEANER",cleaner); tick("HYDROGEN",hydrogen); tick("VACUUM",vacuumCh);
}

static void updateTilt(){
  static uint32_t last=0;
  if(!mpuOk||millis()-last<100) return;
  last=millis();
  sensors_event_t a,g,t; mpu.getEvent(&a,&g,&t);
  float ax=a.acceleration.x,ay=a.acceleration.y,az=a.acceleration.z;
  float norm=sqrtf(ax*ax+ay*ay+az*az); if(norm<0.1f) return;
  float roll=atan2f(ay,az)*180.0f/PI;
  float pitch=atan2f(-ax,sqrtf(ay*ay+az*az))*180.0f/PI;
  float dr=roll-tiltZeroRoll,dp=pitch-tiltZeroPitch;
  tiltDeg=sqrtf(dr*dr+dp*dp);
  tiltMoving=fabsf(norm-9.80665f)>0.65f ||
    fabsf(g.gyro.x)+fabsf(g.gyro.y)+fabsf(g.gyro.z)>0.8f;

  if((tiltDeg>TILT_BLOCK_DEG||tiltMoving)&&hydrogen.on){
    applyChannel("HYDROGEN",false);
    emitEvent("SAFETY|H2_STOP|TILT_OR_MOVEMENT");
  }
}

static void updateWeight(){
  static uint32_t last=0;
  if(!hxOk||millis()-last<220) return;
  last=millis();
  if(!scale.is_ready()) return;
  float w=scale.get_units(3);
  if(mpuOk&&tiltDeg>0.5f&&tiltDeg<=10.0f){
    float c=cosf(tiltDeg*PI/180.0f); if(c>0.95f) w/=c;
  }
  lastWeightG=w;
  if(fullMassG>emptyMassG+10.0f){
    lastLevelPct=100.0f*(w-emptyMassG)/(fullMassG-emptyMassG);
    lastLevelPct=constrain(lastLevelPct,0.0f,100.0f);
  }
}

static uint32_t levelColor(float pct){
  if(pct<=10) return rgb.Color(255,0,0);
  if(pct<35) return rgb.Color(255,45,0);
  if(pct<65) return rgb.Color(255,150,0);
  return rgb.Color(0,220,30);
}

static void updateRgb(){
  static uint32_t last=0;
  if(millis()-last<80) return; last=millis();
  rgb.clear();
  if(lockActive()){
    bool blink=(millis()/350)%2;
    if(blink) for(int i=0;i<RGB_PIXEL_COUNT;i++) rgb.setPixelColor(i,rgb.Color(150,0,0));
    rgb.show(); return;
  }
  if(tiltDeg>TILT_BLOCK_DEG||tiltMoving){
    bool blink=(millis()/250)%2;
    if(blink) for(int i=0;i<RGB_PIXEL_COUNT;i++) rgb.setPixelColor(i,rgb.Color(255,80,0));
    rgb.show(); return;
  }
  if(hydrogen.on){
    for(int i=0;i<RGB_PIXEL_COUNT;i++) rgb.setPixelColor(i,rgb.Color(0,70,255));
    rgb.show(); return;
  }
  int lit=int(roundf(lastLevelPct*RGB_PIXEL_COUNT/100.0f));
  lit=constrain(lit,0,RGB_PIXEL_COUNT);
  uint32_t c=levelColor(lastLevelPct);
  bool criticalBlink=lastLevelPct<=10&&((millis()/350)%2==0);
  for(int i=0;i<lit;i++) rgb.setPixelColor(i,criticalBlink?0:c);
  rgb.show();
}

static String snapshotJson(const OBDSnapshot& s){
  return String("{\"valid\":")+(s.valid?"true":"false")+
    ",\"samples\":"+String(s.samples)+
    ",\"lambda\":"+fnum(s.lambda,3)+
    ",\"afr\":"+fnum(s.afrGasoline,2)+
    ",\"cmdLambda\":"+fnum(s.commandedLambda,3)+
    ",\"o2V\":"+fnum(s.o2Voltage,3)+
    ",\"stft\":"+fnum(s.stft,2)+
    ",\"ltft\":"+fnum(s.ltft,2)+
    ",\"ect\":"+fnum(s.coolantC,1)+
    ",\"rpm\":"+fnum(s.rpm,0)+"}";
}

static String makeStatus(){
  const OBDLiveData& d=obd.data();
  String s="{";
  s+="\"fw\":\"" FW_VERSION "\",";
  s+="\"serial\":\""+deviceSerial+"\",";
  s+="\"lock\":"+String(lockActive()?"true":"false")+",";
  s+="\"cleaner\":"+String(cleaner.on?"true":"false")+",";
  s+="\"hydrogen\":"+String(hydrogen.on?"true":"false")+",";
  s+="\"vacuum\":"+String(vacuumCh.on?"true":"false")+",";
  s+="\"h2Armed\":"+String(((int32_t)(h2ArmedUntil-millis())>0)?"true":"false")+",";
  s+="\"weight_g\":"+fnum(lastWeightG,1)+",";
  s+="\"level_pct\":"+fnum(lastLevelPct,1)+",";
  s+="\"tilt_deg\":"+fnum(tiltDeg,1)+",";
  s+="\"moving\":"+String(tiltMoving?"true":"false")+",";
  s+="\"wifi\":"+String(WiFi.status()==WL_CONNECTED?"true":"false")+",";
  s+="\"obd\":{";
  s+="\"connected\":"+String(d.connected?"true":"false")+",";
  s+="\"proto\":\""+obd.protocolName()+"\",";
  s+="\"wideband\":"+String(d.widebandValid?"true":"false")+",";
  s+="\"lambda\":"+fnum(d.lambda,3)+",";
  s+="\"afr\":"+fnum(d.afrGasoline,2)+",";
  s+="\"cmdLambda\":"+fnum(d.commandedLambda,3)+",";
  s+="\"o2V\":"+fnum(d.o2Voltage,3)+",";
  s+="\"stft\":"+fnum(d.stft,2)+",";
  s+="\"ltft\":"+fnum(d.ltft,2)+",";
  s+="\"ect\":"+fnum(d.coolantC,1)+",";
  s+="\"rpm\":"+fnum(d.rpm,0)+"},";
  s+="\"err\":\""+lastError+"\"}";
  return s;
}

static void publishStatus(bool force=false){
  if(!force&&millis()-lastStatusMs<STATUS_PERIOD_MS) return;
  lastStatusMs=millis();
  String s=makeStatus();
  if(chStatus){ chStatus->setValue(s.c_str()); chStatus->notify(); }
}

static void doTare(){
  if(!hxOk){ emitEvent("HX711|NOT_READY"); return; }
  scale.tare(15); emitEvent("HX711|TARED");
}

static void doCalibrate(float knownGrams){
  if(!hxOk||knownGrams<10.0f){ emitEvent("CAL|INVALID"); return; }
  long raw=scale.get_value(15);
  if(labs(raw)<100){ emitEvent("CAL|RAW_TOO_SMALL"); return; }
  calFactor=float(raw)/knownGrams;
  scale.set_scale(calFactor);
  prefs.putFloat("calF",calFactor);
  emitEvent("CAL|OK|"+String(calFactor,6));
}

static void doTiltZero(){
  if(!mpuOk){ emitEvent("TILT|NO_SENSOR"); return; }
  sensors_event_t a,g,t; mpu.getEvent(&a,&g,&t);
  tiltZeroRoll=atan2f(a.acceleration.y,a.acceleration.z)*180.0f/PI;
  tiltZeroPitch=atan2f(-a.acceleration.x,sqrtf(a.acceleration.y*a.acceleration.y+a.acceleration.z*a.acceleration.z))*180.0f/PI;
  prefs.putFloat("tiltR",tiltZeroRoll); prefs.putFloat("tiltP",tiltZeroPitch);
  emitEvent("TILT|ZEROED");
}

static bool connectWifi(uint32_t timeoutMs=12000){
  if(!wifiSsid.length()) return false;
  WiFi.mode(WIFI_STA); WiFi.begin(wifiSsid.c_str(),wifiPass.c_str());
  uint32_t t=millis();
  while(WiFi.status()!=WL_CONNECTED&&millis()-t<timeoutMs) delay(100);
  bool ok=WiFi.status()==WL_CONNECTED;
  emitEvent(ok?"WIFI|CONNECTED":"WIFI|FAIL");
  return ok;
}

static bool otaFromUrl(const String& url){
  if(!url.length()){ emitEvent("OTA|NO_URL"); return false; }
  if(cleaner.on||hydrogen.on||vacuumCh.on) safeAllOff();
  emitEvent("OTA|START");
  if(WiFi.status()!=WL_CONNECTED&&!connectWifi()) return false;

  HTTPClient http;
  std::unique_ptr<WiFiClient> plain;
  std::unique_ptr<WiFiClientSecure> secure;
  if(url.startsWith("https://")){
    secure.reset(new WiFiClientSecure());
    secure->setInsecure();
    if(!http.begin(*secure,url)){ emitEvent("OTA|HTTP_BEGIN_FAIL"); return false; }
  } else {
    plain.reset(new WiFiClient());
    if(!http.begin(*plain,url)){ emitEvent("OTA|HTTP_BEGIN_FAIL"); return false; }
  }

  int code=http.GET();
  if(code!=HTTP_CODE_OK){ emitEvent("OTA|HTTP|"+String(code)); http.end(); return false; }
  int total=http.getSize();
  if(!Update.begin(total>0?total:UPDATE_SIZE_UNKNOWN)){ emitEvent("OTA|BEGIN_FAIL"); http.end(); return false; }
  WiFiClient* stream=http.getStreamPtr();
  uint8_t buf[1024]; int writtenTotal=0; uint32_t lastEvt=0;
  while(http.connected()&&(total<0||writtenTotal<total)){
    size_t avail=stream->available();
    if(avail){
      size_t take=min(avail,sizeof(buf));
      int n=stream->readBytes(buf,take);
      if(n>0){
        if(Update.write(buf,n)!=(size_t)n){
          emitEvent("OTA|WRITE_FAIL"); Update.end(); http.end(); return false;
        }
        writtenTotal+=n;
      }
    } else delay(1);
    if(millis()-lastEvt>1000&&total>0){
      lastEvt=millis(); emitEvent("OTA|PROGRESS|"+String((100L*writtenTotal)/total));
    }
  }
  bool ok=Update.end(true); http.end();
  if(!ok){ emitEvent("OTA|END_FAIL|"+String(Update.getError())); return false; }
  emitEvent("OTA|OK|REBOOT"); delay(800); ESP.restart();
  return true;
}

static String part(const String& s,int idx){
  int start=0;
  for(int i=0;i<idx;i++){ int p=s.indexOf('|',start); if(p<0) return ""; start=p+1; }
  int end=s.indexOf('|',start); if(end<0) end=s.length();
  return s.substring(start,end);
}

static void scheduleChannel(const String& name,uint32_t delaySec,uint32_t durationSec){
  ChannelState* st=nullptr;
  if(name=="CLEANER") st=&cleaner;
  else if(name=="HYDROGEN") st=&hydrogen;
  else if(name=="VACUUM") st=&vacuumCh;
  if(!st) return;
  if(name=="HYDROGEN"&&(int32_t)(h2ArmedUntil-millis())<=0){
    emitEvent("DENY|HYDROGEN|H2_LOCAL_ARM_REQUIRED"); return;
  }
  st->timerPending=true;
  st->startAt=millis()+delaySec*1000UL;
  st->stopAt=st->startAt+durationSec*1000UL;
  emitEvent("TIMER|SET|"+name);
}

static void runSnapshot(bool before){
  if(!obd.isConnected()){ emitEvent("OBD|NOT_CONNECTED"); return; }
  snapshotBusy=true; safeAllOff();
  emitEvent(String("OBD|SNAP|")+(before?"BEFORE":"AFTER")+"|START");
  OBDSnapshot tmp;
  bool ok=obd.captureSnapshot(tmp,12000);
  if(before) snapBefore=tmp; else snapAfter=tmp;
  snapshotBusy=false;
  emitEvent(String("OBD|SNAP|")+(before?"BEFORE":"AFTER")+"|"+(ok?"OK|":"FAIL|")+snapshotJson(tmp));
}

static void handleCommand(String c){
  c.trim(); if(!c.length()) return;
  Serial.println(String("CMD ")+c);
  String a=part(c,0),b=part(c,1),d=part(c,2),e=part(c,3);
  a.toUpperCase(); b.toUpperCase();

  if(a=="STATUS?") publishStatus(true);
  else if(a=="MANUAL"){ d.toUpperCase(); applyChannel(b,d=="ON"); }
  else if(a=="TIMER") scheduleChannel(b,d.toInt(),e.toInt());
  else if(a=="CANCEL"&&b=="ALL"){ safeAllOff(); emitEvent("CANCEL|ALL|OK"); }
  else if(a=="TARE") doTare();
  else if(a=="CAL") doCalibrate(b.toFloat());
  else if(a=="LEVEL"){
    if(b=="EMPTY"){ emptyMassG=d.toFloat(); prefs.putFloat("emptyG",emptyMassG); }
    else if(b=="FULL"){ fullMassG=d.toFloat(); prefs.putFloat("fullG",fullMassG); }
    emitEvent("LEVEL|SET");
  }
  else if(a=="TILT"&&b=="ZERO") doTiltZero();
  else if(a=="BTNADC"){
    btnCleanerAdc=b.toInt(); btnH2Adc=d.toInt(); btnVacAdc=e.toInt();
    prefs.putInt("btnC",btnCleanerAdc); prefs.putInt("btnH",btnH2Adc); prefs.putInt("btnV",btnVacAdc);
    emitEvent("BTNADC|SAVED");
  }
  else if(a=="WIFI"&&b=="SET"){
    wifiSsid=d; wifiPass=e;
    prefs.putString("ssid",wifiSsid); prefs.putString("wpass",wifiPass); emitEvent("WIFI|SAVED");
  }
  else if(a=="WIFI"&&b=="CONNECT") connectWifi();
  else if(a=="OTA"&&b=="URL"){
    int p1=c.indexOf('|'); int p2=c.indexOf('|',p1+1);
    otaUrl=(p2>=0)?c.substring(p2+1):"";
    prefs.putString("otaUrl",otaUrl); emitEvent("OTA|URL_SAVED");
  }
  else if(a=="OTA"&&b=="START"){ String u=d.length()?d:otaUrl; otaFromUrl(u); }
  else if(a=="OBD"&&b=="CONNECT"){
    safeAllOff(); bool ok=obd.connectAuto();
    emitEvent(String("OBD|CONNECT|")+(ok?"OK|":"FAIL|")+obd.protocolName());
  }
  else if(a=="OBD"&&b=="DISCONNECT"){ obd.disconnect(); emitEvent("OBD|DISCONNECTED"); }
  else if(a=="OBD"&&b=="SNAP"&&d=="BEFORE") runSnapshot(true);
  else if(a=="OBD"&&b=="SNAP"&&d=="AFTER") runSnapshot(false);
  else if(a=="OBD"&&b=="REPORT") emitEvent("OBD|REPORT|BEFORE="+snapshotJson(snapBefore)+"|AFTER="+snapshotJson(snapAfter));
  else if(a=="SESSION"&&b=="START"){ currentJob=d; emitEvent("SESSION|STARTED|"+currentJob); }
  else if(a=="SESSION"&&b=="END"){ currentJob=""; emitEvent("SESSION|ENDED"); }
  else emitEvent("ERR|UNKNOWN_CMD|"+c);
}

class ServerCallbacks:public NimBLEServerCallbacks{
  void onConnect(NimBLEServer*) override { bleClientConnected=true; emitEvent("BLE|CONNECTED"); }
  void onDisconnect(NimBLEServer*) override {
    bleClientConnected=false; NimBLEDevice::startAdvertising();
  }
};

class CmdCallbacks:public NimBLECharacteristicCallbacks{
  void onWrite(NimBLECharacteristic* c) override {
    std::string v=c->getValue();
    if(!v.empty()) handleCommand(String(v.c_str()));
  }
};

static void initBle(){
  String name=String(DEVICE_NAME_PREFIX)+"_"+deviceSerial.substring(deviceSerial.length()-6);
  NimBLEDevice::init(name.c_str());
  NimBLEDevice::setPower(ESP_PWR_LVL_P9);
  NimBLEServer* server=NimBLEDevice::createServer();
  server->setCallbacks(new ServerCallbacks());
  NimBLEService* svc=server->createService(BLE_SERVICE_UUID);
  chCmd=svc->createCharacteristic(BLE_CMD_UUID,NIMBLE_PROPERTY::WRITE|NIMBLE_PROPERTY::WRITE_NR);
  chStatus=svc->createCharacteristic(BLE_STATUS_UUID,NIMBLE_PROPERTY::READ|NIMBLE_PROPERTY::NOTIFY);
  chEvent=svc->createCharacteristic(BLE_EVENT_UUID,NIMBLE_PROPERTY::READ|NIMBLE_PROPERTY::NOTIFY);
  chCmd->setCallbacks(new CmdCallbacks());
  svc->start();
  NimBLEAdvertising* adv=NimBLEDevice::getAdvertising();
  adv->addServiceUUID(BLE_SERVICE_UUID); adv->setScanResponse(true); adv->start();
}

static String buildSerial(){
  uint64_t mac=ESP.getEfuseMac();
  char b[24]; snprintf(b,sizeof(b),"KR-C3-%06llX",(unsigned long long)(mac&0xFFFFFFULL));
  return String(b);
}

static void selfTest(){
  emitEvent("SELFTEST|START");
  emitEvent(String("SELFTEST|HX711|")+(hxOk?"OK":"FAIL"));
  emitEvent(String("SELFTEST|MPU6050|")+(mpuOk?"OK":"FAIL"));
  emitEvent(String("SELFTEST|RTC|")+(rtcOk?"OK":"OPTIONAL_MISSING"));
  emitEvent("SELFTEST|KLINE|READY");
  emitEvent("SELFTEST|DONE");
}

void setup(){
  Serial.begin(115200); delay(300);
  pinMode(PIN_OUT_CLEANER,OUTPUT);
  pinMode(PIN_OUT_HYDROGEN,OUTPUT);
  pinMode(PIN_OUT_VACUUM,OUTPUT);
  safeAllOff();
  pinMode(PIN_BUTTON_ADC,INPUT);

  prefs.begin("khaneh",false);
  deviceSerial=prefs.getString("serial",buildSerial()); prefs.putString("serial",deviceSerial);
  calFactor=prefs.getFloat("calF",1.0f);
  emptyMassG=prefs.getFloat("emptyG",0.0f);
  fullMassG=prefs.getFloat("fullG",5000.0f);
  tiltZeroRoll=prefs.getFloat("tiltR",0.0f);
  tiltZeroPitch=prefs.getFloat("tiltP",0.0f);
  btnCleanerAdc=prefs.getInt("btnC",BTN_ADC_CLEANER_DEFAULT);
  btnH2Adc=prefs.getInt("btnH",BTN_ADC_H2_DEFAULT);
  btnVacAdc=prefs.getInt("btnV",BTN_ADC_VAC_DEFAULT);
  wifiSsid=prefs.getString("ssid","");
  wifiPass=prefs.getString("wpass","");
  otaUrl=prefs.getString("otaUrl","");

  Wire.begin(PIN_I2C_SDA,PIN_I2C_SCL);
  mpuOk=mpu.begin(0x68,&Wire);
  if(mpuOk){
    mpu.setAccelerometerRange(MPU6050_RANGE_2_G);
    mpu.setGyroRange(MPU6050_RANGE_250_DEG);
    mpu.setFilterBandwidth(MPU6050_BAND_21_HZ);
  }
  rtcOk=rtc.begin(&Wire);

  scale.begin(PIN_HX_DOUT,PIN_HX_SCK);
  delay(100); hxOk=scale.is_ready();
  if(hxOk) scale.set_scale(calFactor);

  rgb.begin(); rgb.setBrightness(RGB_BRIGHTNESS); rgb.clear(); rgb.show();
  obd.begin();
  initBle();
  selfTest();
  publishStatus(true);
}

void loop(){
  handleButtons();
  updateTilt();
  updateWeight();
  updateTimers();
  if(obd.isConnected()&&!snapshotBusy) obd.poll();
  updateRgb();
  publishStatus();
  delay(2);
}
