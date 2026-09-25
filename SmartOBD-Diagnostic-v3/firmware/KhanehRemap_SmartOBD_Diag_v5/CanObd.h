#pragma once
#include <Arduino.h>
#include "driver/twai.h"
#include "BoardConfig.h"

class KRCanObd {
 public:
  bool begin(uint32_t bitrate) {
    stop();
    twai_general_config_t g = TWAI_GENERAL_CONFIG_DEFAULT(
      (gpio_num_t)KR_CAN_TX_PIN, (gpio_num_t)KR_CAN_RX_PIN, TWAI_MODE_NORMAL);
    g.tx_queue_len = 8; g.rx_queue_len = 28;
    g.alerts_enabled = TWAI_ALERT_BUS_OFF | TWAI_ALERT_ERR_PASS | TWAI_ALERT_RX_QUEUE_FULL;
    twai_timing_config_t t = TWAI_TIMING_CONFIG_500KBITS();
    if (bitrate == 250000) {
      twai_timing_config_t t250 = TWAI_TIMING_CONFIG_250KBITS();
      t = t250;
    }
    bitrate_ = bitrate == 250000 ? 250000 : 500000;
    twai_filter_config_t f = TWAI_FILTER_CONFIG_ACCEPT_ALL();
    if (twai_driver_install(&g, &t, &f) != ESP_OK) return false;
    installed_ = true;
    if (twai_start() != ESP_OK) { stop(); return false; }
    started_ = true; return true;
  }

  void stop() {
    if (started_) { twai_stop(); started_ = false; }
    if (installed_) { twai_driver_uninstall(); installed_ = false; }
  }
  bool started() const { return started_; }
  uint32_t bitrate() const { return bitrate_; }

  bool requestPid(uint8_t pid, uint8_t* out, size_t& outLen, uint32_t timeoutMs = 55) {
    uint8_t req[2] = {0x01, pid};
    uint8_t buf[64]; size_t n=0;
    if(!requestPayload(req,2,0x41,buf,n,timeoutMs,false)) return false;
    if(n < 2 || buf[0] != 0x41 || buf[1] != pid) return false;
    outLen = min<size_t>(4,n-2);
    for(size_t i=0;i<outLen;i++) out[i]=buf[i+2];
    return outLen>0;
  }

  bool readDtcs(String& out) {
    uint8_t req[1]={0x03}, buf[128]; size_t n=0;
    if(!requestPayload(req,1,0x43,buf,n,350,false)) return false;
    out="";
    if(n<1 || buf[0]!=0x43) return false;
    for(size_t i=1;i+1<n;i+=2){
      uint8_t a=buf[i],b=buf[i+1];
      if(a==0 && b==0) continue;
      char fam="PCBU"[(a>>6)&0x03];
      char code[6];
      snprintf(code,sizeof(code),"%c%u%X%X%X",fam,(a>>4)&0x03,a&0x0F,(b>>4)&0x0F,b&0x0F);
      if(out.length()) out+=','; out+=code;
    }
    return true;
  }

  bool clearDtcs(){
    uint8_t req[1]={0x04}, buf[16]; size_t n=0;
    return requestPayload(req,1,0x44,buf,n,300,false) && n && buf[0]==0x44;
  }

  bool readVin(String& vin){
    uint8_t req[2]={0x09,0x02}, buf[128]; size_t n=0;
    if(!requestPayload(req,2,0x49,buf,n,500,false)) return false;
    vin="";
    if(n<3 || buf[0]!=0x49 || buf[1]!=0x02) return false;
    size_t i=2;
    if(i<n && buf[i]<=0x0F) i++;
    for(;i<n;i++) if(buf[i]>=32 && buf[i]<=126) vin+=(char)buf[i];
    vin.trim(); return vin.length()>=8;
  }

  bool readCalibrationId(String& cal){
    uint8_t req[2]={0x09,0x04}, buf[128]; size_t n=0;
    if(!requestPayload(req,2,0x49,buf,n,500,false)) return false;
    cal="";
    if(n<3 || buf[0]!=0x49 || buf[1]!=0x04) return false;
    size_t i=2; if(i<n && buf[i]<=0x0F) i++;
    for(;i<n;i++) if(buf[i]>=32 && buf[i]<=126) cal+=(char)buf[i];
    cal.trim(); return cal.length()>0;
  }

  bool ecuReset(){
    uint8_t req[2]={0x11,0x01}, buf[32]; size_t n=0;
    return requestPayload(req,2,0x51,buf,n,450,true) && n>=2 && buf[0]==0x51;
  }

 private:
  bool installed_=false, started_=false; uint32_t bitrate_=0;

  bool requestPayload(const uint8_t* payload,uint8_t payloadLen,uint8_t expectedSid,
                      uint8_t* out,size_t& outLen,uint32_t timeoutMs,bool physical){
    outLen=0; if(!started_ || payloadLen<1 || payloadLen>7) return false;
    twai_message_t stale; while(twai_receive(&stale,0)==ESP_OK){}
    twai_message_t tx={}; tx.identifier=physical?0x7E0:0x7DF; tx.extd=0; tx.rtr=0; tx.data_length_code=8;
    tx.data[0]=payloadLen; for(uint8_t i=0;i<payloadLen;i++) tx.data[i+1]=payload[i];
    for(uint8_t i=payloadLen+1;i<8;i++) tx.data[i]=0;
    if(twai_transmit(&tx,pdMS_TO_TICKS(30))!=ESP_OK) return false;
    uint32_t start=millis(); uint32_t responder=0;
    bool multi=false; size_t total=0;
    while(millis()-start<timeoutMs){
      twai_message_t rx={}; if(twai_receive(&rx,pdMS_TO_TICKS(5))!=ESP_OK) continue;
      if(rx.extd||rx.rtr||rx.data_length_code<2) continue;
      if(rx.identifier<0x7E8||rx.identifier>0x7EF) continue;
      uint8_t pci=rx.data[0]>>4;
      if(pci==0){
        uint8_t len=rx.data[0]&0x0F; if(len<1||len>7) continue;
        if(rx.data[1]!=expectedSid) continue;
        outLen=min<size_t>(len,127); for(size_t i=0;i<outLen;i++) out[i]=rx.data[i+1];
        return true;
      }
      if(pci==1){
        total=((rx.data[0]&0x0F)<<8)|rx.data[1]; if(total<1||total>127) return false;
        if(rx.data[2]!=expectedSid) continue;
        responder=rx.identifier; outLen=0;
        for(int i=2;i<8 && outLen<total;i++) out[outLen++]=rx.data[i];
        twai_message_t fc={}; fc.identifier=responder-8; fc.extd=0; fc.rtr=0; fc.data_length_code=8;
        fc.data[0]=0x30; fc.data[1]=0; fc.data[2]=0; for(int i=3;i<8;i++) fc.data[i]=0;
        twai_transmit(&fc,pdMS_TO_TICKS(20)); multi=true; break;
      }
    }
    if(!multi) return false;
    uint8_t nextSeq=1;
    while(millis()-start<timeoutMs && outLen<total){
      twai_message_t rx={}; if(twai_receive(&rx,pdMS_TO_TICKS(6))!=ESP_OK) continue;
      if(rx.identifier!=responder || rx.extd || rx.rtr) continue;
      if((rx.data[0]>>4)!=2) continue;
      if((rx.data[0]&0x0F)!=(nextSeq&0x0F)) continue;
      nextSeq++;
      for(int i=1;i<8 && outLen<total;i++) out[outLen++]=rx.data[i];
    }
    return outLen>=1 && out[0]==expectedSid;
  }
};
