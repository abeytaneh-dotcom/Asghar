#include <assert.h>
#include <stdio.h>
#include <random>
#include "../firmware/KhanehRemap_SmartOBD_Diag_v5/SafeProtocol.h"
#include "../firmware/KhanehRemap_SmartOBD_Diag_v5/WaterGuard.h"
static uint32_t clockMs=0;
static uint16_t lowRaw=0,highRaw=1400;
static bool excite=false;
uint32_t millis(){return clockMs;}
void pinMode(int,int){}
void digitalWrite(int pin,int state){if(pin==KR_WATER_EXCITE_PIN)excite=state;}
void delayMicroseconds(uint32_t){}
uint16_t analogRead(int){return excite?highRaw:lowRaw;}
using kr::IsoResult;
static void isotpTests(){
  uint8_t guarded[18];memset(guarded,0xA5,sizeof(guarded));
  kr::IsoTpRx rx(guarded+1,16,0x49);
  uint8_t oversized[]={0x10,17,0x49,2,1,1,2,3};
  assert(rx.feed(oversized,8)==IsoResult::Invalid);
  for(uint8_t b:guarded)assert(b==0xA5);
  uint8_t out[128]={};
  kr::IsoTpRx sf(out,sizeof(out),0x41);
  uint8_t frame[]={3,0x41,0x0D,88};
  assert(sf.feed(frame,3)==IsoResult::Invalid); // truncated DLC
  kr::IsoTpRx complete(out,sizeof(out),0x41);
  assert(complete.feed(frame,4)==IsoResult::Complete && complete.size()==3 && out[2]==88);
  kr::IsoTpRx wrongSid(out,sizeof(out),0x43);
  assert(wrongSid.feed(frame,4)==IsoResult::Ignored);
  uint8_t ff[]={0x10,20,0x49,2,1,'A','B','C'};
  uint8_t cf1[]={0x21,'D','E','F','G','H','I','J'};
  uint8_t cf2[]={0x22,'K','L','M','N','O','P','Q'};
  kr::IsoTpRx vin(out,sizeof(out),0x49);
  assert(vin.feed(ff,8)==IsoResult::More && vin.size()==6);
  assert(vin.feed(cf1,8)==IsoResult::More);
  assert(vin.feed(cf2,8)==IsoResult::Complete && vin.size()==20);
  kr::IsoTpRx badSeq(out,sizeof(out),0x49);
  assert(badSeq.feed(ff,8)==IsoResult::More);
  assert(badSeq.feed(cf2,8)==IsoResult::Invalid);
  kr::IsoTpRx shortCf(out,sizeof(out),0x49);
  assert(shortCf.feed(ff,8)==IsoResult::More);
  assert(shortCf.feed(cf1,2)==IsoResult::Invalid);
  // Exercise sequence wrap with a full 127-byte response.
  uint8_t longff[]={0x10,127,0x49,0,0,0,0,0};
  kr::IsoTpRx longRx(out,127,0x49);
  assert(longRx.feed(longff,8)==IsoResult::More);
  for(int seq=1;seq<=18;seq++){
    uint8_t cf[8]={uint8_t(0x20|(seq&15)),1,2,3,4,5,6,7};
    assert(longRx.feed(cf,8)==(seq==18?IsoResult::Complete:IsoResult::More));
  }
  assert(longRx.size()==127);
  std::mt19937 rng(12);
  for(int i=0;i<100000;i++){
    memset(guarded,0xA5,sizeof(guarded));
    kr::IsoTpRx fuzz(guarded+1,16,0x49);
    for(int f=0;f<4;f++){
      uint8_t bytes[8];for(auto& b:bytes)b=rng();
      fuzz.feed(bytes,rng()%10);
    }
    assert(guarded[0]==0xA5 && guarded[17]==0xA5);
  }
}
static void klineTests(){
  size_t offset=0,count=0;
  const uint8_t dtcs[]={0x43,1,0x01,0x33};
  assert(kr::dtcLayout(dtcs,4,true,offset,count)&&offset==2&&count==1);
  assert(!kr::dtcLayout(dtcs,3,true,offset,count));
  const uint8_t legacy[]={0x43,0x01,0x33,0,0};
  assert(kr::dtcLayout(legacy,5,false,offset,count)&&offset==1&&count==2);
  uint8_t out[32];size_t n=99;
  uint8_t frame[]={0x83,0xF1,0x10,0x41,0x0D,88,0};
  frame[6]=kr::checksum(frame,6);
  assert(kr::klineFrame(frame,7,true,0x10,out,32,n)&&n==3&&out[2]==88);
  assert(!kr::klineFrame(frame,7,true,0x11,out,32,n)&&n==0);
  assert(!kr::klineFrame(frame,7,true,0x10,out,2,n));
  assert(!kr::klineFrame(frame,6,true,0x10,out,32,n));
  frame[6]^=1;assert(!kr::klineFrame(frame,7,true,0x10,out,32,n));
  uint8_t echo[]={0xC1,0x33,0xF1,0x81,0x66};
  assert(!kr::klineFrame(echo,5,true,0x10,out,32,n));
  uint8_t iso[]={0x48,0x6B,0x10,0x43,0x01,0x33,0};
  iso[6]=kr::checksum(iso,6);
  assert(kr::klineFrame(iso,7,false,0x10,out,32,n)&&n==3);
}
static void fragmentTests(){
  kr::CommandFragments<32> rx;
  const char* a="Y|01|00|02|E|ECU|";
  const char* b="Y|01|01|02|J34";
  assert(rx.feed(a,strlen(a),0)==kr::FragmentResult::More);
  assert(rx.feed(b,strlen(b),100)==kr::FragmentResult::Complete);
  assert(strcmp(rx.value(),"E|ECU|J34")==0);
  rx.clear();assert(rx.feed(b,strlen(b),200)==kr::FragmentResult::Invalid);
  assert(rx.feed(a,strlen(a),300)==kr::FragmentResult::More);
  assert(rx.expired(2301,2000));
  rx.clear();
  const char* bad="Y|GG|00|01|OTA";
  assert(rx.feed(bad,strlen(bad),0)==kr::FragmentResult::Invalid);
  const char* zero="Y|01|00|00|OTA";
  assert(rx.feed(zero,strlen(zero),0)==kr::FragmentResult::Invalid);
  const char* huge="Y|01|00|01|123456789012345678901234567890123";
  assert(rx.feed(huge,strlen(huge),0)==kr::FragmentResult::Invalid);
}
static void waterTests(){
  KRWaterGuard water;water.begin();
  for(int i=0;i<300;i++){clockMs+=180;water.update();}
  assert(!water.state().noWater); // counter remains saturated after 255
  highRaw=0;
  for(int i=0;i<2;i++){clockMs+=180;assert(!water.update().noWater);}
  clockMs+=180;assert(water.update().noWater);
  assert(!water.state().sensorFault); // dry/open is a low-water alarm
  lowRaw=4095;highRaw=4095;
  for(int i=0;i<300;i++){clockMs+=180;water.update();}
  assert(water.state().noWater && water.state().sensorFault);
  lowRaw=0;highRaw=1400;
  for(int i=0;i<9;i++){clockMs+=180;assert(water.update().noWater);}
  clockMs+=180;assert(!water.update().noWater);
  assert(kr::saturatingIncrement(255)==255);
  assert(kr::freshStopped(0,true,1000));
  assert(!kr::freshStopped(0,false,0));
  assert(!kr::freshStopped(0,true,1001));
  assert(!kr::freshStopped(1000,true,0));
}
int main(){
  isotpTests();klineTests();fragmentTests();waterTests();
  puts("PASS: ISO-TP bounds/sequence/fuzz, K-Line validation, BLE fragments, WaterGuard, RPM freshness");
}
