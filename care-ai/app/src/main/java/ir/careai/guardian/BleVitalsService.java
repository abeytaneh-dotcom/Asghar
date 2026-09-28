package ir.careai.guardian;

import android.Manifest;
import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.Service;
import android.bluetooth.BluetoothAdapter;
import android.bluetooth.BluetoothDevice;
import android.bluetooth.BluetoothGatt;
import android.bluetooth.BluetoothGattCallback;
import android.bluetooth.BluetoothGattCharacteristic;
import android.bluetooth.BluetoothGattDescriptor;
import android.bluetooth.BluetoothGattService;
import android.bluetooth.BluetoothManager;
import android.bluetooth.BluetoothProfile;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.os.Build;
import android.os.IBinder;
import android.telephony.SmsManager;

import java.util.ArrayDeque;
import java.util.ArrayList;
import java.util.List;
import java.util.Locale;
import java.util.Queue;
import java.util.UUID;

public class BleVitalsService extends Service {

    public static final String ACTION_VITALS = "ir.careai.guardian.VITALS";
    public static final String ACTION_CONNECT = "ir.careai.guardian.CONNECT_VITALS";
    public static final String ACTION_DISCONNECT = "ir.careai.guardian.DISCONNECT_VITALS";

    public static final String EXTRA_ADDRESS = "address";
    public static final String EXTRA_HR = "hr";
    public static final String EXTRA_SPO2 = "spo2";
    public static final String EXTRA_TREND = "trend";
    public static final String EXTRA_STATE = "state";

    private static final String CHANNEL = "care_ble_vitals";
    private static final int NOTIF_ID = 9401;

    private static final UUID HR_SERVICE =
            UUID.fromString("0000180d-0000-1000-8000-00805f9b34fb");
    private static final UUID HR_MEAS =
            UUID.fromString("00002a37-0000-1000-8000-00805f9b34fb");

    private static final UUID PLX_SERVICE =
            UUID.fromString("00001822-0000-1000-8000-00805f9b34fb");
    private static final UUID PLX_SPOT =
            UUID.fromString("00002a5e-0000-1000-8000-00805f9b34fb");
    private static final UUID PLX_CONT =
            UUID.fromString("00002a5f-0000-1000-8000-00805f9b34fb");

    private static final UUID CCCD =
            UUID.fromString("00002902-0000-1000-8000-00805f9b34fb");

    private BluetoothGatt gatt;
    private final Queue<BluetoothGattCharacteristic> notifyQueue = new ArrayDeque<>();

    private int lastHr = -1;
    private float lastSpO2 = -1f;
    private final ArrayList<Integer> hrHistory = new ArrayList<>();
    private final ArrayList<Float> spoHistory = new ArrayList<>();

    private long lastHrAlert = 0L;
    private long lastSpO2Alert = 0L;

    @Override
    public void onCreate() {
        super.onCreate();
        createChannel();
        startForeground(NOTIF_ID, notification("در انتظار اتصال سنسور"));
    }

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        if (intent == null) return START_STICKY;

        String action = intent.getAction();
        if (ACTION_DISCONNECT.equals(action)) {
            disconnect();
            stopSelf();
            return START_NOT_STICKY;
        }

        if (ACTION_CONNECT.equals(action)) {
            String address = intent.getStringExtra(EXTRA_ADDRESS);
            if (address != null && !address.trim().isEmpty()) {
                connect(address.trim());
            }
        }

        return START_STICKY;
    }

    private void connect(String address) {
        if (Build.VERSION.SDK_INT >= 31
                && checkSelfPermission(Manifest.permission.BLUETOOTH_CONNECT)
                != PackageManager.PERMISSION_GRANTED) {
            broadcast("مجوز اتصال بلوتوث داده نشده است");
            return;
        }

        BluetoothManager bm = (BluetoothManager)getSystemService(BLUETOOTH_SERVICE);
        if (bm == null) {
            broadcast("Bluetooth Manager در دسترس نیست");
            return;
        }

        BluetoothAdapter adapter = bm.getAdapter();
        if (adapter == null || !adapter.isEnabled()) {
            broadcast("بلوتوث خاموش است");
            return;
        }

        try {
            BluetoothDevice device = adapter.getRemoteDevice(address);
            disconnect();
            broadcast("در حال اتصال به " + address);
            gatt = device.connectGatt(
                    this,
                    false,
                    callback,
                    Build.VERSION.SDK_INT >= 23
                            ? BluetoothDevice.TRANSPORT_LE
                            : BluetoothDevice.TRANSPORT_AUTO
            );
        } catch (Exception e) {
            broadcast("اتصال به سنسور انجام نشد");
        }
    }

    private final BluetoothGattCallback callback = new BluetoothGattCallback() {
        @Override
        public void onConnectionStateChange(
                BluetoothGatt g,
                int status,
                int newState) {

            if (newState == BluetoothProfile.STATE_CONNECTED) {
                broadcast("سنسور متصل شد");
                updateNotification();
                if (Build.VERSION.SDK_INT < 31
                        || checkSelfPermission(Manifest.permission.BLUETOOTH_CONNECT)
                        == PackageManager.PERMISSION_GRANTED) {
                    try { g.discoverServices(); } catch (Exception ignored) {}
                }
            } else if (newState == BluetoothProfile.STATE_DISCONNECTED) {
                broadcast("سنسور قطع شد");
                updateNotification();
            }
        }

        @Override
        public void onServicesDiscovered(BluetoothGatt g, int status) {
            notifyQueue.clear();

            BluetoothGattService hr = g.getService(HR_SERVICE);
            if (hr != null) {
                BluetoothGattCharacteristic ch = hr.getCharacteristic(HR_MEAS);
                if (ch != null) notifyQueue.add(ch);
            }

            BluetoothGattService plx = g.getService(PLX_SERVICE);
            if (plx != null) {
                BluetoothGattCharacteristic cont = plx.getCharacteristic(PLX_CONT);
                BluetoothGattCharacteristic spot = plx.getCharacteristic(PLX_SPOT);
                if (cont != null) notifyQueue.add(cont);
                if (spot != null) notifyQueue.add(spot);
            }

            if (notifyQueue.isEmpty()) {
                broadcast("سنسور متصل شد اما سرویس استاندارد ضربان/اکسیژن پیدا نشد");
            } else {
                enableNextNotification();
            }
        }

        @Override
        public void onDescriptorWrite(
                BluetoothGatt g,
                BluetoothGattDescriptor descriptor,
                int status) {
            enableNextNotification();
        }

        @Override
        public void onCharacteristicChanged(
                BluetoothGatt g,
                BluetoothGattCharacteristic characteristic) {
            byte[] value = characteristic.getValue();
            if (value != null) parse(characteristic.getUuid(), value);
        }

        @Override
        public void onCharacteristicRead(
                BluetoothGatt g,
                BluetoothGattCharacteristic characteristic,
                int status) {
            if (status == BluetoothGatt.GATT_SUCCESS) {
                byte[] value = characteristic.getValue();
                if (value != null) parse(characteristic.getUuid(), value);
            }
        }
    };

    private void enableNextNotification() {
        if (gatt == null || notifyQueue.isEmpty()) return;

        if (Build.VERSION.SDK_INT >= 31
                && checkSelfPermission(Manifest.permission.BLUETOOTH_CONNECT)
                != PackageManager.PERMISSION_GRANTED) return;

        BluetoothGattCharacteristic ch = notifyQueue.poll();
        if (ch == null) return;

        try {
            gatt.setCharacteristicNotification(ch, true);
            BluetoothGattDescriptor d = ch.getDescriptor(CCCD);

            if (d != null) {
                int props = ch.getProperties();
                byte[] enable =
                        (props & BluetoothGattCharacteristic.PROPERTY_INDICATE) != 0
                                ? BluetoothGattDescriptor.ENABLE_INDICATION_VALUE
                                : BluetoothGattDescriptor.ENABLE_NOTIFICATION_VALUE;
                d.setValue(enable);
                if (!gatt.writeDescriptor(d)) enableNextNotification();
            } else {
                enableNextNotification();
            }
        } catch (Exception e) {
            enableNextNotification();
        }
    }

    private void parse(UUID uuid, byte[] value) {
        if (HR_MEAS.equals(uuid)) {
            if (value.length < 2) return;
            int flags = value[0] & 0xFF;
            int hr;
            if ((flags & 0x01) != 0 && value.length >= 3) {
                hr = (value[1] & 0xFF) | ((value[2] & 0xFF) << 8);
            } else {
                hr = value[1] & 0xFF;
            }
            if (hr > 0 && hr < 260) {
                lastHr = hr;
                addHr(hr);
                onVitalsChanged();
            }
            return;
        }

        if ((PLX_CONT.equals(uuid) || PLX_SPOT.equals(uuid)) && value.length >= 5) {
            float spo2 = sfloat(value, 1);
            float pulse = sfloat(value, 3);

            if (spo2 > 0f && spo2 <= 100f) lastSpO2 = spo2;
            if (pulse > 0f && pulse < 260f) lastHr = Math.round(pulse);

            if (lastSpO2 > 0f) addSpO2(lastSpO2);
            if (lastHr > 0) addHr(lastHr);
            onVitalsChanged();
        }
    }

    private float sfloat(byte[] value, int offset) {
        if (offset + 1 >= value.length) return Float.NaN;

        int raw = (value[offset] & 0xFF) | ((value[offset+1] & 0xFF) << 8);
        int mantissa = raw & 0x0FFF;
        if ((mantissa & 0x0800) != 0) mantissa |= 0xFFFFF000;

        int exponent = (raw >> 12) & 0x0F;
        if ((exponent & 0x08) != 0) exponent |= 0xFFFFFFF0;

        return (float)(mantissa * Math.pow(10, exponent));
    }

    private void addHr(int v) {
        hrHistory.add(v);
        while (hrHistory.size() > 10) hrHistory.remove(0);
    }

    private void addSpO2(float v) {
        spoHistory.add(v);
        while (spoHistory.size() > 10) spoHistory.remove(0);
    }

    private void onVitalsChanged() {
        SharedPreferences p = getSharedPreferences("careai",MODE_PRIVATE);
        String trend = trendText();

        p.edit()
                .putInt("last_hr",lastHr)
                .putFloat("last_spo2",lastSpO2)
                .putString("last_vitals_trend",trend)
                .putLong("last_vitals_time",System.currentTimeMillis())
                .apply();

        checkAlerts(p);

        Intent i = new Intent(ACTION_VITALS);
        i.setPackage(getPackageName());
        i.putExtra(EXTRA_HR,lastHr);
        i.putExtra(EXTRA_SPO2,lastSpO2);
        i.putExtra(EXTRA_TREND,trend);
        i.putExtra(EXTRA_STATE,"متصل");
        sendBroadcast(i);

        updateNotification();
    }

    private String trendText() {
        StringBuilder out = new StringBuilder();

        if (hrHistory.size() >= 4) {
            int first = hrHistory.get(0);
            int last = hrHistory.get(hrHistory.size()-1);
            if (last - first >= 6) out.append("ضربان رو به افزایش");
            else if (first - last >= 6) out.append("ضربان رو به کاهش");
            else out.append("ضربان نسبتاً پایدار");
        }

        if (spoHistory.size() >= 4) {
            float first = spoHistory.get(0);
            float last = spoHistory.get(spoHistory.size()-1);
            if (out.length() > 0) out.append(" • ");
            if (last - first >= 2f) out.append("اکسیژن رو به افزایش");
            else if (first - last >= 2f) out.append("اکسیژن رو به کاهش");
            else out.append("اکسیژن نسبتاً پایدار");
        }

        if (out.length() == 0) return "در حال جمع‌آوری داده برای تحلیل روند";
        return out.toString();
    }

    private void checkAlerts(SharedPreferences p) {
        int hrMin = p.getInt("hr_min",50);
        int hrMax = p.getInt("hr_max",120);
        float spoMin = p.getFloat("spo2_min",90f);
        float spoMax = p.getFloat("spo2_max",100f);
        int slot = Math.max(1,Math.min(3,p.getInt("vitals_nurse_slot",1)));

        long now = System.currentTimeMillis();
        long cooldown = Math.max(
                60000L,
                p.getLong("vitals_alert_cooldown_ms",300000L)
        );

        if (lastHr > 0
                && (lastHr < hrMin || lastHr > hrMax)
                && now - lastHrAlert >= cooldown) {
            lastHrAlert = now;
            sendVitalsSms(
                    p,
                    slot,
                    "ضربان قلب " + lastHr
                            + " bpm خارج از محدوده تنظیم‌شده "
                            + hrMin + " تا " + hrMax
            );
        }

        if (lastSpO2 > 0f
                && (lastSpO2 < spoMin || lastSpO2 > spoMax)
                && now - lastSpO2Alert >= cooldown) {
            lastSpO2Alert = now;
            sendVitalsSms(
                    p,
                    slot,
                    String.format(
                            Locale.US,
                            "اکسیژن خون %.1f%% خارج از محدوده تنظیم‌شده %.1f تا %.1f",
                            lastSpO2,spoMin,spoMax
                    )
            );
        }
    }

    private void sendVitalsSms(SharedPreferences p, int slot, String text) {
        if (checkSelfPermission(Manifest.permission.SEND_SMS)
                != PackageManager.PERMISSION_GRANTED) return;

        String number = p.getString("trusted"+slot,"").trim();
        if (number.isEmpty()) return;

        String patient = p.getString("patient_name","بیمار");

        try {
            SmsManager.getDefault().sendTextMessage(
                    number,
                    null,
                    "Care AI - " + patient + "\nهشدار سنسور: " + text
                            + "\nاین هشدار بر اساس محدوده تنظیم‌شده است و تشخیص پزشکی نیست.",
                    null,
                    null
            );
        } catch (Exception ignored) {}
    }

    private void broadcast(String state) {
        Intent i = new Intent(ACTION_VITALS);
        i.setPackage(getPackageName());
        i.putExtra(EXTRA_HR,lastHr);
        i.putExtra(EXTRA_SPO2,lastSpO2);
        i.putExtra(EXTRA_TREND,trendText());
        i.putExtra(EXTRA_STATE,state);
        sendBroadcast(i);
    }

    private void updateNotification() {
        String text = "سنسور سلامت";
        if (lastHr > 0 || lastSpO2 > 0f) {
            text = (lastHr > 0 ? "HR " + lastHr : "HR --")
                    + " • "
                    + (lastSpO2 > 0f
                    ? String.format(Locale.US,"SpO2 %.1f%%",lastSpO2)
                    : "SpO2 --");
        }

        NotificationManager nm =
                (NotificationManager)getSystemService(NOTIFICATION_SERVICE);
        if (nm != null) nm.notify(NOTIF_ID,notification(text));
    }

    private Notification notification(String text) {
        Notification.Builder b =
                Build.VERSION.SDK_INT >= 26
                        ? new Notification.Builder(this,CHANNEL)
                        : new Notification.Builder(this);

        return b.setSmallIcon(android.R.drawable.stat_sys_data_bluetooth)
                .setContentTitle("Care AI • پایش سنسور")
                .setContentText(text)
                .setOngoing(true)
                .build();
    }

    private void createChannel() {
        if (Build.VERSION.SDK_INT < 26) return;
        NotificationManager nm =
                (NotificationManager)getSystemService(NOTIFICATION_SERVICE);
        if (nm != null) {
            nm.createNotificationChannel(new NotificationChannel(
                    CHANNEL,
                    "پایش سنسور Care AI",
                    NotificationManager.IMPORTANCE_LOW
            ));
        }
    }

    private void disconnect() {
        if (gatt != null) {
            if (Build.VERSION.SDK_INT < 31
                    || checkSelfPermission(Manifest.permission.BLUETOOTH_CONNECT)
                    == PackageManager.PERMISSION_GRANTED) {
                try { gatt.disconnect(); } catch (Exception ignored) {}
                try { gatt.close(); } catch (Exception ignored) {}
            }
            gatt = null;
        }
    }

    @Override
    public void onDestroy() {
        disconnect();
        super.onDestroy();
    }

    @Override
    public IBinder onBind(Intent intent) {
        return null;
    }
}
