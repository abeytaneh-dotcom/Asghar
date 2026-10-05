/*
 KHANEH REMAP SMART OBD - DIAGNOSTIC v5.0
 ESP32-C3 + CAN transceiver + K-Line transceiver + water probe.
 Diagnostics only: ECU ID, DTC read/clear, live PIDs, ECU reset, cluster data.
*/
#include <Arduino.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <atomic>
#include "freertos/FreeRTOS.h"
#include "freertos/queue.h"
#include "freertos/task.h"
#include <Preferences.h>
#include <ArduinoJson.h>
#include <NimBLEDevice.h>
#include "BoardConfig.h"
#include "WaterGuard.h"
#include "CanObd.h"
#include "KLineObd.h"

static const char* FW_VERSION="5.1-bench-safe";
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
std::atomic<bool> bleConnected{false};
std::atomic<uint32_t> connectionEpoch{0}, droppedCommands{0};
bool wifiEnabled=false;
struct KRCommand { uint32_t epoch; char text[KR_COMMAND_MAX_BYTES+1]; };
QueueHandle_t commandQueue=nullptr;
TaskHandle_t waterTaskHandle=nullptr;
portMUX_TYPE waterMux=portMUX_INITIALIZER_UNLOCKED;
KRWaterState waterSnapshot;
uint32_t waterHeartbeat=0, rpmUpdated=0, lastPoll=0, handledEpoch=0;
bool rpmValid=false;
KRProtocol requestedProtocol=PROTO_NONE;
String configuredSsid,selectedProfile="AUTO";
kr::CommandFragments<KR_COMMAND_MAX_BYTES> fragments;
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
 telem.rpm=0;telem.speed=0;telem.coolant=NAN;telem.fuel=NAN;busFails=0;rpmValid=false;
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
 if(!KR_HARDWARE_VERIFIED || !waterTaskHandle || requestedProtocol==PROTO_NONE) return;
 if(telem.proto!=PROTO_NONE||millis()-lastDetect<3000)return;
 lastDetect=millis();bool ok=false;
 switch(requestedProtocol){
  case PROTO_CAN500:ok=testCan(500000);break;
  case PROTO_CAN250:ok=testCan(250000);break;
  case PROTO_KLINE_FAST:ok=testK(true);break;
  case PROTO_KLINE_5BAUD:ok=testK(false);break;
  default:break;
 }
 if(ok)setProto(requestedProtocol);
 else{disconnectBus();bleOut("ECU:NOT_FOUND");}
}
static bool readPid(uint8_t pid,uint8_t*d,size_t&n){
 if(telem.proto==PROTO_CAN500||telem.proto==PROTO_CAN250)return canObd.requestPid(pid,d,n,50);
 if(telem.proto==PROTO_KLINE_FAST||telem.proto==PROTO_KLINE_5BAUD)return klineObd.requestPid(pid,d,n,170);
 return false;
}
static void decodePid(uint8_t pid,const uint8_t*d,size_t n){
 size_t expected=(pid==0x0C||pid==0x42)?2:1;
 if(n!=expected){if(pid==0x0C)rpmValid=false;return;}
 if(pid==0x05)telem.coolant=(float)d[0]-40;
 else if(pid==0x0C&&n>=2){telem.rpm=((uint16_t)d[0]*256+d[1])/4.0f;rpmUpdated=millis();rpmValid=true;}
 else if(pid==0x0D)telem.speed=d[0];
 else if(pid==0x2F)telem.fuel=d[0]*100.0f/255.0f;
 else if(pid==0x42&&n>=2)telem.voltage=((uint16_t)d[0]*256+d[1])/1000.0f;
}
static void pollVehicle(){
 if(millis()-lastPoll<KR_OBD_POLL_MS)return;lastPoll=millis();
 if(telem.proto==PROTO_NONE){autoDetect();return;}
 static const uint8_t pids[]={0x0C,0x0D,0x0C,0x05,0x0D,0x2F,0x0C,0x42};
 uint8_t pid=pids[pidIndex++%(sizeof(pids)/sizeof(pids[0]))],d[4];size_t n=0;
 if(readPid(pid,d,n)){busFails=0;telem.ecuConnected=true;decodePid(pid,d,n);}
 else {if(pid==0x0C)rpmValid=false;if(++busFails>=14){disconnectBus();bleOut("ECU:NOT_FOUND");}}
}
// Only this task owns the sensor and buzzer; no BLE, Wi-Fi or ECU calls here.
static void waterTask(void*){
 bool on=false;uint32_t buzzerAt=millis();
 for(;;){
  if(waterGuard.due()){
   KRWaterState w=waterGuard.update();
   portENTER_CRITICAL(&waterMux);waterSnapshot=w;waterHeartbeat=millis();portEXIT_CRITICAL(&waterMux);
  }
  if(KR_BUZZER_PIN>=0){
   if(!waterGuard.state().noWater){on=false;digitalWrite(KR_BUZZER_PIN,LOW);}
   else if(millis()-buzzerAt>=(on?230u:120u)){buzzerAt=millis();on=!on;digitalWrite(KR_BUZZER_PIN,on?HIGH:LOW);}
  }
  vTaskDelay(pdMS_TO_TICKS(10));
 }
}
static void waterTick(){
 KRWaterState w;uint32_t heartbeat;
 portENTER_CRITICAL(&waterMux);w=waterSnapshot;heartbeat=waterHeartbeat;portEXIT_CRITICAL(&waterMux);
 if(!waterTaskHandle||millis()-heartbeat>750){w.noWater=true;w.sensorFault=true;}
 bool old=telem.noWater;
 telem.waterLevel=w.level;telem.noWater=w.noWater;telem.waterSensorFault=w.sensorFault;
 if(telem.noWater&&!old)bleOut("ALARM:LOW_COOLANT,"+String(telem.waterLevel,0));
 else if(!telem.noWater&&old)bleOut("COOLANT:OK");
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
static void startOta(){bleOut("DENY|OTA_DISABLED");}
static bool mayMutate(){
 if(!KR_ENABLE_MUTATING_COMMANDS){bleOut("DENY|BENCH_READ_ONLY");return false;}
 if(!KR_HARDWARE_VERIFIED || !telem.ecuConnected || telem.proto==PROTO_NONE ||
    !kr::freshStopped(telem.rpm,rpmValid,millis()-rpmUpdated)){
  bleOut("DENY|RPM_UNKNOWN_OR_RUNNING");return false;
 }
 return true;
}
static void handle(String s){
 s.trim();if(!s.length())return;
 if(s=="GET_SERIAL"){bleOut("S|"+serialNumber());return;}
 if(s=="PING"){bleOut("PONG|"+String(millis()));return;}
 if(s=="STATUS"){bleOut("ST|"+String(protoName(telem.proto))+"|"+String(telem.ecuConnected?1:0)+"|WIFI:"+String(wifiEnabled?1:0));return;}
 if(s=="BUS?"){bleOut("BUS|"+String(protoName(telem.proto)));return;}
 if(s=="BUS|OFF"){requestedProtocol=PROTO_NONE;disconnectBus();bleOut("BUS|OFF");return;}
 if(s.startsWith("BUS|")){
  if(!KR_HARDWARE_VERIFIED||!waterTaskHandle){bleOut("DENY|HARDWARE_UNVERIFIED");return;}
  KRProtocol p=PROTO_NONE;
  if(s=="BUS|CAN500")p=PROTO_CAN500;else if(s=="BUS|CAN250")p=PROTO_CAN250;
  else if(s=="BUS|KLINE_FAST")p=PROTO_KLINE_FAST;else if(s=="BUS|KLINE_5BAUD")p=PROTO_KLINE_5BAUD;
  if(p==PROTO_NONE){bleOut("DENY|PROTOCOL");return;}
  disconnectBus();requestedProtocol=p;lastDetect=millis()-3000;autoDetect();return;
 }
 if(s=="REDETECT"){disconnectBus();lastDetect=millis()-3000;bleOut("ECU:SCAN,SELECTED");return;}
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
  if(!mayMutate())return;bool ok=false;
  if(telem.proto==PROTO_CAN500||telem.proto==PROTO_CAN250)ok=canObd.clearDtcs();
  else if(telem.proto==PROTO_KLINE_FAST||telem.proto==PROTO_KLINE_5BAUD)ok=klineObd.clearDtcs();
  bleOut(ok?"CLEAR_DTC:SENT":"CLEAR_DTC:FAIL");return;
 }
 if(s=="ECU|RESET"){
  if(!mayMutate())return;bool ok=false;
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
static void resetFragments(){fragments.clear();}
static void acceptCommand(const String& s){
 if(!s.startsWith("Y|")){resetFragments();handle(s);return;}
 auto result=fragments.feed(s.c_str(),s.length(),millis());
 if(result==kr::FragmentResult::Invalid){bleOut("DENY|RX_FRAGMENT");return;}
 if(result==kr::FragmentResult::Complete){String full=fragments.value();resetFragments();handle(full);}
}
static void processCommands(){
 uint32_t epoch=connectionEpoch.load();
 if(epoch!=handledEpoch){resetFragments();handledEpoch=epoch;}
 if(fragments.expired(millis(),KR_FRAGMENT_TIMEOUT_MS)){resetFragments();bleOut("DENY|RX_TIMEOUT");}
 if(droppedCommands.exchange(0)){resetFragments();if(commandQueue)xQueueReset(commandQueue);bleOut("DENY|RX_QUEUE");}
 KRCommand command;
 if(commandQueue && xQueueReceive(commandQueue,&command,0)==pdTRUE &&
    bleConnected.load() && command.epoch==connectionEpoch.load())acceptCommand(String(command.text));
}
class ServerCB:public NimBLEServerCallbacks{
 void onConnect(NimBLEServer*,NimBLEConnInfo&)override{connectionEpoch.fetch_add(1);bleConnected=true;}
 void onDisconnect(NimBLEServer*,NimBLEConnInfo&,int)override{bleConnected=false;connectionEpoch.fetch_add(1);NimBLEDevice::startAdvertising();}
};
class CmdCB:public NimBLECharacteristicCallbacks{
 void onWrite(NimBLECharacteristic*c,NimBLEConnInfo&)override{
  auto value=c->getValue();
  if(!commandQueue||value.size()==0||value.size()>KR_COMMAND_MAX_BYTES||
     memchr(value.data(),0,value.size())){droppedCommands.fetch_add(1);return;}
  KRCommand command={};command.epoch=connectionEpoch.load();
  memcpy(command.text,value.data(),value.size());
  if(xQueueSend(commandQueue,&command,0)!=pdTRUE)droppedCommands.fetch_add(1);
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
 if(KR_BUZZER_PIN>=0){pinMode(KR_BUZZER_PIN,OUTPUT);digitalWrite(KR_BUZZER_PIN,LOW);}
 waterGuard.begin();
 xTaskCreate(waterTask,"krWater",3072,nullptr,2,&waterTaskHandle);
 commandQueue=xQueueCreate(8,sizeof(KRCommand));
 telem.voltage=batteryVoltage();setupBle();loadNet();
 if(wifiEnabled)wifiSaved(false);else{WiFi.mode(WIFI_OFF);WiFi.setAutoReconnect(false);}
 Serial.println("BOOT|KHANEH_REMAP|SMART_OBD|"+String(FW_VERSION));
}
void loop(){
 waterTick();processCommands();
 static uint32_t bat=0;if(millis()-bat>1000){bat=millis();float v=batteryVoltage();if(!isnan(v)&&(isnan(telem.voltage)||telem.proto==PROTO_NONE))telem.voltage=v;}
 pollVehicle();telemetryTick();
 if(wifiEnabled&&configuredSsid.length()&&WiFi.status()!=WL_CONNECTED&&millis()-lastWifiRetry>15000){lastWifiRetry=millis();wifiSaved(false);}
 delay(1);
}
