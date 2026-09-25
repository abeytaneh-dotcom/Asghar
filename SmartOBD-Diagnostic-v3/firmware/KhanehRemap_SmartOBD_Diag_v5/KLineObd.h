#pragma once
#include <Arduino.h>
#include "BoardConfig.h"

class KRKLineObd {
 public:
  enum InitMode { NONE=0, FAST=1, FIVE_BAUD=2 };
  KRKLineObd():uart_(1){}
  void stop(){uart_.end();mode_=NONE;pinMode(KR_KLINE_TX_PIN,OUTPUT);digitalWrite(KR_KLINE_TX_PIN,HIGH);}
  bool beginFast(){
    stop(); pinMode(KR_KLINE_TX_PIN,OUTPUT); digitalWrite(KR_KLINE_TX_PIN,HIGH); delay(320);
    digitalWrite(KR_KLINE_TX_PIN,LOW); delay(25); digitalWrite(KR_KLINE_TX_PIN,HIGH); delay(25);
    startUart(); flushRx();
    uint8_t req[5]={0xC1,0x33,0xF1,0x81,0}; req[4]=checksum(req,4);
    uart_.write(req,sizeof(req)); uart_.flush();
    uint8_t rx[32]; size_t n=readFrame(rx,sizeof(rx),350);
    bool ok=false; for(size_t i=0;i<n;i++) if(rx[i]==0xC1 || rx[i]==0x81){ok=true;break;}
    if(!ok){stop();return false;} mode_=FAST; return true;
  }
  bool beginFiveBaud(){
    stop();pinMode(KR_KLINE_TX_PIN,OUTPUT);digitalWrite(KR_KLINE_TX_PIN,HIGH);delay(320);
    send5BaudByte(0x33);startUart();uint8_t init[3]={};size_t got=0;uint32_t t=millis();
    while(millis()-t<1300&&got<3){if(uart_.available())init[got++]=(uint8_t)uart_.read();else delay(1);}
    if(got<3||init[0]!=0x55){stop();return false;}delay(28);uart_.write((uint8_t)~init[2]);uart_.flush();delay(55);flushRx();mode_=FIVE_BAUD;return true;
  }
  InitMode mode()const{return mode_;}

  bool requestPid(uint8_t pid,uint8_t* out,size_t& outLen,uint32_t timeoutMs=170){
    uint8_t req[2]={0x01,pid},buf[64];size_t n=0;
    if(!requestService(req,2,0x41,buf,sizeof(buf),n,timeoutMs))return false;
    int pos=find(buf,n,0x41,pid);if(pos<0)return false;outLen=min<size_t>(4,n-(pos+2));for(size_t i=0;i<outLen;i++)out[i]=buf[pos+2+i];return outLen>0;
  }
  bool readDtcs(String& out){
    uint8_t req[1]={0x03},buf[128];size_t n=0;if(!requestService(req,1,0x43,buf,sizeof(buf),n,420))return false;
    int pos=findSid(buf,n,0x43);if(pos<0)return false;out="";
    for(size_t i=pos+1;i+1<n;i+=2){uint8_t a=buf[i],b=buf[i+1];if(!a&&!b)continue;char fam="PCBU"[(a>>6)&3];char c[6];snprintf(c,sizeof(c),"%c%u%X%X%X",fam,(a>>4)&3,a&15,(b>>4)&15,b&15);if(out.length())out+=',';out+=c;}return true;
  }
  bool clearDtcs(){uint8_t req[1]={0x04},buf[32];size_t n=0;return requestService(req,1,0x44,buf,sizeof(buf),n,350)&&findSid(buf,n,0x44)>=0;}
  bool readVin(String& vin){uint8_t req[2]={0x09,0x02},buf[160];size_t n=0;if(!requestService(req,2,0x49,buf,sizeof(buf),n,600))return false;int p=find(buf,n,0x49,0x02);if(p<0)return false;vin="";for(size_t i=p+2;i<n;i++)if(buf[i]>=32&&buf[i]<=126)vin+=(char)buf[i];vin.trim();return vin.length()>=8;}
  bool readCalibrationId(String& cal){uint8_t req[2]={0x09,0x04},buf[160];size_t n=0;if(!requestService(req,2,0x49,buf,sizeof(buf),n,600))return false;int p=find(buf,n,0x49,0x04);if(p<0)return false;cal="";for(size_t i=p+2;i<n;i++)if(buf[i]>=32&&buf[i]<=126)cal+=(char)buf[i];cal.trim();return cal.length()>0;}
  bool ecuReset(){uint8_t req[2]={0x11,0x01},buf[48];size_t n=0;return requestService(req,2,0x51,buf,sizeof(buf),n,450)&&findSid(buf,n,0x51)>=0;}

 private:
  HardwareSerial uart_;InitMode mode_=NONE;
  static uint8_t checksum(const uint8_t*b,size_t n){uint16_t s=0;for(size_t i=0;i<n;i++)s+=b[i];return(uint8_t)(s&0xFF);}
  void startUart(){uart_.begin(10400,SERIAL_8N1,KR_KLINE_RX_PIN,KR_KLINE_TX_PIN);delay(10);}void flushRx(){while(uart_.available())uart_.read();}
  size_t readFrame(uint8_t*out,size_t cap,uint32_t total){size_t n=0;uint32_t st=millis(),last=st;while(millis()-st<total){while(uart_.available()){int c=uart_.read();if(c>=0&&n<cap)out[n++]=(uint8_t)c;last=millis();}if(n&&millis()-last>28)break;delay(1);}return n;}
  void send5BaudByte(uint8_t b){const uint32_t bit=200;digitalWrite(KR_KLINE_TX_PIN,LOW);delay(bit);for(int i=0;i<8;i++){digitalWrite(KR_KLINE_TX_PIN,(b>>i)&1?HIGH:LOW);delay(bit);}digitalWrite(KR_KLINE_TX_PIN,HIGH);delay(bit);}
  int findSid(const uint8_t*r,size_t n,uint8_t sid){for(size_t i=0;i<n;i++)if(r[i]==sid)return(int)i;return-1;}
  int find(const uint8_t*r,size_t n,uint8_t sid,uint8_t sub){for(size_t i=0;i+1<n;i++)if(r[i]==sid&&r[i+1]==sub)return(int)i;return-1;}
  bool requestService(const uint8_t*payload,uint8_t len,uint8_t expected,uint8_t*out,size_t cap,size_t&outLen,uint32_t timeout){
    if(mode_==NONE||len<1||len>7)return false;flushRx();uint8_t req[16]={};size_t k=0;
    req[k++]=(mode_==FAST)?(uint8_t)(0xC0|len):0x68;req[k++]=(mode_==FAST)?0x33:0x6A;req[k++]=0xF1;for(uint8_t i=0;i<len;i++)req[k++]=payload[i];req[k++]=checksum(req,k);uart_.write(req,k);uart_.flush();outLen=readFrame(out,cap,timeout);return outLen>0&&findSid(out,outLen,expected)>=0;
  }
};
