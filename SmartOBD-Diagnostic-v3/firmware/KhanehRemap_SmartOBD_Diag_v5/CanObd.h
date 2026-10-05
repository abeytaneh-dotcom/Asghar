#pragma once
#include <Arduino.h>
#include "driver/twai.h"
#include "BoardConfig.h"
#include "SafeProtocol.h"

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
    outLen=0;
    uint8_t req[2] = {0x01, pid};
    uint8_t buf[64]; size_t n=0;
    if(!requestPayload(req,2,0x41,buf,sizeof(buf),n,timeoutMs,false)) return false;
    if(n < 3 || n > 6 || buf[0] != 0x41 || buf[1] != pid) return false;
    outLen = min<size_t>(4,n-2);
    for(size_t i=0;i<outLen;i++) out[i]=buf[i+2];
    return outLen>0;
  }

  bool readDtcs(String& out) {
    uint8_t req[1]={0x03}, buf[128]; size_t n=0;
    if(!requestPayload(req,1,0x43,buf,sizeof(buf),n,350,false)) return false;
    out="";
    // ISO15765 Mode 03 includes a DTC-count byte (ELM327 datasheet p.34).
    size_t offset=0,count=0;
    if(!kr::dtcLayout(buf,n,true,offset,count))return false;
    for(size_t i=offset;i<offset+count*2;i+=2){
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
    return requestPayload(req,1,0x44,buf,sizeof(buf),n,300,false) && n==1 && buf[0]==0x44;
  }

  bool readVin(String& vin){
    uint8_t req[2]={0x09,0x02}, buf[128]; size_t n=0;
    if(!requestPayload(req,2,0x49,buf,sizeof(buf),n,500,false)) return false;
    vin="";
    if(n!=20 || buf[0]!=0x49 || buf[1]!=0x02 || buf[2]!=1) return false;
    for(size_t i=3;i<n;i++){if(buf[i]<32||buf[i]>126)return false;vin+=(char)buf[i];}
    vin.trim(); return vin.length()==17;
  }

  bool readCalibrationId(String& cal){
    uint8_t req[2]={0x09,0x04}, buf[128]; size_t n=0;
    if(!requestPayload(req,2,0x49,buf,sizeof(buf),n,500,false)) return false;
    cal="";
    if(n!=19 || buf[0]!=0x49 || buf[1]!=0x04 || buf[2]!=1) return false;
    for(size_t i=3;i<n;i++){if(!buf[i])continue;if(buf[i]<32||buf[i]>126)return false;cal+=(char)buf[i];}
    cal.trim(); return cal.length()>0;
  }

  bool ecuReset(){
    uint8_t req[2]={0x11,0x01}, buf[32]; size_t n=0;
    return requestPayload(req,2,0x51,buf,sizeof(buf),n,450,true) && n==2 && buf[0]==0x51 && buf[1]==0x01;
  }

 private:
  bool installed_=false, started_=false; uint32_t bitrate_=0;

  bool requestPayload(const uint8_t* payload, uint8_t payloadLen, uint8_t expectedSid,
                      uint8_t* out, size_t capacity, size_t& outLen,
                      uint32_t timeoutMs, bool physical) {
    outLen = 0;
    if (!started_ || !out || !capacity || !payloadLen || payloadLen > 7) return false;
    twai_status_info_t status = {};
    if (twai_get_status_info(&status) != ESP_OK || status.state != TWAI_STATE_RUNNING) return false;
    // Bound draining even on a busy vehicle network.
    twai_message_t stale;
    for (unsigned i = 0; i < 28 && twai_receive(&stale, 0) == ESP_OK; ++i) {}
    twai_message_t tx = {};
    tx.identifier = physical ? 0x7E0 : 0x7DF;
    tx.data_length_code = 8; tx.data[0] = payloadLen;
    memcpy(tx.data + 1, payload, payloadLen);
    if (twai_transmit(&tx, pdMS_TO_TICKS(30)) != ESP_OK) return false;
    kr::IsoTpRx assembler(out, capacity, expectedSid);
    uint32_t start = millis(), responder = 0;
    while (millis() - start < timeoutMs) {
      twai_message_t rx = {};
      if (twai_receive(&rx, pdMS_TO_TICKS(5)) != ESP_OK) continue;
      if (rx.extd || rx.rtr || rx.identifier < 0x7E8 || rx.identifier > 0x7EF) continue;
      if (physical && rx.identifier != 0x7E8) continue;
      if (responder && rx.identifier != responder) continue;
      auto result = assembler.feed(rx.data, rx.data_length_code);
      if (result == kr::IsoResult::Ignored) continue;
      if (result == kr::IsoResult::Invalid) return false;
      if (result == kr::IsoResult::Complete) { outLen = assembler.size(); return true; }
      if (!responder) {
        responder = rx.identifier;
        twai_message_t fc = {}; fc.identifier = responder - 8;
        fc.data_length_code = 8; fc.data[0] = 0x30;
        // Pace consecutive frames rather than asking for an unbounded burst.
        fc.data[2] = 5;
        if (twai_transmit(&fc, pdMS_TO_TICKS(20)) != ESP_OK) return false;
      }
    }
    // Never expose a partial response after timeout.
    outLen = 0; return false;
  }
};
