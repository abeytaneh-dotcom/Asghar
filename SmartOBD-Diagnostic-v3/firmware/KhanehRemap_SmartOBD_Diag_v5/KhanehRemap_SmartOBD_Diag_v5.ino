/*
 KHANEH REMAP SMART OBD - DIAGNOSTIC v5.0
 ESP32-C3 + CAN transceiver + K-Line transceiver + water probe.
 Diagnostics only: ECU ID, DTC read/clear, live PIDs, ECU reset, cluster data.
*/
#include <Arduino.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPUpdate.h>
#include <Preferences.h>
#include <ArduinoJson.h>
#include <NimBLEDevice.h>
#include "BoardConfig.h"
#include "WaterGuard.h"
#include "CanObd.h"
#include "KLineObd.h"

static const char* FW_VERSION="5.0-diag";
static const char* UUID_SERVICE="7f640201-7c7d-4f0a-8b6f-4f484f4d4501";
static const char* UUID_CMD    ="7f640202-7c7d-4f0a-8b6f-4f484f4d4501";
static const char* UUID_DATA   ="7f640203-7c7d-4f0a-8b6f-4f484f4d4501";

enum KRProtocol{PROTO_NONE,PROTO_CAN500,PROTO_CAN250,PROTO_KLINE_FAST,PROTO_KLINE_5BAUD};
struct KRTelemetry{
 float speed=0,rpm=0,coolant=NAN,fuel=NAN,voltage=NAN,waterLevel=0;
 bool noWater=true,waterSensorFault=true,ecuConnected=false;
 KRProtocol proto=PROTO_NONE;
};

Preferences prefs;
KRWaterGuard waterGuard;
KRCanObd canObd;
KRKLineObd klineObd;
KRTelemetry telem;
NimBLECharacteristic *chCmd=nullptr,*chData=nullptr;
bool bleConnected=false,otaRunning=false,wifiEnabled=false;
String configuredSsid,selectedProfile="AUTO",rxLong;
int rxId=-1,rxNext=0,rxTotal=0;
uint32_t lastFast=0,lastFull=0,lastLegacy=0,lastDetect=0,lastWifiRetry=0;
uint8_t pidIndex=0,busFails=0,txId=0;

static String serialNumber(){
 prefs.begin("smartobd",false);
 String s=prefs.getString("serial","");
 if(!s.length()){
   uint64_t mac=ESP.getEfuseMac();
   char b[32]; snprintf(b,sizeof(b),"KR-ESP32-%08lX",(uint32_t)(mac&0xffffffffUL));
   s=b; prefs.putString("serial",s);
 }
 prefs.end(); return s;
}
static const char* protoName(KRProtocol p){
 switch(p){
  case PROTO_CAN500:return "CAN500"; case PROTO_CAN250:return "CAN250";
  case PROTO_KLINE_FAST:return "KLINE_FAST"; case PROTO_KLINE_5BAUD:return "KLINE_5BAUD";
  default:return "NONE";
 }
}
static String field(const String&s,int wanted){
 int from=0,idx=0;
 for(int i=0;i<=(int)s.length();i++) if(i==(int)s.length()||s[i]=='|'){
   if(idx==wanted)return s.substring(from,i); from=i+1; idx++;
 }
 return "";
}
static void rawNotify(const String&s){
 if(!chData)return; chData->setValue((uint8_t*)s.c_str(),s.length());
 if(bleConnected)chData->notify();
}
static void bleOut(const String&s){
 if(s.length()<=20){rawNotify(s);return;}
 const int chunk=9; int total=(s.length()+chunk-1)/chunk; uint8_t id=++txId;
 if(total>255){rawNotify("DENY|TX_TOO_LONG");return;}
 for(int seq=0;seq<total;seq++){
   char h[16];snprintf(h,sizeof(h),"X|%02X|%02X|%02X|",id,seq,total);
   rawNotify(String(h)+s.substring(seq*chunk,min((seq+1)*chunk,(int)s.length()))); delay(2);
 }
}
static float batteryVoltage(){
 uint32_t mv=analogReadMilliVolts(KR_BATTERY_SENSE_PIN);
 float v=(mv/1000.0f)*KR_BATTERY_DIVIDER_RATIO;
 return (v>=0&&v<=24)?v:NAN;
}
static void disconnectBus(){
 canObd.stop();klineObd.stop();telem.proto=PROTO_NONE;telem.ecuConnected=false;
 telem.rpm=0;telem.speed=0;busFails=0;
}
static bool testCan(uint32_t rate){
 if(!canObd.begin(rate))return false;
 uint8_t d[4];size_t n=0;if(canObd.requestPid(0x00,d,n,90))return true;
 canObd.stop();return false;
}
static bool testK(bool fast){
 if(!(fast?klineObd.beginFast():klineObd.beginFiveBaud()))return false;
 uint8_t d[4];size_t n=0;if(klineObd.requestPid(0x00,d,n,240))return true;
 klineObd.stop();return false;
}
static void setProto(KRProtocol p){
 telem.proto=p;telem.ecuConnected=p!=PROTO_NONE;busFails=0;
 bleOut("ECU:CONNECTED,"+String(protoName(p)));
}
static void autoDetect(){
 if(telem.proto!=PROTO_NONE||millis()-lastDetect<3000)return;lastDetect=millis();
 bleOut("ECU:SCAN,CAN500");if(testCan(500000)){setProto(PROTO_CAN500);return;}
 bleOut("ECU:SCAN,CAN250");if(testCan(250000)){setProto(PROTO_CAN250);return;}
 bleOut("ECU:SCAN,KFAST");if(testK(true)){setProto(PROTO_KLINE_FAST);return;}
 bleOut("ECU:SCAN,K5B");if(testK(false)){setProto(PROTO_KLINE_5BAUD);return;}
 disconnectBus();bleOut("ECU:NOT_FOUND");
}
static bool readPid(uint8_t pid,uint8_t*d,size_t&n){
 if(telem.proto==PROTO_CAN500||telem.proto==PROTO_CAN250)return canObd.requestPid(pid,d,n,50);
 if(telem.proto==PROTO_KLINE_FAST||telem.proto==PROTO_KLINE_5BAUD)return klineObd.requestPid(pid,d,n,170);
 return false;
}
static void decodePid(uint8_t pid,const uint8_t*d,size_t n){
 if(!n)return;
 if(pid==0x05)telem.coolant=(float)d[0]-40;
 else if(pid==0x0C&&n>=2)telem.rpm=((uint16_t)d[0]*256+d[1])/4.0f;
 else if(pid==0x0D)telem.speed=d[0];
 else if(pid==0x2F)telem.fuel=d[0]*100.0f/255.0f;
 else if(pid==0x42&&n>=2)telem.voltage=((uint16_t)d[0]*256+d[1])/1000.0f;
}
static void pollVehicle(){
 if(telem.proto==PROTO_NONE){autoDetect();return;}
 static const uint8_t pids[]={0x0C,0x0D,0x0C,0x05,0x0D,0x2F,0x0C,0x42};
 uint8_t pid=pids[pidIndex++%(sizeof(pids)/sizeof(pids[0]))],d[4];size_t n=0;
 if(readPid(pid,d,n)){busFails=0;telem.ecuConnected=true;decodePid(pid,d,n);}
 else if(++busFails>=14){disconnectBus();bleOut("ECU:NOT_FOUND");}
}
static void waterTick(){
 if(!waterGuard.due())return;
 bool old=telem.noWater;KRWaterState w=waterGuard.update();
 telem.waterLevel=w.level;telem.noWater=w.noWater;telem.waterSensorFault=w.sensorFault;
 if(telem.noWater&&!old)bleOut("ALARM:LOW_COOLANT,"+String(telem.waterLevel,0));
 else if(!telem.noWater&&old)bleOut("COOLANT:OK");
}
static void buzzerTick(){
 static bool on=false;static uint32_t tm=0;
 if(!telem.noWater){on=false;digitalWrite(KR_BUZZER_PIN,LOW);return;}
 uint32_t now=millis(),iv=on?230:120;if(now-tm>=iv){tm=now;on=!on;digitalWrite(KR_BUZZER_PIN,on);}
}
static void telemetryTick(){
 uint32_t now=millis();
 if(bleConnected&&now-lastFast>=KR_FAST_TELEMETRY_MS){
  lastFast=now;bleOut("Q|"+String((int)roundf(telem.speed))+"|"+String((int)roundf(telem.rpm)));
 }
 if(bleConnected&&now-lastFull>=KR_FULL_TELEMETRY_MS){
  lastFull=now;
  String t="T|"+String((int)roundf(telem.speed))+"|"+String((int)roundf(telem.rpm))+"|"+
   (isnan(telem.coolant)?String("-"):String(telem.coolant,0))+"|"+
   (isnan(telem.fuel)?String("-"):String(telem.fuel,0))+"|"+
   (isnan(telem.voltage)?String("-"):String(telem.voltage,1))+"|"+
   String(telem.waterLevel,0)+"|"+String(telem.noWater?1:0)+"|"+
   String(telem.waterSensorFault?1:0)+"|"+protoName(telem.proto)+"|"+
   String(wifiEnabled?1:0)+"|"+String(WiFi.status()==WL_CONNECTED?1:0);
  bleOut(t);
 }
 if(bleConnected&&now-lastLegacy>=650){
  lastLegacy=now;
  bleOut("L1|"+String((int)roundf(telem.rpm))+"|"+String((int)roundf(telem.speed))+"|"+(isnan(telem.coolant)?String("-"):String(telem.coolant,0)));
  delay(3);bleOut("L2|"+(isnan(telem.fuel)?String("-"):String(telem.fuel,0))+"|"+(isnan(telem.voltage)?String("-"):String(telem.voltage,1)));
  delay(3);bleOut("L3|"+String(telem.waterLevel,0)+"|0|0|0");
  if(telem.noWater)bleOut("ALARM:LOW_COOLANT,"+String(telem.waterLevel,0));
 }
}
static void loadNet(){
 prefs.begin("net",true);configuredSsid=prefs.getString("ssid","");wifiEnabled=prefs.getBool("enabled",false);prefs.end();
}
static void wifiOff(bool persist){
 if(persist){prefs.begin("net",false);prefs.putBool("enabled",false);prefs.end();}
 wifiEnabled=false;WiFi.setAutoReconnect(false);WiFi.disconnect(true,false);WiFi.mode(WIFI_OFF);bleOut("NET|OFF");
}
static bool wifiSaved(bool persist){
 prefs.begin("net",true);String ssid=prefs.getString("ssid",""),pass=prefs.getString("pass","");prefs.end();
 if(!ssid.length()||pass.length()<8){bleOut("NET|NO_SAVED");return false;}
 if(persist){prefs.begin("net",false);prefs.putBool("enabled",true);prefs.end();}
 wifiEnabled=true;configuredSsid=ssid;WiFi.mode(WIFI_STA);WiFi.setAutoReconnect(false);WiFi.begin(ssid.c_str(),pass.c_str());
 bleOut("NET|CONNECTING|"+ssid);return true;
}
static bool saveWifi(const String&ssid,const String&pass){
 if(!ssid.length()||ssid.length()>32||pass.length()<8||pass.length()>63)return false;
 prefs.begin("net",false);prefs.putString("ssid",ssid);prefs.putString("pass",pass);prefs.putBool("enabled",true);prefs.end();
 configuredSsid=ssid;return wifiSaved(false);
}
static void otaTask(void*){
 otaRunning=true;bleOut("OTA|START");
 if(!wifiEnabled||WiFi.status()!=WL_CONNECTED){bleOut("OTA|NO_WIFI");otaRunning=false;vTaskDelete(nullptr);return;}
 WiFiClientSecure c;
#if KR_OTA_ALLOW_INSECURE
 c.setInsecure();
#else
 if(strlen(KR_OTA_ROOT_CA)<64){bleOut("OTA|NO_CA");otaRunning=false;vTaskDelete(nullptr);return;}
 c.setCACert(KR_OTA_ROOT_CA);
#endif
 httpUpdate.rebootOnUpdate(true);
 t_httpUpdate_return r=httpUpdate.update(c,KR_OTA_URL,FW_VERSION);
 if(r==HTTP_UPDATE_FAILED)bleOut("OTA|FAIL");else if(r==HTTP_UPDATE_NO_UPDATES)bleOut("OTA|CURRENT");
 otaRunning=false;vTaskDelete(nullptr);
}
static void startOta(){if(otaRunning){bleOut("OTA|BUSY");return;}xTaskCreate(otaTask,"krOta",12288,nullptr,1,nullptr);}
static void handle(String s){
 s.trim();if(!s.length())return;
 if(s=="GET_SERIAL"){bleOut("S|"+serialNumber());return;}
 if(s=="PING"){bleOut("PONG|"+String(millis()));return;}
 if(s=="STATUS"){bleOut("ST|"+String(protoName(telem.proto))+"|"+String(telem.ecuConnected?1:0)+"|WIFI:"+String(wifiEnabled?1:0));return;}
 if(s=="BUS?"){bleOut("BUS|"+String(protoName(telem.proto)));return;}
 if(s=="REDETECT"){disconnectBus();lastDetect=0;bleOut("ECU:SCAN,AUTO");return;}
 if(s.startsWith("E|")||s.startsWith("ECU_SELECT:")){
  selectedProfile=s.startsWith("E|")?s.substring(2):s.substring(11);selectedProfile.trim();if(!selectedProfile.length())selectedProfile="AUTO";
  bleOut("EC|"+selectedProfile);if(telem.proto==PROTO_NONE){lastDetect=0;autoDetect();}return;
 }
 if(s=="ID?"){
  if(telem.proto==PROTO_NONE)autoDetect();String vin="",cal="";bool v=false,c=false;
  if(telem.proto==PROTO_CAN500||telem.proto==PROTO_CAN250){v=canObd.readVin(vin);c=canObd.readCalibrationId(cal);}
  else if(telem.proto==PROTO_KLINE_FAST||telem.proto==PROTO_KLINE_5BAUD){v=klineObd.readVin(vin);c=klineObd.readCalibrationId(cal);}
  bleOut("ID|AUTO|"+selectedProfile+"||"+(c?cal:String(""))+"|||"+(v?vin:String(""))+"|"+protoName(telem.proto)+"||");return;
 }
 if(s=="READ_DTC"){
  if(telem.proto==PROTO_NONE)autoDetect();String codes="";bool ok=false;
  if(telem.proto==PROTO_CAN500||telem.proto==PROTO_CAN250)ok=canObd.readDtcs(codes);
  else if(telem.proto==PROTO_KLINE_FAST||telem.proto==PROTO_KLINE_5BAUD)ok=klineObd.readDtcs(codes);
  if(!ok){bleOut("DT|ERROR");return;}if(!codes.length()){bleOut("DT|NONE");bleOut("DT|END");return;}
  bleOut("DT|BEGIN");int from=0;while(from<(int)codes.length()){int q=codes.indexOf(',',from);String x=q<0?codes.substring(from):codes.substring(from,q);if(x.length())bleOut("DT|"+x);if(q<0)break;from=q+1;}bleOut("DT|END");return;
 }
 if(s=="CLEAR_DTC"){
  if(telem.rpm>50){bleOut("DENY|ENGINE_RUNNING");return;}if(telem.proto==PROTO_NONE)autoDetect();bool ok=false;
  if(telem.proto==PROTO_CAN500||telem.proto==PROTO_CAN250)ok=canObd.clearDtcs();
  else if(telem.proto==PROTO_KLINE_FAST||telem.proto==PROTO_KLINE_5BAUD)ok=klineObd.clearDtcs();
  bleOut(ok?"CLEAR_DTC:SENT":"CLEAR_DTC:FAIL");return;
 }
 if(s=="ECU|RESET"){
  if(telem.rpm>50){bleOut("DENY|ENGINE_RUNNING");return;}if(telem.proto==PROTO_NONE)autoDetect();bool ok=false;
  if(telem.proto==PROTO_CAN500||telem.proto==PROTO_CAN250)ok=canObd.ecuReset();
  else if(telem.proto==PROTO_KLINE_FAST||telem.proto==PROTO_KLINE_5BAUD)ok=klineObd.ecuReset();
  bleOut(ok?"RESET|OK":"RESET|UNSUPPORTED");return;
 }
 if(s=="NET?"){bleOut("NET|"+String(wifiEnabled?"ON":"OFF")+"|"+String(WiFi.status()==WL_CONNECTED?"CONNECTED":"DISCONNECTED")+"|"+(WiFi.status()==WL_CONNECTED?WiFi.localIP().toString():String("0.0.0.0"))+"|"+configuredSsid);return;}
 if(s=="WIFI_ON"){wifiSaved(true);return;}
 if(s=="WIFI_OFF"){wifiOff(true);return;}
 if(s.startsWith("WIFI:")){
  JsonDocument d;if(deserializeJson(d,s.substring(5))){bleOut("NET|BAD_JSON");return;}
  String ssid=d["ssid"]|"",pass=d["pass"]|"";bleOut(saveWifi(ssid,pass)?"NET|SAVED":"NET|BAD_DATA");return;
 }
 if(s=="OTA"){startOta();return;}
 if(s.startsWith("PRG|")||s.startsWith("ACT|")||s.startsWith("WRITE|")||s.startsWith("ERASE|")){bleOut("DENY|READ_ONLY");return;}
 bleOut("DENY|CMD");
}
class ServerCB:public NimBLEServerCallbacks{
 void onConnect(NimBLEServer*,NimBLEConnInfo&)override{bleConnected=true;}
 void onDisconnect(NimBLEServer*,NimBLEConnInfo&,int)override{bleConnected=false;NimBLEDevice::startAdvertising();}
};
class CmdCB:public NimBLECharacteristicCallbacks{
 void onWrite(NimBLECharacteristic*c,NimBLEConnInfo&)override{
  String s=c->getValue().c_str();
  if(s.startsWith("Y|")){
   int id=strtol(field(s,1).c_str(),nullptr,16),seq=strtol(field(s,2).c_str(),nullptr,16),tot=strtol(field(s,3).c_str(),nullptr,16);
   String p=field(s,4);
   if(seq==0||id!=rxId){rxId=id;rxNext=0;rxTotal=tot;rxLong="";}
   if(id!=rxId||seq!=rxNext||tot!=rxTotal){rxLong="";rxNext=rxTotal=0;bleOut("DENY|RX_SEQ");return;}
   rxLong+=p;rxNext++;if(rxNext>=rxTotal){String full=rxLong;rxLong="";rxNext=rxTotal=0;handle(full);}return;
  }
  handle(s);
 }
};
static void setupBle(){
 String name="KR_OBD_"+serialNumber().substring(max(0,(int)serialNumber().length()-8));
 NimBLEDevice::init(name.c_str());NimBLEDevice::setPower(3);NimBLEDevice::setMTU(185);
 NimBLEServer*s=NimBLEDevice::createServer();s->setCallbacks(new ServerCB());
 NimBLEService*v=s->createService(UUID_SERVICE);
 chCmd=v->createCharacteristic(UUID_CMD,NIMBLE_PROPERTY::WRITE|NIMBLE_PROPERTY::WRITE_NR);
 chData=v->createCharacteristic(UUID_DATA,NIMBLE_PROPERTY::READ|NIMBLE_PROPERTY::NOTIFY);
 chCmd->setCallbacks(new CmdCB());chData->setValue("READY");v->start();
 NimBLEAdvertising*a=NimBLEDevice::getAdvertising();a->setName(name.c_str());a->addServiceUUID(UUID_SERVICE);a->start();
}
void setup(){
 Serial.begin(115200);delay(180);analogReadResolution(12);
 pinMode(KR_BUZZER_PIN,OUTPUT);digitalWrite(KR_BUZZER_PIN,LOW);waterGuard.begin();
 telem.voltage=batteryVoltage();setupBle();loadNet();
 if(wifiEnabled)wifiSaved(false);else{WiFi.mode(WIFI_OFF);WiFi.setAutoReconnect(false);}
 Serial.println("BOOT|KHANEH_REMAP|SMART_OBD|"+String(FW_VERSION));
}
void loop(){
 waterTick();buzzerTick();
 static uint32_t bat=0;if(millis()-bat>1000){bat=millis();float v=batteryVoltage();if(!isnan(v)&&(isnan(telem.voltage)||telem.proto==PROTO_NONE))telem.voltage=v;}
 pollVehicle();telemetryTick();
 if(wifiEnabled&&configuredSsid.length()&&WiFi.status()!=WL_CONNECTED&&millis()-lastWifiRetry>15000){lastWifiRetry=millis();wifiSaved(false);}
 delay(1);
}
