/*
  KHANEH REMAP - SMART OBD / CarLab Bridge v2.3
  Target: ESP32-C3
  Transport:
    - PC CarLab <-> USB Serial 115200
    - Web App <-> BLE GATT
  Purpose:
    - Full telemetry bridge
    - Real round-trip ACK between Web App and CarLab
    - Persistent coolant/no-water alarm
    - DTC simulation bridge
    - Persistent serial stored in NVS

  IMPORTANT:
    SIM/Bench mode is for bench testing only.
    Do not inject simulated frames while connected to a real vehicle bus.
*/

#include <Arduino.h>
#include <NimBLEDevice.h>
#include <Preferences.h>

static const char* FW_VERSION = "3.1-programmer-pro";

// Real-vehicle programming is intentionally compile-time gated.
// Enable only after the board has the corresponding automotive transceiver
// and a verified ECU-family programming driver.
#ifndef KR_CAN_PROGRAMMER
#define KR_CAN_PROGRAMMER 0
#endif
#ifndef KR_KLINE_PROGRAMMER
#define KR_KLINE_PROGRAMMER 0
#endif

// SMART_OBD_V2 UUIDs - must match the web app.
static const char* UUID_SERVICE = "7f640201-7c7d-4f0a-8b6f-4f484f4d4501";
static const char* UUID_CMD     = "7f640202-7c7d-4f0a-8b6f-4f484f4d4501";
static const char* UUID_DATA    = "7f640203-7c7d-4f0a-8b6f-4f484f4d4501";

NimBLECharacteristic *chCmd = nullptr, *chData = nullptr;
Preferences prefs;

String usbRx;
String storedSerial;
String lastData = "READY";
String dtcs = "";

bool simMode = false;
bool coolantAlarm = false;
float coolantLevel = 100.0f;

unsigned long lastHeartbeatFromSim = 0;
unsigned long lastHeartbeatNotify = 0;
unsigned long lastCoolantAlarmNotify = 0;
unsigned long lastDiagNotify = 0;

static bool simulatorOnline() {
  return lastHeartbeatFromSim != 0 && (millis() - lastHeartbeatFromSim) < 7000UL;
}

static String field(const String& s, int index) {
  int from = 0, part = 0;
  for (int i = 0; i <= (int)s.length(); ++i) {
    if (i == (int)s.length() || s[i] == '|') {
      if (part == index) return s.substring(from, i);
      from = i + 1;
      ++part;
    }
  }
  return "";
}

static bool validSerial(const String& raw) {
  String s = raw;
  s.trim();
  s.toUpperCase();

  // Same family used by the current web app: PREFIX-YY-NNNNNN
  int p1 = s.indexOf('-');
  int p2 = s.indexOf('-', p1 + 1);
  if (p1 < 2 || p1 > 8 || p2 != p1 + 3) return false;
  if ((int)s.length() != p2 + 7) return false;

  for (int i = 0; i < p1; ++i) {
    char c = s[i];
    if (!((c >= 'A' && c <= 'Z') || (c >= '0' && c <= '9'))) return false;
  }
  for (int i = p1 + 1; i < p2; ++i) if (!isDigit(s[i])) return false;
  for (int i = p2 + 1; i < (int)s.length(); ++i) if (!isDigit(s[i])) return false;
  return true;
}

static void setDataValue(const String& text, bool notify = true) {
  lastData = text;
  if (!chData) return;
  chData->setValue((uint8_t*)lastData.c_str(), lastData.length());
  if (notify) chData->notify();
}

static uint8_t longTxId = 0;
static void bleOut(const String& text) {
  // Keep every notification <=20 bytes so it is safe even when ATT MTU stays at 23.
  if (text.length() <= 20) {
    setDataValue(text, true);
    return;
  }
  const int chunkSize = 9; // X|ID|SEQ|TOT| = 11 bytes, +9 data = 20.
  int total = (text.length() + chunkSize - 1) / chunkSize;
  if (total > 255) {
    setDataValue("DENY|TX_TOO_LONG", true);
    return;
  }
  uint8_t id = ++longTxId;
  char head[16];
  for (int seq = 0; seq < total; ++seq) {
    int from = seq * chunkSize;
    String part = text.substring(from, ((from + chunkSize < (int)text.length()) ? from + chunkSize : (int)text.length()));
    snprintf(head, sizeof(head), "X|%02X|%02X|%02X|", id, seq, total);
    setDataValue(String(head) + part, true);
    delay(3);
  }
}

static void usbOut(const String& text) {
  Serial.println(text);
}

static void forwardToSimulator(const String& text) {
  // The PC simulator reads newline terminated commands from USB Serial.
  usbOut(text);
}

static void setCoolantState(float level, bool forceAlarm = false) {
  if (isnan(level)) return;
  coolantLevel = constrain(level, 0.0f, 100.0f);

  const bool shouldAlarm = forceAlarm || coolantLevel <= 15.0f;
  const bool shouldClear = !forceAlarm && coolantLevel >= 25.0f;

  if (shouldAlarm) {
    if (!coolantAlarm) {
      coolantAlarm = true;
      bleOut("ALARM:LOW_COOLANT," + String(coolantLevel, 0));
    }
  } else if (shouldClear && coolantAlarm) {
    coolantAlarm = false;
    bleOut("COOLANT:OK");
  }
}

static bool validDtcCode(String code) {
  code.trim();
  code.toUpperCase();
  if (code.length() != 5) return false;
  char family = code[0];
  if (!(family == 'P' || family == 'B' || family == 'C' || family == 'U')) return false;
  if (!(code[1] >= '0' && code[1] <= '3')) return false;
  for (int i = 2; i < 5; ++i) {
    char c = code[i];
    if (!((c >= '0' && c <= '9') || (c >= 'A' && c <= 'F'))) return false;
  }
  return true;
}

static void addDtc(String code) {
  code.trim();
  code.toUpperCase();
  if (!validDtcCode(code)) return;
  if (dtcs.length() > 1200) return; // guard heap use in bench fault-injection mode

  // Avoid duplicates.
  String padded = "," + dtcs + ",";
  if (padded.indexOf("," + code + ",") >= 0) return;

  if (dtcs.length()) dtcs += ",";
  dtcs += code;
}

static void delDtc(String code) {
  code.trim();
  code.toUpperCase();
  if (!dtcs.length()) return;

  String src = "," + dtcs + ",";
  src.replace("," + code + ",", ",");
  while (src.indexOf(",,") >= 0) src.replace(",,", ",");
  if (src.startsWith(",")) src.remove(0, 1);
  if (src.endsWith(",")) src.remove(src.length() - 1);
  dtcs = src;
}

static String chipSuffix() {
  uint64_t mac = ESP.getEfuseMac();
  char buf[13];
  snprintf(buf, sizeof(buf), "%012llX", (unsigned long long)mac);
  return String(buf);
}

static String deviceName() {
  return "KR_OBD_" + chipSuffix();
}

static void sendDiag() {
  // Keep every BLE notification short enough to remain robust even with a small ATT MTU.
  bleOut("D1|FW" + String(FW_VERSION) + "|S" + String(simulatorOnline() ? 1 : 0));
  delay(8);
  unsigned long hbAge = lastHeartbeatFromSim ? (millis() - lastHeartbeatFromSim) : 999999UL;
  if (hbAge > 999999UL) hbAge = 999999UL;
  bleOut("D2|H" + String(hbAge) + "|C" + String(coolantLevel, 0) + "|A" + String(coolantAlarm ? 1 : 0));
}

static void handleBleCommand(String s) {
  s.trim();
  if (!s.length()) return;

  // Serial provisioning used by the current web app.
  if (s == "GET_SERIAL") {
    bleOut("S|" + (storedSerial.length() ? storedSerial : "UNSET"));
    return;
  }
  if (s.startsWith("SET_SERIAL:") || s.startsWith("SS|")) {
    String requested = s.startsWith("SS|")
      ? s.substring(3)
      : s.substring(String("SET_SERIAL:").length());
    requested.trim();
    requested.toUpperCase();

    if (!validSerial(requested)) {
      bleOut("S|INVALID");
      return;
    }
    if (storedSerial.length() && storedSerial != requested) {
      bleOut("S|LOCKED");
      return;
    }

    storedSerial = requested;
    prefs.putString("serial", storedSerial);
    bleOut("S|" + storedSerial);
    return;
  }

  if (s == "STATUS") {
    bleOut(simulatorOnline() ? "ST|SIM|1" : "ST|NONE|0");
    return;
  }
  if (s == "D?") {
    sendDiag();
    return;
  }

  // In bench mode the ECU selection is metadata for the simulator.
  if (s == "REDETECT") {
    if (simulatorOnline()) {
      bleOut("ECU:CONNECTED,SIM");
      forwardToSimulator("WEB|ECU|AUTO");
    } else {
      bleOut("ECU:DISCONNECTED,NONE");
    }
    return;
  }
  if (s.startsWith("ECU_SELECT:") || s.startsWith("E|")) {
    String p = s.startsWith("E|") ? s.substring(2) : s.substring(String("ECU_SELECT:").length());
    // Compact reply keeps common profile names below the 20-byte ATT payload boundary.
    bleOut("EC|" + p);
    forwardToSimulator("WEB|ECU|" + p);
    return;
  }

  if (s == "ID?") {
    if (simulatorOnline()) {
      forwardToSimulator("ID?");
    } else {
      // The current generic bridge has no safe way to invent SW/CAL identifiers.
      // A real ECU driver must return a normalized ID|... record after reading it from the ECU.
      bleOut("ID|UNKNOWN|UNKNOWN||||NONE|||");
    }
    return;
  }

  if (s == "PRG|CAP?") {
    if (simulatorOnline()) {
      forwardToSimulator(s);
    } else {
      bleOut("PRG|CAP|SIM=0|CAN=" + String(KR_CAN_PROGRAMMER ? 1 : 0) +
             "|KLINE=" + String(KR_KLINE_PROGRAMMER ? 1 : 0) + "|PROFILES=VERIFIED_ONLY");
    }
    return;
  }

  if (s.startsWith("PRG|")) {
    if (simulatorOnline()) {
      forwardToSimulator(s);
    } else {
      // Real write commands stay locked unless a verified, ECU-specific flash driver is compiled.
      // This prevents a generic CAN/K-Line frame sequence from corrupting an ECU.
      bleOut("PRG|ERROR|REAL_PROFILE_DRIVER_REQUIRED");
    }
    return;
  }

  if (s == "READ_DTC") {
    if (!dtcs.length()) {
      bleOut("DT|NONE");
      return;
    }
    bleOut("DT|BEGIN");
    delay(10);
    int from = 0;
    while (from < (int)dtcs.length()) {
      int comma = dtcs.indexOf(',', from);
      String code = comma < 0 ? dtcs.substring(from) : dtcs.substring(from, comma);
      code.trim();
      if (code.length()) {
        bleOut("DT|" + code);
        delay(12);
      }
      if (comma < 0) break;
      from = comma + 1;
    }
    bleOut("DT|END");
    return;
  }
  if (s == "CLEAR_DTC") {
    dtcs = "";
    bleOut("CLEAR_DTC:SENT");
    forwardToSimulator("W|D|CLEAR");
    return;
  }

  // Two-stage bridge test:
  // A|E|id confirms Web/BLE <-> ESP.
  // A|P|id confirms the full PC Programmer/Simulator round trip.
  if (s.startsWith("W|P|")) {
    String id = field(s, 2);
    if (id.length()) bleOut("A|E|" + id);
    forwardToSimulator(s);
    return;
  }

  if (s.startsWith("W|A|") || s == "W|S" ||
      s.startsWith("WEB|PING|") || s.startsWith("WEB|ACT|")) {
    forwardToSimulator(s);
    return;
  }

  // Compatibility commands.
  if (s == "PING") {
    bleOut("PONG|" + String(millis()));
    return;
  }

  bleOut("DENY|CMD");
}

static void handleSimulatorLine(String s) {
  s.trim();
  if (!s.length()) return;

  if (s == "PING") {
    usbOut("PONG|" + String(millis()));
    return;
  }
  if (s.startsWith("HELLO|")) {
    usbOut("HELLO|ESP32-C3|SMART-OBD-BRIDGE|" + String(FW_VERSION));
    return;
  }

  // Live snapshots from CarLab -> convert S* to the L* protocol expected by web app.
  if (s.startsWith("S1|")) {
    simMode = true;
    bleOut("L1|" + s.substring(3));
    return;
  }
  if (s.startsWith("S2|")) {
    bleOut("L2|" + s.substring(3));
    return;
  }
  if (s.startsWith("S3|")) {
    String levelText = field(s, 1);
    float level = levelText.toFloat();
    setCoolantState(level, false);
    bleOut("L3|" + s.substring(3));
    return;
  }
  if (s.startsWith("S4|")) { bleOut("L4|" + s.substring(3)); return; }
  if (s.startsWith("S5|")) { bleOut("L5|" + s.substring(3)); return; }
  if (s.startsWith("S6|")) { bleOut("L6|" + s.substring(3)); return; }
  if (s.startsWith("S7|")) { bleOut("L7|" + s.substring(3)); return; }
  if (s.startsWith("S8|")) { bleOut("L8|" + s.substring(3)); return; }
  if (s.startsWith("S9|")) { bleOut("L9|" + s.substring(3)); return; }
  if (s.startsWith("SX|")) { bleOut("LX|" + s.substring(3)); return; }
  if (s.startsWith("SG|")) { bleOut("LG|" + s.substring(3)); return; }

  // Generic extra telemetry: P|KEY|VALUE
  if (s.startsWith("P|")) {
    if (s.length() <= 20) bleOut(s);
    else bleOut("DENY|P_LEN");
    return;
  }

  // Heartbeat generated by CarLab every ~2 s.
  if (s == "HB" || s.startsWith("SIM|HEARTBEAT")) {
    simMode = true;
    lastHeartbeatFromSim = millis();
    bleOut("H|" + String(lastHeartbeatFromSim));
    return;
  }

  // Real simulator ACK for round-trip tests / actuator commands.
  if (s.startsWith("A|")) {
    bleOut(s);
    return;
  }

  // Simulator may emit explicit coolant states.
  if (s.startsWith("ALARM:LOW_COOLANT")) {
    int comma = s.indexOf(',');
    float level = comma >= 0 ? s.substring(comma + 1).toFloat() : coolantLevel;
    bool wasAlarm = coolantAlarm;
    setCoolantState(level, true);
    if (wasAlarm) bleOut("ALARM:LOW_COOLANT," + String(coolantLevel, 0));
    return;
  }
  if (s == "COOLANT:OK") {
    coolantLevel = max(coolantLevel, 25.0f);
    coolantAlarm = false;
    bleOut("COOLANT:OK");
    return;
  }

  // Mirror simulator actuator changes immediately; SX snapshots still provide the authoritative full mask.
  if (s.startsWith("SIM|ACT|")) {
    String key = field(s, 2); key.toUpperCase();
    String val = field(s, 3);
    String frame = "ACT|" + key + "|" + (val == "1" ? "1" : "0");
    if (frame.length() <= 20) bleOut(frame);
    return;
  }

  // Track simulator DTC list.
  if (s.startsWith("SIM|DTC|")) {
    String op = field(s, 2);
    String code = field(s, 3);
    op.toUpperCase();
    if (op == "ADD") addDtc(code);
    else if (op == "DEL") delDtc(code);
    else if (op == "CLEAR") dtcs = "";
    return;
  }

  if (s.startsWith("SIM|ECU|")) {
    usbOut("ACK|ECU|" + field(s, 2));
    return;
  }
  if (s.startsWith("SIM|CANRATE|")) {
    usbOut("ACK|CANRATE|" + field(s, 2));
    return;
  }

  // Track bench mode.
  if (s.startsWith("SIM|MODE|")) {
    String v = field(s, 2);
    v.toUpperCase();
    simMode = (v == "ON" || v == "1" || v == "TRUE");
    return;
  }

  // Direct state updates - useful for compatibility and manual bench tests.
  if (s.startsWith("SIM|SET|")) {
    String key = field(s, 2);
    String val = field(s, 3);
    key.toUpperCase();
    if (key == "COOLANTLEVEL" || key == "COOLANT_LEVEL") {
      setCoolantState(val.toFloat(), false);
    }
    return;
  }

  if (s.startsWith("SIM|FAULT|NO_WATER|")) {
    bool on = field(s, 3) == "1";
    if (on) setCoolantState(0, true);
    else {
      coolantLevel = 100;
      coolantAlarm = false;
      bleOut("COOLANT:OK");
    }
    return;
  }

  if (s.startsWith("ID|") || s.startsWith("PRG|")) {
    bleOut(s);
    return;
  }

  // Diagnostic replies or explicit messages can safely pass through.
  if (s.startsWith("D1|") || s.startsWith("D2|") ||
      s.startsWith("DIAG|") || s.startsWith("BRIDGE|")) {
    bleOut(s);
    return;
  }
}

static String longCmdBuf = "";
static int longCmdId = -1, longCmdNext = 0, longCmdTotal = 0;

class CmdCallbacks : public NimBLECharacteristicCallbacks {
  void onWrite(NimBLECharacteristic* c, NimBLEConnInfo& info) override {
    std::string v = c->getValue();
    if (!v.size()) return;
    String cmd(v.c_str());
    if (cmd.startsWith("Y|")) {
      String idS=field(cmd,1), seqS=field(cmd,2), totS=field(cmd,3), part=field(cmd,4);
      int id=(int)strtol(idS.c_str(),nullptr,16), seq=(int)strtol(seqS.c_str(),nullptr,16), tot=(int)strtol(totS.c_str(),nullptr,16);
      if(seq==0 || id!=longCmdId){longCmdId=id;longCmdNext=0;longCmdTotal=tot;longCmdBuf="";}
      if(id!=longCmdId || seq!=longCmdNext || tot!=longCmdTotal){longCmdBuf="";longCmdNext=0;longCmdTotal=0;bleOut("DENY|RX_SEQ");return;}
      longCmdBuf += part; longCmdNext++;
      if(longCmdNext>=longCmdTotal){String full=longCmdBuf;longCmdBuf="";longCmdNext=0;longCmdTotal=0;handleBleCommand(full);}
      return;
    }
    handleBleCommand(cmd);
  }
};

static void setupBle() {
  String name = deviceName();
  NimBLEDevice::init(name.c_str());
  NimBLEDevice::setPower(3);
  // Preferred local MTU. Core telemetry also stays <= 20 bytes, so it remains safe if the peer negotiates less.
  NimBLEDevice::setMTU(185);

  NimBLEServer* server = NimBLEDevice::createServer();
  NimBLEService* svc = server->createService(UUID_SERVICE);

  chCmd = svc->createCharacteristic(
    UUID_CMD,
    NIMBLE_PROPERTY::WRITE | NIMBLE_PROPERTY::WRITE_NR
  );
  chData = svc->createCharacteristic(
    UUID_DATA,
    NIMBLE_PROPERTY::READ | NIMBLE_PROPERTY::NOTIFY
  );

  chCmd->setCallbacks(new CmdCallbacks());
  chData->setValue(lastData.c_str());

  svc->start();

  NimBLEAdvertising* adv = NimBLEDevice::getAdvertising();
  // Name-based discovery is intentional: a full 128-bit UUID + unique name can exceed
  // the 31-byte legacy advertising payload. The service remains accessible because the
  // web clients request it in optionalServices before connecting.
  adv->setName(name.c_str());
  adv->start();
}

void setup() {
  Serial.begin(115200);
  delay(250);

  prefs.begin("smartobd", false);
  storedSerial = prefs.getString("serial", "");

  setupBle();

  usbOut("BOOT|KHANEH_REMAP|SMART_OBD_BRIDGE|" + String(FW_VERSION));
  usbOut("HW|" + deviceName());
}

void loop() {
  if (simMode && lastHeartbeatFromSim && (millis() - lastHeartbeatFromSim) > 7000UL) {
    simMode = false; // heartbeat expired
  }
  // USB <-> PC simulator
  while (Serial.available()) {
    char c = (char)Serial.read();
    if (c == '\n') {
      handleSimulatorLine(usbRx);
      usbRx = "";
    } else if (c != '\r' && usbRx.length() < 512) {
      usbRx += c;
    }
  }

  // Keep web UI heartbeat green while the simulator is genuinely alive.
  if (lastHeartbeatFromSim && millis() - lastHeartbeatFromSim < 6000) {
    if (millis() - lastHeartbeatNotify > 1800) {
      lastHeartbeatNotify = millis();
      bleOut("H|" + String(lastHeartbeatFromSim));
    }
  }

  // Persistent coolant warning: never silently disappears while fault remains.
  if (coolantAlarm && millis() - lastCoolantAlarmNotify > 2200) {
    lastCoolantAlarmNotify = millis();
    bleOut("ALARM:LOW_COOLANT," + String(coolantLevel, 0));
  }

  // Periodic lightweight diagnostics for bench troubleshooting.
  if (simMode && millis() - lastDiagNotify > 10000) {
    lastDiagNotify = millis();
    if (!lastHeartbeatFromSim || millis() - lastHeartbeatFromSim > 7000) {
      bleOut("DIAG|SIM_HEARTBEAT_MISSING");
    }
  }

  delay(2);
}