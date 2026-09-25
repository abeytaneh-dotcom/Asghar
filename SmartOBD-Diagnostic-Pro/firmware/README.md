# ESP32-C3 VCI Firmware

Hardware profile used by this project:

- ESP32-C3 DevKitM-1
- CAN transceiver: GPIO4 TX / GPIO5 RX
- K-Line transceiver (L9637D or MC33290): GPIO7 TX / GPIO6 RX
- Coolant-level input: GPIO3, active-low when water is present
- BLE GATT service: `7f640201-7c7d-4f0a-8b6f-4f484f4d4501`

The firmware provides real OBD-II diagnostics over CAN 500/250 kbps and ISO9141/KWP K-Line: ECU identification, stored/pending/permanent DTC reading, DTC clearing, common live PIDs, standardized ECU reset attempts, and the persistent low-coolant alarm.

Do not connect ESP32 GPIO directly to CAN-H/CAN-L or vehicle K-Line. Automotive transceivers are required.
