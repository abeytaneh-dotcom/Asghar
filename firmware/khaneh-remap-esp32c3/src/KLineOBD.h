#pragma once
#include <Arduino.h>

struct OBDLiveData {
  bool connected=false, widebandValid=false, commandedLambdaValid=false;
  bool o2VoltageValid=false, stftValid=false, ltftValid=false, coolantValid=false, rpmValid=false;
  float lambda=NAN, commandedLambda=NAN, afrGasoline=NAN, o2Voltage=NAN;
  float stft=NAN, ltft=NAN, coolantC=NAN, rpm=NAN;
  uint32_t lastUpdateMs=0;
};

struct OBDSnapshot {
  bool valid=false;
  uint32_t samples=0;
  float lambda=NAN, commandedLambda=NAN, afrGasoline=NAN, o2Voltage=NAN;
  float stft=NAN, ltft=NAN, coolantC=NAN, rpm=NAN;
};

class KLineOBD {
public:
  KLineOBD(int rxPin,int txPin);
  void begin(); void end(); bool connectAuto(); void disconnect();
  bool isConnected() const { return live.connected; }
  bool poll();
  const OBDLiveData& data() const { return live; }
  bool captureSnapshot(OBDSnapshot& out,uint32_t durationMs=12000);
  String protocolName() const;
private:
  enum Proto { NONE, ISO9141_5BAUD, KWP_FAST } proto=NONE;
  HardwareSerial serial;
  int rx,tx;
  OBDLiveData live;
  bool supported[0x60]={false};
  uint8_t pollIndex=0;
  uint32_t lastPollMs=0;
  void lineIdle(); void send5BaudByte(uint8_t b);
  bool init5Baud(); bool initFast();
  uint8_t checksum(const uint8_t* d,size_t n);
  void flushInput();
  bool sendRequest(uint8_t mode,uint8_t pid,uint8_t* resp,size_t& respLen,uint32_t timeoutMs=220);
  int findPayload(const uint8_t* r,size_t n,uint8_t modeResp,uint8_t pid);
  bool readPid(uint8_t pid,uint8_t* payload,size_t& payloadLen);
  bool readSupportedBlock(uint8_t basePid);
  void decodePid(uint8_t pid,const uint8_t* p,size_t n);
};
