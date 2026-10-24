# NETLIST / Connectivity

## Power
`J2.1 VIN_RAW -> F1 -> VIN -> U3(MP1584EN)`; `J2.2 -> GND`. MP1584 output is `3V3D`; `FB1` creates `3V3A` for W5500 analog supply.

## ESP32-C3 SuperMini socket
- 3V3 -> 3V3D; GND -> GND; 5V left unconnected.
- GPIO5=MISO, GPIO6=SCLK, GPIO7=MOSI, GPIO10=CS, GPIO4=RST, GPIO3=INT.
- GPIO8=PROBE_EN.
- GPIO0/1/20/21=LEVEL1/2/3/4.

## W5500
- VDD pin 28 -> 3V3D.
- AVDD pins 4/8/11/15/17/21 -> 3V3A through FB1 supply domain.
- AGND/GND pins -> GND.
- PMODE0/1/2 -> 3V3D (111, all-capable auto-negotiation).
- EXRES1 pin 10 -> 12.4k 1% -> GND.
- XI/XO -> 25MHz crystal with 18pF load capacitors and 1M across crystal.
- VBG -> 10nF GND; TOCAP -> 10nF GND; 1V2O -> 4.7uF GND.

## Ethernet MDI / HR911105A
- W5500 TXP/TXN -> 0R damping links -> HR911105A TD+/TD-. Each TX line biased to 3V3A through 51R.
- TCT -> 3V3A via 10R, 22nF to GND.
- W5500 RXP/RXN -> 6.8nF series -> HR911105A RD+/RD-. RXP and RXN each connect to RCT through 82R; RCT has 10nF to GND.

## Level probes
`J3 = COM, L1, L2, L3, L4`. COM is driven from 3V3D by Q5 AO3401A only while GPIO8 is LOW. Each level probe drives an MMBT3904 base through 100k; base has 1M pulldown; collector is pulled up to 3V3D by 10k and feeds ESP32. Water present = input LOW while excitation is active.
