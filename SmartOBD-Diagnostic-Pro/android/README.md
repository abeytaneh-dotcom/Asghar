# Android App

Native BLE + local WebView UI. No server or login is required.

BLE profile:

- Service `7f640201-7c7d-4f0a-8b6f-4f484f4d4501`
- Command `7f640202-7c7d-4f0a-8b6f-4f484f4d4501`
- Data/Notify `7f640203-7c7d-4f0a-8b6f-4f484f4d4501`

The serial format is `PREFIX-YY-NNNNNN` (for example `KR-26-000001`). The serial is paired with the ESP32 NVS and stored locally in the app.
