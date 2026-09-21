#include "KLineOBD.h"
#include "config.h"
#include <math.h>

KLineOBD::KLineOBD(int rxPin,int txPin):serial(1),rx(rxPin),tx(txPin){}

void KLineOBD::begin(){
  pinMode(tx,OUTPUT); digitalWrite(tx,HIGH); pinMode(rx,INPUT);
  live=OBDLiveData{};
}
void KLineOBD::end(){ disconnect(); }

void KLineOBD::lineIdle(){
  serial.end(); pinMode(tx,OUTPUT); digitalWrite(tx,HIGH); delay(300);
}
void KLineOBD::flushInput(){ while(serial.available()) serial.read(); }

uint8_t KLineOBD::checksum(const uint8_t* d,size_t n){
  uint16_t s=0; for(size_t i=0;i<n;++i) s+=d[i]; return uint8_t(s&0xFF);
}

void KLineOBD::send5BaudByte(uint8_t b){
  pinMode(tx,OUTPUT);
  digitalWrite(tx,LOW); delay(200);
  for(int i=0;i<8;++i){ digitalWrite(tx,(b>>i)&1); delay(200); }
  digitalWrite(tx,HIGH); delay(200);
}

bool KLineOBD::init5Baud(){
  lineIdle(); send5BaudByte(0x33);
  serial.begin(10400,SERIAL_8N1,rx,tx);
  uint8_t b[3]={0}; uint32_t t0=millis(); int got=0;
  while(millis()-t0<1800 && got<3){ if(serial.available()) b[got++]=serial.read(); }
  if(got<3 || b[0]!=0x55){ serial.end(); return false; }
  delay(25); serial.write(uint8_t(~b[2])); serial.flush(); delay(60); flushInput();
  proto=ISO9141_5BAUD; live.connected=true; return true;
}

bool KLineOBD::initFast(){
  lineIdle();
  digitalWrite(tx,LOW); delay(25); digitalWrite(tx,HIGH); delay(25);
  serial.begin(10400,SERIAL_8N1,rx,tx); flushInput();
  uint8_t req[5]={0xC1,0x33,0xF1,0x81,0}; req[4]=checksum(req,4);
  serial.write(req,sizeof(req)); serial.flush();
  uint8_t r[32]; size_t n=0; uint32_t t0=millis(),last=t0;
  while(millis()-t0<350){
    while(serial.available() && n<sizeof(r)){ r[n++]=serial.read(); last=millis(); }
    if(n && millis()-last>35) break;
    delay(1);
  }
  bool ok=false;
  for(size_t i=0;i<n;++i) if(r[i]==0xC1 || r[i]==0x81){ ok=true; break; }
  if(!ok){ serial.end(); return false; }
  proto=KWP_FAST; live.connected=true; return true;
}

bool KLineOBD::connectAuto(){
  disconnect(); begin();
  if(!initFast() && !init5Baud()){ live.connected=false; proto=NONE; return false; }
  memset(supported,0,sizeof(supported));
  readSupportedBlock(0x00);
  if(supported[0x20]) readSupportedBlock(0x20);
  if(supported[0x40]) readSupportedBlock(0x40);
  return true;
}

void KLineOBD::disconnect(){
  serial.end(); pinMode(tx,OUTPUT); digitalWrite(tx,HIGH); live.connected=false; proto=NONE;
}

bool KLineOBD::sendRequest(uint8_t mode,uint8_t pid,uint8_t* resp,size_t& respLen,uint32_t timeoutMs){
  if(!live.connected) return false;
  flushInput();
  uint8_t req[6]={0x68,0x6A,0xF1,mode,pid,0}; req[5]=checksum(req,5);
  serial.write(req,sizeof(req)); serial.flush();
  respLen=0; uint32_t t0=millis(),lastByte=t0;
  while(millis()-t0<timeoutMs){
    while(serial.available() && respLen<64){ resp[respLen++]=serial.read(); lastByte=millis(); }
    if(respLen && millis()-lastByte>28) break;
    delay(1);
  }
  return respLen>=4;
}

int KLineOBD::findPayload(const uint8_t* r,size_t n,uint8_t modeResp,uint8_t pid){
  for(size_t i=0;i+1<n;++i) if(r[i]==modeResp && r[i+1]==pid) return int(i+2);
  return -1;
}

bool KLineOBD::readPid(uint8_t pid,uint8_t* payload,size_t& payloadLen){
  uint8_t r[64]; size_t n=0;
  if(!sendRequest(0x01,pid,r,n)) return false;
  int p=findPayload(r,n,0x41,pid); if(p<0) return false;
  payloadLen=0;
  for(size_t i=p;i<n && payloadLen<4;++i) payload[payloadLen++]=r[i];
  return payloadLen>0;
}

bool KLineOBD::readSupportedBlock(uint8_t basePid){
  uint8_t p[4]; size_t n=0;
  if(!readPid(basePid,p,n) || n<4) return false;
  uint32_t bits=(uint32_t(p[0])<<24)|(uint32_t(p[1])<<16)|(uint32_t(p[2])<<8)|p[3];
  for(int i=1;i<=32;++i){
    uint8_t pid=basePid+i;
    if(pid<sizeof(supported)) supported[pid]=(bits>>(32-i))&1;
  }
  return true;
}

void KLineOBD::decodePid(uint8_t pid,const uint8_t* p,size_t n){
  if(pid==0x0C && n>=2){ live.rpm=((256.0f*p[0])+p[1])/4.0f; live.rpmValid=true; }
  else if(pid==0x05 && n>=1){ live.coolantC=float(p[0])-40.0f; live.coolantValid=true; }
  else if(pid==0x06 && n>=1){ live.stft=(100.0f/128.0f)*p[0]-100.0f; live.stftValid=true; }
  else if(pid==0x07 && n>=1){ live.ltft=(100.0f/128.0f)*p[0]-100.0f; live.ltftValid=true; }
  else if(pid==0x14 && n>=2){
    live.o2Voltage=p[0]/200.0f; live.o2VoltageValid=true;
    live.stft=(100.0f/128.0f)*p[1]-100.0f; live.stftValid=true;
  }
  else if((pid>=0x24 && pid<=0x2B) && n>=4){
    uint16_t ab=(uint16_t(p[0])<<8)|p[1];
    float lam=2.0f*ab/65536.0f;
    if(lam>0.4f && lam<2.5f){ live.lambda=lam; live.afrGasoline=lam*14.7f; live.widebandValid=true; }
  }
  else if((pid>=0x34 && pid<=0x3B) && n>=4){
    uint16_t ab=(uint16_t(p[0])<<8)|p[1];
    float lam=2.0f*ab/65536.0f;
    if(lam>0.4f && lam<2.5f){ live.lambda=lam; live.afrGasoline=lam*14.7f; live.widebandValid=true; }
  }
  else if(pid==0x44 && n>=2){
    uint16_t ab=(uint16_t(p[0])<<8)|p[1];
    live.commandedLambda=2.0f*ab/65536.0f;
    live.commandedLambdaValid=live.commandedLambda>0.4f && live.commandedLambda<2.5f;
  }
  live.lastUpdateMs=millis();
}

bool KLineOBD::poll(){
  if(!live.connected || millis()-lastPollMs<OBD_POLL_PERIOD_MS) return false;
  lastPollMs=millis();
  static const uint8_t preferred[]={0x0C,0x05,0x06,0x07,0x14,0x24,0x34,0x44};
  for(size_t tries=0;tries<sizeof(preferred);++tries){
    uint8_t pid=preferred[pollIndex++%sizeof(preferred)];
    if(pid<sizeof(supported) && !supported[pid]) continue;
    uint8_t p[4]; size_t n=0;
    if(readPid(pid,p,n)){ decodePid(pid,p,n); return true; }
    break;
  }
  return false;
}

bool KLineOBD::captureSnapshot(OBDSnapshot& out,uint32_t durationMs){
  if(!live.connected) return false;
  double sLam=0,sCmd=0,sO2=0,sSt=0,sLt=0,sCt=0,sRpm=0;
  uint32_t nLam=0,nCmd=0,nO2=0,nSt=0,nLt=0,nCt=0,nRpm=0;
  uint32_t start=millis();
  while(millis()-start<durationMs){
    poll();
    if(live.widebandValid && !isnan(live.lambda)){ sLam+=live.lambda; nLam++; }
    if(live.commandedLambdaValid && !isnan(live.commandedLambda)){ sCmd+=live.commandedLambda; nCmd++; }
    if(live.o2VoltageValid && !isnan(live.o2Voltage)){ sO2+=live.o2Voltage; nO2++; }
    if(live.stftValid && !isnan(live.stft)){ sSt+=live.stft; nSt++; }
    if(live.ltftValid && !isnan(live.ltft)){ sLt+=live.ltft; nLt++; }
    if(live.coolantValid && !isnan(live.coolantC)){ sCt+=live.coolantC; nCt++; }
    if(live.rpmValid && !isnan(live.rpm)){ sRpm+=live.rpm; nRpm++; }
    delay(25);
  }
  out=OBDSnapshot{};
  out.samples=max(max(nLam,nO2),nRpm);
  if(nLam){ out.lambda=sLam/nLam; out.afrGasoline=out.lambda*14.7f; }
  if(nCmd) out.commandedLambda=sCmd/nCmd;
  if(nO2) out.o2Voltage=sO2/nO2;
  if(nSt) out.stft=sSt/nSt;
  if(nLt) out.ltft=sLt/nLt;
  if(nCt) out.coolantC=sCt/nCt;
  if(nRpm) out.rpm=sRpm/nRpm;
  out.valid=(nLam||nO2||nSt||nLt);
  return out.valid;
}

String KLineOBD::protocolName() const{
  if(proto==ISO9141_5BAUD) return "ISO9141-2";
  if(proto==KWP_FAST) return "ISO14230-KWP";
  return "NONE";
}
