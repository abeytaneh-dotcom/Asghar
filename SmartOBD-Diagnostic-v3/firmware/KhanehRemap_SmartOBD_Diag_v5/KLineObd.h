#pragma once
#include <Arduino.h>
#include "driver/uart.h"
#include "BoardConfig.h"
#include "SafeProtocol.h"

class KRKLineObd {
 public:
  enum InitMode { NONE=0, FAST=1, FIVE_BAUD=2 };
  KRKLineObd():uart_(1) {}
  void stop() {
    if (configured_) { uart_.end(); pinMode(KR_KLINE_TX_PIN, OUTPUT); drive(true); }
    mode_=NONE;
  }
  bool beginFast() {
    prepare(); drive(true); delay(320);
    drive(false); delay(25); drive(true);uint32_t highAt=micros();
    startUart(); flushRx();
    while(uint32_t(micros()-highAt)<25000)delayMicroseconds(100);
    const uint8_t request[]={0xC1,0x33,0xF1,0x81,0x66};
    writeBytes(request,sizeof(request));
    uint8_t raw[64],payload[32];size_t used=0;
    size_t n=readFrame(raw,sizeof(raw),350,request,sizeof(request));
    if (!kr::klineFrame(raw,n,true,KR_KLINE_ECU_ADDRESS,payload,sizeof(payload),used) ||
        used!=3 || payload[0]!=0xC1 || payload[1]!=0xE9 || payload[2]!=0x8F) { stop(); return false; }
    kwp_=true;mode_=FAST;return true;
  }
  bool beginFiveBaud() {
    prepare();drive(true);delay(320);send5BaudByte(0x33);startUart();
    uint8_t init[3]={};size_t got=readExact(init,3,1300);
    if(got!=3 || init[0]!=0x55 || !init[1] || !init[2]) { stop();return false; }
    kwp_=!((init[1]==0x08 && init[2]==0x08) || (init[1]==0x94 && init[2]==0x94));
    if(kwp_ && !(init[1]==0xE9 && init[2]==0x8F)){stop();return false;}
    delay(28);uint8_t complement=(uint8_t)~init[2];
    uart_.write(complement);uart_.flush();
    uint8_t ack=0;
    if(readExact(&ack,1,300)!=1) {stop();return false;}
    if(ack==complement && readExact(&ack,1,300)!=1) {stop();return false;}
    if(ack!=(uint8_t)~0x33) {stop();return false;}
    mode_=FIVE_BAUD;lastRequest_=millis();return true;
  }
  InitMode mode()const {return mode_;}
  bool requestPid(uint8_t pid,uint8_t* out,size_t& outLen,uint32_t timeoutMs=170) {
    outLen=0;uint8_t req[]={0x01,pid},buf[64];size_t n=0;
    if(!requestService(req,2,0x41,buf,sizeof(buf),n,timeoutMs) ||
       n<3 || n>6 || buf[1]!=pid) return false;
    outLen=n-2;memcpy(out,buf+2,outLen);return true;
  }
  bool readDtcs(String& out) {
    uint8_t req[]={0x03},buf[128];size_t n=0;
    if(!requestService(req,1,0x43,buf,sizeof(buf),n,420) || (n-1)%2) return false;
    out="";
    for(size_t i=1;i+1<n;i+=2) {
      uint8_t a=buf[i],b=buf[i+1];if(!a&&!b)continue;
      char code[6];snprintf(code,sizeof(code),"%c%u%X%X%X","PCBU"[(a>>6)&3],(a>>4)&3,a&15,(b>>4)&15,b&15);
      if(out.length())out+=',';out+=code;
    }
    return true;
  }
  bool clearDtcs() {
    uint8_t req[]={0x04},buf[32];size_t n=0;
    return requestService(req,1,0x44,buf,sizeof(buf),n,350) && n==1;
  }
  // Legacy Mode 09 responses spanning separate messages need ECU-specific assembly.
  // Reject them rather than returning a plausible but incomplete identifier.
  bool readVin(String& vin) {
    uint8_t req[]={0x09,0x02},buf[128];size_t n=0;vin="";
    if(!requestService(req,2,0x49,buf,sizeof(buf),n,600) || n!=20 || buf[1]!=2 || buf[2]!=1) return false;
    for(size_t i=3;i<n;i++) {if(buf[i]<32||buf[i]>126)return false;vin+=(char)buf[i];}
    return vin.length()==17;
  }
  bool readCalibrationId(String& cal) {
    uint8_t req[]={0x09,0x04},buf[128];size_t n=0;cal="";
    if(!requestService(req,2,0x49,buf,sizeof(buf),n,600) || n!=19 || buf[1]!=4 || buf[2]!=1) return false;
    for(size_t i=3;i<n;i++) {if(!buf[i])continue;if(buf[i]<32||buf[i]>126)return false;cal+=(char)buf[i];}
    return cal.length()>0;
  }
  bool ecuReset() {
    uint8_t req[]={0x11,0x01},buf[32];size_t n=0;
    return requestService(req,2,0x51,buf,sizeof(buf),n,450) && n==2 && buf[1]==1;
  }
 private:
  HardwareSerial uart_;InitMode mode_=NONE;bool configured_=false,kwp_=false;
  uint32_t lastRequest_=0;
  void drive(bool busHigh) {digitalWrite(KR_KLINE_TX_PIN,(busHigh != bool(KR_KLINE_TX_INVERTED))?HIGH:LOW);}
  void prepare() {stop();configured_=true;pinMode(KR_KLINE_TX_PIN,OUTPUT);}
  void send5BaudByte(uint8_t value) {
    drive(false);delay(200);
    for(unsigned i=0;i<8;i++){drive((value>>i)&1);delay(200);}
    drive(true);delay(200);
  }
  void startUart() {
    uart_.begin(10400,SERIAL_8N1,KR_KLINE_RX_PIN,KR_KLINE_TX_PIN);
    uart_set_line_inverse(UART_NUM_1,KR_KLINE_TX_INVERTED?UART_SIGNAL_TXD_INV:0);
  }
  void flushRx() {while(uart_.available())uart_.read();}
  size_t readExact(uint8_t* out,size_t count,uint32_t timeout) {
    size_t n=0;uint32_t start=millis();
    while(n<count && millis()-start<timeout) {
      if(uart_.available())out[n++]=(uint8_t)uart_.read();else delay(1);
    }
    return n;
  }
  void writeBytes(const uint8_t* data,size_t n) {
    for(size_t i=0;i<n;i++) {uart_.write(data[i]);uart_.flush();if(i+1<n)delay(5);}
    lastRequest_=millis();
  }
  size_t readFrame(uint8_t* out,size_t cap,uint32_t timeout,const uint8_t* echo,size_t echoLen) {
    size_t n=0;uint32_t start=millis(),last=start;bool echoRemoved=false;
    while(millis()-start<timeout) {
      while(uart_.available()) {
        int c=uart_.read();if(c<0)continue;if(n>=cap)return 0;
        out[n++]=(uint8_t)c;last=millis();
        if(!echoRemoved && n==echoLen && memcmp(out,echo,echoLen)==0) {n=0;echoRemoved=true;}
      }
      if(n && millis()-last>28)return n;
      delay(1);
    }
    return 0; // partial frame at timeout is invalid
  }
  bool requestService(const uint8_t* payload,uint8_t len,uint8_t expected,
                      uint8_t* out,size_t cap,size_t& used,uint32_t timeout) {
    used=0;if(mode_==NONE||!len||len>7)return false;
    // ISO9141/KWP inter-request quiet time, including the previous response.
    uint32_t elapsed=millis()-lastRequest_;if(elapsed<55)delay(55-elapsed);
    flushRx();uint8_t req[16];size_t n=0;
    req[n++]=kwp_?(0xC0|len):0x68;req[n++]=kwp_?0x33:0x6A;req[n++]=0xF1;
    memcpy(req+n,payload,len);n+=len;
    uint8_t sum=kr::checksum(req,n);req[n++]=sum;
    writeBytes(req,n);uint8_t raw[160];size_t rawLen=readFrame(raw,sizeof(raw),timeout,req,n);
    lastRequest_=millis();
    return kr::klineFrame(raw,rawLen,kwp_,KR_KLINE_ECU_ADDRESS,out,cap,used) && used && out[0]==expected;
  }
};
