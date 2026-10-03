#pragma once
#include <Arduino.h>
#include "BoardConfig.h"

struct KRWaterState {
  float level = 0.0f;
  bool noWater = true;
  bool sensorFault = true;
  uint16_t rawLow = 0;
  uint16_t rawHigh = 0;
};

class KRWaterGuard {
 public:
  void begin() {
    pinMode(KR_WATER_EXCITE_PIN, OUTPUT);
    digitalWrite(KR_WATER_EXCITE_PIN, LOW);
    pinMode(KR_WATER_SENSE_PIN, INPUT);
    lastSample_ = 0;
  }

  bool due() const {
    return millis() - lastSample_ >= KR_WATER_SAMPLE_MS;
  }

  KRWaterState update() {
    if (!due()) return state_;
    lastSample_ = millis();

    digitalWrite(KR_WATER_EXCITE_PIN, LOW);
    delayMicroseconds(2200);
    uint16_t low = analogRead(KR_WATER_SENSE_PIN);

    digitalWrite(KR_WATER_EXCITE_PIN, HIGH);
    delayMicroseconds(2200);
    uint16_t high = analogRead(KR_WATER_SENSE_PIN);

    digitalWrite(KR_WATER_EXCITE_PIN, LOW);

    bool lowPhaseBad = low > KR_WATER_LOW_PHASE_MAX;
    bool highPhaseBad = high < KR_WATER_WET_MIN_RAW;
    bool shortHigh = high >= KR_WATER_SHORT_HIGH_RAW && lowPhaseBad;
    bool bad = lowPhaseBad || highPhaseBad || shortHigh;

    if (bad) {
      badCount_ = min<uint8_t>(255, badCount_ + 1);
      goodCount_ = 0;
    } else {
      goodCount_ = min<uint8_t>(255, goodCount_ + 1);
      badCount_ = 0;
    }

    if (badCount_ >= KR_WATER_BAD_SAMPLES) {
      state_.noWater = true;
      state_.sensorFault = lowPhaseBad || shortHigh;
    }
    if (goodCount_ >= KR_WATER_GOOD_SAMPLES) {
      state_.noWater = false;
      state_.sensorFault = false;
    }

    float pct = 0.0f;
    if (high > KR_WATER_WET_MIN_RAW) {
      pct = (float)(high - KR_WATER_WET_MIN_RAW) * 100.0f /
            (float)(KR_WATER_WET_FULL_RAW - KR_WATER_WET_MIN_RAW);
    }
    state_.level = constrain(pct, 0.0f, 100.0f);
    state_.rawLow = low;
    state_.rawHigh = high;
    return state_;
  }

  const KRWaterState& state() const { return state_; }

 private:
  KRWaterState state_;
  uint32_t lastSample_ = 0;
  uint8_t badCount_ = 0;
  uint8_t goodCount_ = 0;
};
