#pragma once
#include <stdint.h>
#include <stddef.h>
#define PROGMEM
#define OUTPUT 1
#define INPUT 0
#define HIGH 1
#define LOW 0
uint32_t millis();
void pinMode(int,int);
void digitalWrite(int,int);
void delayMicroseconds(uint32_t);
uint16_t analogRead(int);
template<typename T> T constrain(T value,T low,T high){
  return value<low?low:value>high?high:value;
}
