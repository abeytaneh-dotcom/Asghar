# Water Level Ethernet Controller — 40×40 mm Rev A

دو لایه، 1.6 mm FR-4. این پروژه برای کنترل/اندازه‌گیری 4 سطح آب با ESP32-C3 SuperMini و ارتباط Ethernet ساخته شده است.

## معماری
- MCU module: ESP32-C3 SuperMini, 2×8, 2.54 mm pitch, 15.24 mm row spacing
- Ethernet: WIZnet W5500 + 25 MHz crystal + HR911105A integrated-magnetics RJ45
- Power: 7–24 V nominal input -> MP1584EN -> 3.3 V
- Water probes: COM + L1/L2/L3/L4
- Excitation: AO3401A P-MOS controlled by GPIO8, normally OFF
- Sense: 4× MMBT3904 stages, active-low into ESP32
- PCB: 40.0 × 40.0 mm, 2 copper layers

## ESP32 pin map
| Function | GPIO |
|---|---:|
| W5500 MISO | 5 |
| W5500 SCLK | 6 |
| W5500 MOSI | 7 |
| W5500 CS | 10 |
| W5500 RESET | 4 |
| W5500 INT | 3 |
| Probe excitation | 8 |
| Level 1 | 0 |
| Level 2 | 1 |
| Level 3 | 20 |
| Level 4 | 21 |

## Probe logic
GPIO8 is held high by the external gate pull-up, so the common electrode is OFF at reset. Firmware should briefly drive GPIO8 LOW, wait a few milliseconds, sample LEVEL1..LEVEL4, then drive GPIO8 HIGH again. This avoids continuous DC excitation of the water electrodes.

## Network
The W5500 SPI control signals are routed to the ESP32. MDI routing uses short differential paths to the HR911105A and follows the W5500 reference network (TX 51R bias, TCT 10R/22nF, RX 6.8nF AC coupling with 82R bias to RCT).

## Power
The MP1584EN stage follows the 3.3 V application values: 10 uH, 124 k / 40.2 k feedback divider, 22 uF output, 100 nF bootstrap. Do not connect the ESP32 USB 5 V and an external 5 V source simultaneously; this board powers the module through its 3V3 rail.

## Files
- `WaterLevel_ESP32.kicad_pcb` — routed board source
- `WaterLevel_ESP32.kicad_sch` — project overview sheet
- `WaterLevel_ESP32.kicad_pro` — KiCad project
- `BOM.csv` — component list
- `NETLIST.md` — electrical connectivity / design notes

## Before manufacturing
Run KiCad DRC after opening the project and refill copper zones. Verify the exact purchased HR911105A mechanical drawing before ordering PCBs because clone jacks can differ in shield-pin geometry.
