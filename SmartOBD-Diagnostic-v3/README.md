# Khaneh Remap SmartOBD Diagnostic v3 — guarded bench firmware

Firmware: `5.1-bench-safe`. This is a bench-validation candidate, **not a vehicle-ready release**.

## Default behavior

- CAN/K-Line do not start at boot. `KR_HARDWARE_VERIFIED=0` rejects bus-start commands.
- OTA returns `DENY|OTA_DISABLED`. No insecure firmware download or automatic reboot is available.
- DTC clear and ECU reset return `DENY|BENCH_READ_ONLY` by default. Enabling them requires a separate security and ECU-target review; fresh valid RPM is an additional condition, not authentication.
- Water sensing runs in its own task, including while K-Line initialization blocks the diagnostic loop. Alarm telemetry is still delivered by the main loop and can be delayed during a transaction.
- The buzzer pin defaults to `-1` (disabled): no independent physical buzzer output is confirmed for this PCB. Do not claim a working audible alarm until its circuit is verified and the pin is configured.
- BLE UUIDs and existing `Q`, `T`, `L1`/`L2`/`L3`, DTC and identity messages are retained. BLE commands are queued; CAN/K-Line and notifications have one owner, the main loop. Queued commands and partial messages are invalidated across connection sessions.

## PCB mapping — verify before flashing or powering

| Signal | GPIO |
|---|---:|
| CAN TX | 5 |
| CAN RX | 6 |
| K-Line TX | 10 |
| K-Line RX | 7 |
| Water excitation, provisional | 1 |
| Water ADC, provisional | 0 |
| Battery ADC, provisional | 3 |
| Buzzer | disabled; actual pin required |

The CAN/K-Line mapping follows the existing project requirement. This repository does not establish the actual PCB connectivity, sensor front-end, 12 V input protection or buzzer circuit. Match the table against the real schematic and continuity measurements. GPIO10 must never also drive the buzzer.

Check K-Line idle polarity and RX protection. `KR_KLINE_TX_INVERTED` controls both GPIO initialization and UART TX inversion for an inverting transistor stage; its correct value must be measured on the physical circuit. The expected ECU source address is currently `0x10` (`KR_KLINE_ECU_ADDRESS`).

Only after these checks may a local bench build use `-D KR_HARDWARE_VERIFIED=1`, with an independently verified buzzer pin if available. The CI `esp32-c3-verified-bench` environment is compile coverage only and its binary is deliberately not uploaded.

## Explicit bus selection

After hardware verification, send exactly one of:

- `BUS|CAN500`
- `BUS|CAN250`
- `BUS|KLINE_FAST`
- `BUS|KLINE_5BAUD`

`BUS|OFF` stops the selected bus. `REDETECT` retries only that selected protocol. There is no automatic scan transmitting at different CAN rates. `E|...` / `ECU_SELECT:...` is a display label and does not identify a physical ECU or select a reset destination.

## Parser scope

- CAN: standard 11-bit OBD addressing only; explicit capacity, DLC, ISO-TP sequence and complete-message checks. Oversized and incomplete messages fail. One responding ECU is used for each transaction; results do not aggregate multiple ECUs.
- CAN Mode 03 consumes the DTC-count byte before decoding pairs, as described in the [ELM327 manufacturer datasheet, page 34](https://www.elmelectronics.com/wp-content/uploads/2016/07/ELM327DS.pdf).
- K-Line: only complete, addressed, checksummed frames are accepted. Exact request echo is removed. Fast-init validates the positive start-communication payload; 5-baud validates synchronization, keyword exchange and complemented address acknowledgement. Supported keyword pairs are ISO9141 `08/08`, `94/94` and KWP `E9/8F`; other variants fail closed pending ECU-specific validation.
- K-Line Mode 09 identifiers spread across separate legacy messages are currently rejected, not assembled. Only a complete single-message VIN (17 characters) or one complete calibration record is accepted. This is not support for every ISO9141/KWP ECU.
- BLE `Y|HH|HH|HH|payload` preserves every character after the header, including `|`. Limits: 512 bytes per command, 8 queued writes, 2-second partial-message timeout. Sequence errors, malformed headers and overflow are rejected. Incoming data is not authenticated; keep the default read-only/OTA-disabled configuration.
- WaterGuard uses 3 consecutive bad samples and 10 good samples at a nominal 180 ms cadence. Counters saturate at 255. Thresholds require calibration for the real coolant and circuit. ADC percentage is not measured tank volume; open electrode/dry tank are both low-water states. Some shorts can mimic a wet probe, depending on the supervision network.
- Telemetry timestamps and actual BLE delivery latency require hardware/app validation. The requested 50/200 ms intervals are scheduling targets, not guarantees during blocking transactions.

## Automated validation

From the repository root:

```sh
g++ -std=c++11 -Wall -Wextra -Werror -fsanitize=address,undefined -fno-omit-frame-pointer -I SmartOBD-Diagnostic-v3/tests/stubs SmartOBD-Diagnostic-v3/tests/safety_test.cpp -o /tmp/smartobd-safety-test
/tmp/smartobd-safety-test
python -m platformio run -d SmartOBD-Diagnostic-v3/firmware/KhanehRemap_SmartOBD_Diag_v5 -e esp32-c3 -e esp32-c3-verified-bench
```

Native tests cover ISO-TP capacities, short DLC, missing/wrong sequence, sequence wrap, 100,000 randomized receive cases, K-Line checksum/address/echo rejection, CAN vs legacy DTC layout, BLE delimiter/sequence/size/timeout, actual WaterGuard transitions beyond 255 samples and RPM freshness. Native stubs do not validate ESP32 task scheduling, electrical behavior or ECU timing.

CI runs these tests, rejects a K-Line/buzzer pin conflict, builds both configurations with pinned dependencies and uploads only the guarded default firmware plus ELF/bootloader/partition binaries.

## Hardware go/no-go checklist

Do not connect to a vehicle until every applicable item passes on the exact flashed commit and binary:

1. **Power/pins:** continuity, CAN TX/RX, K-Line TX/RX and polarity, 3.3 V protections, water ADC/excitation circuit, battery divider and a separate buzzer driver/pin. Use a current-limited supply first.
2. **CAN simulator:** 250/500 kbit/s separately, single/multi-frame PID/VIN/DTC, multiple responders, wrong SID/PID, short DLC, oversized FF, missing/duplicate CF, bus-off, unplug/replug. No crash, no partial result, bounded recovery.
3. **K-Line simulator:** echo-only must not connect; correct/incorrect fast-init and 5-baud acknowledgement; wrong address/checksum; short/overlong response; UART inversion and measured 25 ms / 200 ms timing. Unsupported multi-message identity returns failure.
4. **Command safety:** OTA/clear/reset remain denied in the shipped default. If mutating commands are later reviewed and enabled, unknown/stale/failed RPM and engine-running conditions must deny commands; explicitly verify the selected physical ECU.
5. **BLE/app:** MTU 23/185, legacy and fragmented traffic, payload containing `|`, missing/duplicate fragments, malformed hex, oversized writes, queue flood, reconnect mid-message. No stale-session command execution; successful complete messages reconstruct exactly. Measure notification loss and latency.
6. **Water alarm:** wet/dry/open/ground-short/supply-short/noise, with disconnected BLE, busy CAN, 5-baud initialization and Wi-Fi reconnect. Measure sample cadence and actual local buzzer timing. Verify thresholds and fault coverage with the real sensor front-end. This gate cannot pass while the buzzer is disabled.
7. **OTA future gate:** authenticated provisioning plus a trusted signed-image deployment and verified partition layout; wrong certificate/image, interrupted network/power and recovery tests. OTA is intentionally disabled until then.

Current decision: **GO for controlled bench validation after pin verification; NO-GO for vehicle use or a production hardware release.**
