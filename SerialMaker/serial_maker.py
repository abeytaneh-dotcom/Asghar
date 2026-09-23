# Khaneh Remap Smart OBD - Serial Maker / Provisioning Studio
# Windows desktop provisioning utility for ESP32-C3
# Version 2.0

import csv
import io
import json
import os
import re
import secrets
import shutil
import sqlite3
import sys
import threading
from contextlib import redirect_stdout, redirect_stderr
from datetime import datetime
from pathlib import Path
from tkinter import filedialog, messagebox, ttk

import customtkinter as ctk
import qrcode
from PIL import Image, ImageDraw, ImageFont
from serial.tools import list_ports

APP_NAME = "Khaneh Remap Serial Maker"
APP_VERSION = "2.1"
BRAND = "KHANEH_REMAP"
DEFAULT_PREFIX = "KR"

# Keep the GUI strictly single-instance. This also protects against any
# accidental relaunch caused by Windows file associations or child processes.
_INSTANCE_MUTEX = None

def acquire_single_instance():
    global _INSTANCE_MUTEX
    if os.name != "nt":
        return True
    try:
        import ctypes
        kernel32 = ctypes.windll.kernel32
        _INSTANCE_MUTEX = kernel32.CreateMutexW(
            None, False, "Global\\KhanehRemap_SerialMaker_v21"
        )
        if not _INSTANCE_MUTEX:
            return True
        ERROR_ALREADY_EXISTS = 183
        return kernel32.GetLastError() != ERROR_ALREADY_EXISTS
    except Exception:
        # Never block normal startup if the mutex API itself is unavailable.
        return True


def user_data_dir():
    root = os.getenv("APPDATA")
    if root:
        p = Path(root) / "KhanehRemap" / "SerialMaker"
    else:
        p = Path.home() / ".khanehremap" / "SerialMaker"
    p.mkdir(parents=True, exist_ok=True)
    return p


def output_dir():
    docs = Path.home() / "Documents"
    if not docs.exists():
        docs = Path.home()
    p = docs / "KhanehRemap_Serials"
    p.mkdir(parents=True, exist_ok=True)
    return p


DATA_DIR = user_data_dir()
DB_PATH = DATA_DIR / "devices.db"
SETTINGS_PATH = DATA_DIR / "settings.json"


def resource_path(name):
    base = Path(getattr(sys, "_MEIPASS", Path(__file__).resolve().parent))
    return base / name


def load_settings():
    data = {
        "prefix": DEFAULT_PREFIX,
        "operator": "",
        "firmware_path": "",
        "auto_flash": False,
        "flash_address": "0x0",
    }
    if SETTINGS_PATH.exists():
        try:
            data.update(json.loads(SETTINGS_PATH.read_text(encoding="utf-8")))
        except Exception:
            pass
    return data


def save_settings(data):
    SETTINGS_PATH.write_text(json.dumps(data, ensure_ascii=False, indent=2), encoding="utf-8")


def init_db():
    with sqlite3.connect(DB_PATH) as con:
        con.execute("""
            CREATE TABLE IF NOT EXISTS devices (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                serial TEXT NOT NULL UNIQUE,
                chip_id TEXT NOT NULL UNIQUE,
                mac TEXT,
                activation_token TEXT NOT NULL,
                created_at TEXT NOT NULL,
                operator TEXT,
                firmware TEXT,
                status TEXT NOT NULL DEFAULT 'READY',
                note TEXT
            )
        """)
        con.execute("CREATE INDEX IF NOT EXISTS idx_devices_mac ON devices(mac)")
        con.commit()


def db_rows(search=""):
    init_db()
    with sqlite3.connect(DB_PATH) as con:
        con.row_factory = sqlite3.Row
        if search.strip():
            q = f"%{search.strip()}%"
            rows = con.execute(
                """SELECT * FROM devices
                   WHERE serial LIKE ? OR chip_id LIKE ? OR mac LIKE ? OR operator LIKE ?
                   ORDER BY id DESC""", (q, q, q, q)
            ).fetchall()
        else:
            rows = con.execute("SELECT * FROM devices ORDER BY id DESC").fetchall()
        return [dict(r) for r in rows]


def db_find_by_hw(chip_id, mac):
    init_db()
    with sqlite3.connect(DB_PATH) as con:
        con.row_factory = sqlite3.Row
        row = con.execute(
            "SELECT * FROM devices WHERE chip_id=? OR (mac<>'' AND mac=?) LIMIT 1",
            (chip_id, mac or "")
        ).fetchone()
        return dict(row) if row else None


def db_insert(row):
    init_db()
    with sqlite3.connect(DB_PATH) as con:
        con.execute(
            """INSERT INTO devices
               (serial, chip_id, mac, activation_token, created_at, operator, firmware, status, note)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)""",
            (
                row["serial"], row["chip_id"], row.get("mac", ""),
                row["activation_token"], row["created_at"],
                row.get("operator", ""), row.get("firmware", ""),
                row.get("status", "READY"), row.get("note", "")
            )
        )
        con.commit()


def next_serial(prefix):
    yy = datetime.now().strftime("%y")
    stem = f"{prefix}-{yy}-"
    max_n = 0
    with sqlite3.connect(DB_PATH) as con:
        rows = con.execute("SELECT serial FROM devices WHERE serial LIKE ?", (stem + "%",)).fetchall()
    rx = re.compile(rf"^{re.escape(stem)}(\d{{6}})$")
    for (s,) in rows:
        m = rx.match(s or "")
        if m:
            max_n = max(max_n, int(m.group(1)))
    return f"{stem}{max_n + 1:06d}"


def run_esptool(args):
    """Run esptool in-process so the single-file EXE needs no external Python/esptool install."""
    import esptool
    buf = io.StringIO()
    code = 0
    with redirect_stdout(buf), redirect_stderr(buf):
        try:
            esptool.main(args)
        except SystemExit as e:
            code = 0 if e.code in (0, None) else int(e.code)
    text = buf.getvalue()
    if code != 0:
        raise RuntimeError(text.strip() or f"esptool error {code}")
    return text


def read_esp32c3(port):
    """Read a stable hardware identity from ESP32-C3 using esptool v5 syntax."""
    if not port:
        raise RuntimeError("پورت سریال انتخاب نشده است.")

    # esptool v5 renamed CLI commands from underscore to hyphen form.
    # ESP32-C3 has no legacy standalone Chip ID in the ESP8266 sense, so the
    # factory/base MAC is the primary stable hardware identifier.
    candidates = [
        ["--chip", "esp32c3", "--port", port, "read-mac"],
        ["--chip", "esp32c3", "--port", port, "chip-id"],
    ]

    collected = []
    for args in candidates:
        try:
            out = run_esptool(args)
            collected.append(out)
        except Exception as exc:
            collected.append(str(exc))

    text = "\n".join(collected)

    # Accept current and older esptool output formats.
    mac = ""
    mac_patterns = [
        r"\bMAC(?: Address)?\s*[:=]\s*([0-9A-Fa-f]{2}(?::[0-9A-Fa-f]{2}){5})\b",
        r"\bBase MAC\s*[:=]\s*([0-9A-Fa-f]{2}(?::[0-9A-Fa-f]{2}){5})\b",
        r"\b([0-9A-Fa-f]{2}(?::[0-9A-Fa-f]{2}){5})\b",
    ]
    for pattern in mac_patterns:
        m = re.search(pattern, text, re.IGNORECASE)
        if m:
            mac = m.group(1).upper()
            break

    # For ESP32-C3 use the immutable base MAC (without separators) as our
    # internal hardware ID. This is deterministic and unique for provisioning.
    chip = mac.replace(":", "") if mac else ""

    # Fallback for any esptool build that prints an explicit chip-id value.
    if not chip:
        m = re.search(r"Chip(?:\s+ID|-ID)?\s*[:=]\s*(0x[0-9A-Fa-f]+)", text, re.IGNORECASE)
        if m:
            chip = m.group(1).upper()

    if not chip:
        # Keep the useful tail of esptool output so connection/driver/BOOT
        # errors are visible instead of being collapsed into "serial not found".
        clean = re.sub(r"\x1b\[[0-9;]*m", "", text).strip()
        tail = clean[-1400:] if clean else "هیچ پاسخی از برد دریافت نشد."
        raise RuntimeError(
            "ارتباط با ESP32-C3 برقرار نشد یا MAC خوانده نشد.\n\n"
            "موارد زیر را بررسی کنید:\n"
            "• کابل USB حتماً دیتادار باشد.\n"
            "• پورت COM صحیح را انتخاب کنید.\n"
            "• اگر برد وارد حالت دانلود نمی‌شود، BOOT را نگه دارید و یک‌بار RESET بزنید.\n\n"
            "خروجی esptool:\n" + tail
        )

    return chip, mac, text


def make_qr_payload(serial_no, chip_id, mac, token):
    return json.dumps(
        {
            "v": 1,
            "brand": BRAND,
            "serial": serial_no,
            "chip_id": chip_id,
            "mac": mac,
            "token": token,
        },
        ensure_ascii=False,
        separators=(",", ":"),
    )


def font(size, bold=False):
    names = [
        Path(os.environ.get("WINDIR", "C:/Windows")) / "Fonts" / ("arialbd.ttf" if bold else "arial.ttf"),
        Path(os.environ.get("WINDIR", "C:/Windows")) / "Fonts" / "segoeui.ttf",
    ]
    for p in names:
        try:
            if p.exists():
                return ImageFont.truetype(str(p), size)
        except Exception:
            pass
    return ImageFont.load_default()


def create_qr_and_label(record):
    out = output_dir()
    device_dir = out / record["serial"]
    device_dir.mkdir(parents=True, exist_ok=True)

    payload = make_qr_payload(
        record["serial"], record["chip_id"], record.get("mac", ""), record["activation_token"]
    )

    qr = qrcode.QRCode(version=None, box_size=10, border=3)
    qr.add_data(payload)
    qr.make(fit=True)
    qr_img = qr.make_image(fill_color="black", back_color="white").convert("RGB")
    qr_path = device_dir / f"{record['serial']}-QR.png"
    qr_img.save(qr_path)

    W, H = 1180, 520
    canvas = Image.new("RGB", (W, H), "#07111f")
    d = ImageDraw.Draw(canvas)
    d.rounded_rectangle((18, 18, W - 18, H - 18), radius=34, fill="#0c1b2d", outline="#2f9fff", width=4)
    d.rounded_rectangle((42, 42, 345, H - 42), radius=28, fill="#ffffff")
    qr_label = qr_img.resize((270, 270))
    canvas.paste(qr_label, (58, 74))

    d.text((62, 365), "SCAN TO ACTIVATE", font=font(29, True), fill="#08111e")
    d.text((395, 70), "KHANEH REMAP", font=font(52, True), fill="#ffffff")
    d.text((397, 138), "SMART OBD", font=font(35, True), fill="#40b9ff")
    d.rounded_rectangle((395, 212, 1120, 326), radius=20, fill="#081421", outline="#21496a", width=2)
    d.text((430, 230), "DEVICE SERIAL", font=font(24, True), fill="#8cb9d7")
    d.text((430, 269), record["serial"], font=font(41, True), fill="#ffffff")
    d.text((397, 372), f"CHIP ID  {record['chip_id']}", font=font(22), fill="#bcd1df")
    if record.get("mac"):
        d.text((397, 412), f"MAC      {record['mac']}", font=font(22), fill="#bcd1df")
    d.text((397, 461), "Official provisioning label", font=font(20), fill="#5f8197")

    label_path = device_dir / f"{record['serial']}-LABEL.png"
    canvas.save(label_path, quality=96)

    json_path = device_dir / f"{record['serial']}.json"
    json_path.write_text(json.dumps(record, ensure_ascii=False, indent=2), encoding="utf-8")
    return qr_path, label_path, json_path


def export_csv(path):
    rows = db_rows()
    fields = [
        "serial", "chip_id", "mac", "activation_token", "created_at",
        "operator", "firmware", "status", "note"
    ]
    with open(path, "w", encoding="utf-8-sig", newline="") as f:
        w = csv.DictWriter(f, fieldnames=fields)
        w.writeheader()
        for r in rows:
            w.writerow({k: r.get(k, "") for k in fields})


def backup_all(dest_zip):
    stage = DATA_DIR / "_backup"
    if stage.exists():
        shutil.rmtree(stage, ignore_errors=True)
    stage.mkdir(parents=True, exist_ok=True)
    if DB_PATH.exists():
        shutil.copy2(DB_PATH, stage / DB_PATH.name)
    if SETTINGS_PATH.exists():
        shutil.copy2(SETTINGS_PATH, stage / SETTINGS_PATH.name)
    csv_path = stage / "devices.csv"
    export_csv(csv_path)
    base = str(Path(dest_zip).with_suffix(""))
    archive = shutil.make_archive(base, "zip", stage)
    shutil.rmtree(stage, ignore_errors=True)
    return Path(archive)


def flash_merged_bin(port, firmware_path, address="0x0"):
    if not Path(firmware_path).exists():
        raise RuntimeError("فایل Firmware پیدا نشد.")
    args = [
        "--chip", "esp32c3", "--port", port, "--baud", "460800",
        "write-flash", "-z", address, firmware_path
    ]
    return run_esptool(args)


class SerialMakerApp(ctk.CTk):
    def __init__(self):
        super().__init__()
        init_db()
        self.settings = load_settings()
        self.current_chip = ""
        self.current_mac = ""
        self.current_raw = ""
        self.current_port = ""
        self.busy = False

        ctk.set_appearance_mode("dark")
        ctk.set_default_color_theme("blue")

        self.title(f"{APP_NAME}  v{APP_VERSION}")
        self.geometry("1140x740")
        self.minsize(1020, 680)
        self.configure(fg_color="#07111f")

        try:
            ico = resource_path("app.ico")
            if ico.exists():
                self.iconbitmap(str(ico))
        except Exception:
            pass

        self._style_tree()
        self._build_layout()
        self.refresh_ports()
        self.refresh_registry()
        self.refresh_stats()

    def _style_tree(self):
        style = ttk.Style()
        try:
            style.theme_use("clam")
        except Exception:
            pass
        style.configure("Treeview", background="#0d1c2b", foreground="#eaf6ff",
                        fieldbackground="#0d1c2b", borderwidth=0, rowheight=34,
                        font=("Segoe UI", 10))
        style.configure("Treeview.Heading", background="#132b40", foreground="#a9d7f5",
                        relief="flat", font=("Segoe UI", 10, "bold"))
        style.map("Treeview", background=[("selected", "#175b88")])

    def _build_layout(self):
        self.grid_columnconfigure(1, weight=1)
        self.grid_rowconfigure(0, weight=1)

        self.sidebar = ctk.CTkFrame(self, width=220, corner_radius=0, fg_color="#081522")
        self.sidebar.grid(row=0, column=0, sticky="nsew")
        self.sidebar.grid_propagate(False)

        logo = ctk.CTkFrame(self.sidebar, width=78, height=78, corner_radius=39,
                            fg_color="#0e7fc4", border_width=2, border_color="#42c8ff")
        logo.pack(pady=(34, 10))
        logo.pack_propagate(False)
        ctk.CTkLabel(logo, text="KR", font=("Segoe UI", 27, "bold"), text_color="white").pack(expand=True)

        ctk.CTkLabel(self.sidebar, text="خانه ریمپ", font=("Tahoma", 20, "bold")).pack()
        ctk.CTkLabel(self.sidebar, text="SMART OBD  •  SERIAL STUDIO",
                     font=("Segoe UI", 10), text_color="#6ea4c7").pack(pady=(2, 28))

        self.nav_buttons = {}
        for key, label, icon in [
            ("home", "ثبت دستگاه", "⚡"),
            ("registry", "دستگاه‌های ثبت‌شده", "▦"),
            ("firmware", "Firmware", "◈"),
            ("settings", "تنظیمات و پشتیبان", "⚙"),
        ]:
            b = ctk.CTkButton(
                self.sidebar, text=f"{icon}   {label}",
                height=46, corner_radius=12, anchor="e",
                font=("Tahoma", 12, "bold"),
                fg_color="transparent", hover_color="#102b40",
                command=lambda k=key: self.show_page(k)
            )
            b.pack(fill="x", padx=14, pady=5)
            self.nav_buttons[key] = b

        ctk.CTkLabel(self.sidebar, text="v2.0  •  Production Tool",
                     font=("Segoe UI", 9), text_color="#45647a").pack(side="bottom", pady=18)

        self.main = ctk.CTkFrame(self, corner_radius=0, fg_color="#07111f")
        self.main.grid(row=0, column=1, sticky="nsew")
        self.main.grid_columnconfigure(0, weight=1)
        self.main.grid_rowconfigure(0, weight=1)

        self.pages = {}
        self.pages["home"] = self._home_page()
        self.pages["registry"] = self._registry_page()
        self.pages["firmware"] = self._firmware_page()
        self.pages["settings"] = self._settings_page()
        self.show_page("home")

    def page_shell(self, title, subtitle):
        f = ctk.CTkFrame(self.main, corner_radius=0, fg_color="#07111f")
        header = ctk.CTkFrame(f, height=92, corner_radius=0, fg_color="#07111f")
        header.pack(fill="x", padx=28, pady=(22, 4))
        ctk.CTkLabel(header, text=title, font=("Tahoma", 24, "bold"), anchor="e").pack(anchor="e")
        ctk.CTkLabel(header, text=subtitle, font=("Tahoma", 11),
                     text_color="#7ea6bd", anchor="e").pack(anchor="e", pady=(5, 0))
        return f

    def _home_page(self):
        page = self.page_shell(
            "ثبت سریع دستگاه",
            "برد ESP32-C3 را وصل کنید؛ برنامه شناسه واقعی را می‌خواند و سریال، QR و لیبل را یکجا می‌سازد."
        )

        stats = ctk.CTkFrame(page, fg_color="transparent")
        stats.pack(fill="x", padx=28, pady=(4, 10))
        self.stat_total = self.stat_card(stats, "کل دستگاه‌ها", "0", "▦")
        self.stat_today = self.stat_card(stats, "ثبت امروز", "0", "●")
        self.stat_next = self.stat_card(stats, "سریال بعدی", "-", "#")

        card = ctk.CTkFrame(page, corner_radius=20, fg_color="#0b1a29",
                            border_width=1, border_color="#173a54")
        card.pack(fill="both", expand=True, padx=28, pady=(8, 26))

        step = ctk.CTkFrame(card, fg_color="transparent")
        step.pack(fill="x", padx=24, pady=(24, 12))

        self.port_status = ctk.CTkLabel(step, text="● منتظر اتصال", font=("Tahoma", 12, "bold"),
                                        text_color="#ffbd4a")
        self.port_status.pack(side="right", padx=(0, 10))

        ctk.CTkButton(step, text="↻ تازه‌سازی", width=110, height=38, corner_radius=10,
                      fg_color="#173650", command=self.refresh_ports).pack(side="left", padx=5)

        self.port_box = ctk.CTkComboBox(step, width=250, height=38, values=["بدون پورت"])
        self.port_box.pack(side="left", padx=5)

        ctk.CTkButton(step, text="۱) شناسایی ESP32-C3", width=190, height=38,
                      corner_radius=10, command=self.read_device_async).pack(side="left", padx=5)

        info = ctk.CTkFrame(card, corner_radius=15, fg_color="#081522")
        info.pack(fill="x", padx=24, pady=12)
        info.grid_columnconfigure((0, 1, 2), weight=1)
        self.serial_preview = self.info_cell(info, 0, "سریال پیشنهادی", "-")
        self.chip_value = self.info_cell(info, 1, "شناسه سخت‌افزار", "-")
        self.mac_value = self.info_cell(info, 2, "MAC", "-")

        guide = ctk.CTkFrame(card, corner_radius=15, fg_color="#0d2233")
        guide.pack(fill="x", padx=24, pady=(8, 12))
        ctk.CTkLabel(guide, text="① USB را وصل کن   ←   ② شناسایی را بزن   ←   ③ «ثبت و ساخت لیبل» را بزن",
                     font=("Tahoma", 13, "bold"), text_color="#9ed9ff").pack(pady=16)

        self.progress = ctk.CTkProgressBar(card, height=8, corner_radius=4)
        self.progress.pack(fill="x", padx=24, pady=(5, 8))
        self.progress.set(0)

        self.action_status = ctk.CTkLabel(card, text="آماده", font=("Tahoma", 11),
                                          text_color="#76a7c2")
        self.action_status.pack(pady=(0, 8))

        actions = ctk.CTkFrame(card, fg_color="transparent")
        actions.pack(fill="x", padx=24, pady=(6, 24))
        self.register_btn = ctk.CTkButton(
            actions, text="۲) ثبت دستگاه + ساخت QR و لیبل",
            font=("Tahoma", 16, "bold"), height=58, corner_radius=16,
            fg_color="#087ebd", hover_color="#0b96df", command=self.register_current
        )
        self.register_btn.pack(side="right", fill="x", expand=True, padx=(5, 10))

        ctk.CTkButton(actions, text="باز کردن پوشه لیبل‌ها", width=190, height=58,
                      corner_radius=16, fg_color="#19364c", hover_color="#24506f",
                      command=lambda: self.open_path(output_dir())).pack(side="left", padx=(10, 5))
        return page

    def stat_card(self, parent, title, value, icon):
        f = ctk.CTkFrame(parent, corner_radius=16, fg_color="#0b1a29",
                         border_width=1, border_color="#15354d")
        f.pack(side="right", fill="x", expand=True, padx=6)
        top = ctk.CTkFrame(f, fg_color="transparent")
        top.pack(fill="x", padx=14, pady=(12, 2))
        ctk.CTkLabel(top, text=icon, font=("Segoe UI", 18, "bold"),
                     text_color="#40b9ff").pack(side="left")
        ctk.CTkLabel(top, text=title, font=("Tahoma", 10),
                     text_color="#7ea6bd").pack(side="right")
        val = ctk.CTkLabel(f, text=value, font=("Segoe UI", 20, "bold"))
        val.pack(anchor="e", padx=14, pady=(0, 12))
        return val

    def info_cell(self, parent, col, label, value):
        f = ctk.CTkFrame(parent, corner_radius=12, fg_color="#0c2030")
        f.grid(row=0, column=col, padx=8, pady=12, sticky="nsew")
        ctk.CTkLabel(f, text=label, font=("Tahoma", 10),
                     text_color="#6f9ab4").pack(anchor="e", padx=12, pady=(10, 2))
        v = ctk.CTkLabel(f, text=value, font=("Consolas", 14, "bold"), text_color="#ffffff")
        v.pack(anchor="e", padx=12, pady=(0, 12))
        return v

    def _registry_page(self):
        page = self.page_shell("دستگاه‌های ثبت‌شده",
                               "جستجو، بازسازی QR/لیبل، کپی سریال و خروجی بانک دستگاه‌ها.")
        bar = ctk.CTkFrame(page, fg_color="transparent")
        bar.pack(fill="x", padx=28, pady=(8, 12))

        self.search_var = ctk.StringVar()
        search = ctk.CTkEntry(bar, textvariable=self.search_var, width=340, height=40,
                              placeholder_text="جستجو: سریال، MAC یا Chip ID")
        search.pack(side="right", padx=5)
        search.bind("<KeyRelease>", lambda e: self.refresh_registry())

        ctk.CTkButton(bar, text="خروجی CSV", width=120, height=40,
                      command=self.export_csv_ui).pack(side="left", padx=5)
        ctk.CTkButton(bar, text="↻", width=48, height=40,
                      command=self.refresh_registry).pack(side="left", padx=5)

        frame = ctk.CTkFrame(page, corner_radius=16, fg_color="#0b1a29")
        frame.pack(fill="both", expand=True, padx=28, pady=(0, 12))

        cols = ("serial", "chip_id", "mac", "created_at", "operator", "status")
        self.tree = ttk.Treeview(frame, columns=cols, show="headings", selectmode="browse")
        labels = {"serial":"Serial","chip_id":"Chip ID","mac":"MAC","created_at":"Created","operator":"Operator","status":"Status"}
        widths = {"serial":150,"chip_id":180,"mac":150,"created_at":160,"operator":130,"status":90}
        for c in cols:
            self.tree.heading(c, text=labels[c])
            self.tree.column(c, width=widths[c], anchor="center")
        y = ttk.Scrollbar(frame, orient="vertical", command=self.tree.yview)
        self.tree.configure(yscrollcommand=y.set)
        self.tree.pack(side="left", fill="both", expand=True, padx=(12,0), pady=12)
        y.pack(side="right", fill="y", padx=(0,12), pady=12)

        actions = ctk.CTkFrame(page, fg_color="transparent")
        actions.pack(fill="x", padx=28, pady=(0,22))
        ctk.CTkButton(actions, text="بازسازی QR و لیبل", height=42,
                      command=self.rebuild_selected).pack(side="right", padx=5)
        ctk.CTkButton(actions, text="کپی سریال", height=42, fg_color="#173650",
                      command=self.copy_selected_serial).pack(side="right", padx=5)
        ctk.CTkButton(actions, text="باز کردن پوشه دستگاه", height=42, fg_color="#173650",
                      command=self.open_selected_folder).pack(side="right", padx=5)
        return page

    def _firmware_page(self):
        page = self.page_shell(
            "Firmware",
            "فلش اختیاری فایل merged .bin روی ESP32-C3. برای نسخه تست، Secure Boot/eFuse فعال نمی‌شود."
        )
        card = ctk.CTkFrame(page, corner_radius=18, fg_color="#0b1a29",
                            border_width=1, border_color="#173a54")
        card.pack(fill="x", padx=28, pady=12)

        ctk.CTkLabel(card, text="Firmware merged BIN", font=("Segoe UI", 12, "bold")).pack(
            anchor="w", padx=22, pady=(22,5))
        row = ctk.CTkFrame(card, fg_color="transparent")
        row.pack(fill="x", padx=20, pady=(0,12))
        self.fw_entry = ctk.CTkEntry(row, height=40)
        self.fw_entry.pack(side="left", fill="x", expand=True, padx=4)
        self.fw_entry.insert(0, self.settings.get("firmware_path",""))
        ctk.CTkButton(row, text="انتخاب فایل", width=120, height=40,
                      command=self.choose_firmware).pack(side="right", padx=4)

        row2 = ctk.CTkFrame(card, fg_color="transparent")
        row2.pack(fill="x", padx=20, pady=(0,18))
        ctk.CTkLabel(row2, text="Flash address", font=("Segoe UI",11)).pack(side="left", padx=4)
        self.addr_entry = ctk.CTkEntry(row2, width=120, height=38)
        self.addr_entry.pack(side="left", padx=4)
        self.addr_entry.insert(0, self.settings.get("flash_address","0x0"))
        ctk.CTkButton(row2, text="فلش Firmware روی برد متصل", height=42,
                      fg_color="#0a7fad", command=self.flash_async).pack(side="right", padx=4)

        notice = ctk.CTkFrame(page, corner_radius=16, fg_color="#102334")
        notice.pack(fill="x", padx=28, pady=10)
        ctk.CTkLabel(
            notice,
            text="نکته: این قسمت فقط برای فایل merged مناسب است. قفل نهایی Flash Encryption / Secure Boot در مرحله Production اضافه می‌شود.",
            font=("Tahoma",11), text_color="#ffd27a", wraplength=800, justify="right"
        ).pack(padx=20, pady=18, anchor="e")
        return page

    def _settings_page(self):
        page = self.page_shell("تنظیمات و پشتیبان",
                               "تنظیم اپراتور، پیشوند سریال، خروجی و نسخه پشتیبان بانک دستگاه‌ها.")
        card = ctk.CTkFrame(page, corner_radius=18, fg_color="#0b1a29")
        card.pack(fill="x", padx=28, pady=12)

        grid = ctk.CTkFrame(card, fg_color="transparent")
        grid.pack(fill="x", padx=22, pady=22)
        grid.grid_columnconfigure(0, weight=1)
        grid.grid_columnconfigure(1, weight=1)

        ctk.CTkLabel(grid, text="نام اپراتور", font=("Tahoma",11)).grid(row=0,column=1,sticky="e",pady=(0,4))
        self.operator_entry = ctk.CTkEntry(grid, height=40, justify="right")
        self.operator_entry.grid(row=1,column=1,sticky="ew",padx=(8,0))
        self.operator_entry.insert(0, self.settings.get("operator",""))

        ctk.CTkLabel(grid, text="پیشوند سریال", font=("Tahoma",11)).grid(row=0,column=0,sticky="e",pady=(0,4))
        self.prefix_entry = ctk.CTkEntry(grid, height=40)
        self.prefix_entry.grid(row=1,column=0,sticky="ew",padx=(0,8))
        self.prefix_entry.insert(0, self.settings.get("prefix",DEFAULT_PREFIX))

        ctk.CTkButton(card, text="ذخیره تنظیمات", height=42,
                      command=self.save_settings_ui).pack(anchor="e", padx=22, pady=(0,22))

        card2 = ctk.CTkFrame(page, corner_radius=18, fg_color="#0b1a29")
        card2.pack(fill="x", padx=28, pady=10)
        ctk.CTkLabel(card2, text="بانک دستگاه‌ها", font=("Tahoma",14,"bold")).pack(anchor="e", padx=22, pady=(18,10))
        b = ctk.CTkFrame(card2, fg_color="transparent")
        b.pack(fill="x", padx=18, pady=(0,18))
        ctk.CTkButton(b, text="ساخت نسخه پشتیبان ZIP", height=42,
                      command=self.backup_ui).pack(side="right", padx=4)
        ctk.CTkButton(b, text="خروجی CSV برای سرور", height=42, fg_color="#173650",
                      command=self.export_csv_ui).pack(side="right", padx=4)
        ctk.CTkButton(b, text="باز کردن پوشه داده‌ها", height=42, fg_color="#173650",
                      command=lambda:self.open_path(DATA_DIR)).pack(side="right", padx=4)
        return page

    def show_page(self, key):
        for p in self.pages.values():
            p.pack_forget()
        self.pages[key].pack(fill="both", expand=True)
        for k,b in self.nav_buttons.items():
            b.configure(fg_color="#11324a" if k==key else "transparent")

    def refresh_ports(self):
        ports = list(list_ports.comports())
        names = [p.device for p in ports]
        if names:
            self.port_box.configure(values=names)
            preferred = None
            preferred_vids = {0x303A, 0x10C4, 0x1A86}  # Espressif, CP210x, CH34x
            for p in ports:
                desc = f"{p.description} {p.manufacturer or ''} {p.hwid}".lower()
                if (getattr(p, "vid", None) in preferred_vids or
                        any(k in desc for k in [
                            "espressif", "esp32", "usb jtag", "usb serial",
                            "cp210", "ch340", "ch341", "wch", "uart"
                        ])):
                    preferred = p.device
                    break
            self.port_box.set(preferred or names[0])
            self.port_status.configure(
                text=f"● {len(names)} پورت پیدا شد" + (f"  •  {preferred}" if preferred else ""),
                text_color="#67e8a6"
            )
        else:
            self.port_box.configure(values=["بدون پورت"])
            self.port_box.set("بدون پورت")
            self.port_status.configure(text="● برد پیدا نشد", text_color="#ff7d7d")

    def set_busy(self, busy, status=None):
        self.busy = busy
        self.register_btn.configure(state="disabled" if busy else "normal")
        if busy:
            self.progress.configure(mode="indeterminate")
            self.progress.start()
        else:
            self.progress.stop()
            self.progress.configure(mode="determinate")
            self.progress.set(1 if self.current_chip else 0)
        if status:
            self.action_status.configure(text=status)

    def read_device_async(self):
        if self.busy:
            return
        port = self.port_box.get().strip()
        if not port or port=="بدون پورت":
            messagebox.showwarning("اتصال","ابتدا ESP32-C3 را با کابل USB دیتادار وصل کنید.")
            return
        self.set_busy(True,"در حال خواندن شناسه واقعی ESP32-C3 ...")
        threading.Thread(target=self._read_worker,args=(port,),daemon=True).start()

    def _read_worker(self, port):
        try:
            chip,mac,raw = read_esp32c3(port)
            self.after(0,lambda:self._read_success(port,chip,mac,raw))
        except Exception as exc:
            self.after(0,lambda:self._read_error(str(exc)))

    def _read_success(self, port, chip, mac, raw):
        self.current_port=port
        self.current_chip=chip
        self.current_mac=mac
        self.current_raw=raw
        self.chip_value.configure(text=chip)
        self.mac_value.configure(text=mac or "-")
        prefix=self.settings.get("prefix",DEFAULT_PREFIX).strip().upper() or DEFAULT_PREFIX
        old=db_find_by_hw(chip,mac)
        if old:
            self.serial_preview.configure(text=old["serial"])
            self.action_status.configure(text=f"این برد قبلاً ثبت شده است: {old['serial']}", text_color="#ffd27a")
        else:
            self.serial_preview.configure(text=next_serial(prefix))
            self.action_status.configure(text="برد آماده ثبت است.", text_color="#67e8a6")
        self.set_busy(False)
        self.refresh_stats()

    def _read_error(self,msg):
        self.current_chip=""
        self.current_mac=""
        self.chip_value.configure(text="-")
        self.mac_value.configure(text="-")
        self.serial_preview.configure(text="-")
        self.set_busy(False,"خواندن برد ناموفق بود.")
        messagebox.showerror("ESP32-C3",msg)

    def register_current(self):
        if self.busy:
            return
        if not self.current_chip:
            messagebox.showinfo("مرحله اول","اول دکمه «شناسایی ESP32-C3» را بزنید.")
            return

        old=db_find_by_hw(self.current_chip,self.current_mac)
        if old:
            create_qr_and_label(old)
            self.serial_preview.configure(text=old["serial"])
            messagebox.showinfo("قبلاً ثبت شده",f"این سخت‌افزار قبلاً با سریال {old['serial']} ثبت شده بود.\nQR و لیبل دوباره ساخته شد.")
            return

        prefix=self.settings.get("prefix",DEFAULT_PREFIX).strip().upper() or DEFAULT_PREFIX
        serial_no=next_serial(prefix)
        record={
            "serial":serial_no,
            "chip_id":self.current_chip,
            "mac":self.current_mac,
            "activation_token":secrets.token_urlsafe(32),
            "created_at":datetime.now().isoformat(timespec="seconds"),
            "operator":self.settings.get("operator",""),
            "firmware":Path(self.settings.get("firmware_path","")).name if self.settings.get("firmware_path") else "",
            "status":"READY",
            "note":"",
        }
        try:
            db_insert(record)
            _,label_path,_=create_qr_and_label(record)
        except Exception as exc:
            messagebox.showerror("ثبت دستگاه",str(exc))
            return

        self.serial_preview.configure(text=serial_no)
        self.action_status.configure(text=f"ثبت شد ✓  سریال {serial_no}", text_color="#67e8a6")
        self.refresh_registry()
        self.refresh_stats()
        if messagebox.askyesno("ثبت موفق",f"سریال دستگاه:\n{serial_no}\n\nQR و لیبل ساخته شد.\nلیبل باز شود؟"):
            self.open_path(label_path)

    def refresh_stats(self):
        rows=db_rows()
        today=datetime.now().date().isoformat()
        count_today=sum(1 for r in rows if str(r.get("created_at","")).startswith(today))
        prefix=self.settings.get("prefix",DEFAULT_PREFIX).strip().upper() or DEFAULT_PREFIX
        self.stat_total.configure(text=str(len(rows)))
        self.stat_today.configure(text=str(count_today))
        self.stat_next.configure(text=next_serial(prefix))

    def refresh_registry(self):
        if not hasattr(self,"tree"):
            return
        for item in self.tree.get_children():
            self.tree.delete(item)
        q=self.search_var.get() if hasattr(self,"search_var") else ""
        for r in db_rows(q):
            self.tree.insert("", "end", iid=str(r["id"]),
                             values=(r["serial"],r["chip_id"],r.get("mac",""),r["created_at"],r.get("operator",""),r.get("status","")))

    def selected_record(self):
        sel=self.tree.selection()
        if not sel:
            messagebox.showinfo("انتخاب دستگاه","یک دستگاه را از لیست انتخاب کنید.")
            return None
        rid=int(sel[0])
        for r in db_rows():
            if int(r["id"])==rid:
                return r
        return None

    def rebuild_selected(self):
        r=self.selected_record()
        if not r:
            return
        _,label,_=create_qr_and_label(r)
        messagebox.showinfo("انجام شد",f"QR و لیبل {r['serial']} دوباره ساخته شد.")
        self.open_path(label)

    def copy_selected_serial(self):
        r=self.selected_record()
        if not r:
            return
        self.clipboard_clear()
        self.clipboard_append(r["serial"])
        self.update()
        messagebox.showinfo("کپی شد",r["serial"])

    def open_selected_folder(self):
        r=self.selected_record()
        if r:
            self.open_path(output_dir()/r["serial"])

    def choose_firmware(self):
        p=filedialog.askopenfilename(title="انتخاب Firmware merged",
                                     filetypes=[("Firmware BIN","*.bin"),("All files","*.*")])
        if p:
            self.fw_entry.delete(0,"end")
            self.fw_entry.insert(0,p)
            self.settings["firmware_path"]=p
            save_settings(self.settings)

    def flash_async(self):
        if self.busy:
            return
        port=self.port_box.get().strip()
        fw=self.fw_entry.get().strip()
        addr=self.addr_entry.get().strip() or "0x0"
        if not port or port=="بدون پورت":
            messagebox.showwarning("اتصال","پورت ESP32-C3 را انتخاب کنید.")
            return
        if not fw or not Path(fw).exists():
            messagebox.showwarning("Firmware","یک فایل merged .bin معتبر انتخاب کنید.")
            return
        if not messagebox.askyesno("فلش Firmware","فایل انتخاب‌شده روی برد نوشته شود؟\nاین عملیات Secure Boot یا eFuse را فعال نمی‌کند."):
            return
        self.set_busy(True,"در حال فلش Firmware ...")
        threading.Thread(target=self._flash_worker,args=(port,fw,addr),daemon=True).start()

    def _flash_worker(self,port,fw,addr):
        try:
            flash_merged_bin(port,fw,addr)
            self.after(0,lambda:self._flash_done(True,"Firmware با موفقیت نوشته شد."))
        except Exception as exc:
            self.after(0,lambda:self._flash_done(False,str(exc)))

    def _flash_done(self,ok,msg):
        self.set_busy(False,msg if ok else "فلش ناموفق بود.")
        if ok:
            messagebox.showinfo("Firmware",msg)
        else:
            messagebox.showerror("Firmware",msg)

    def save_settings_ui(self):
        prefix=re.sub(r"[^A-Za-z0-9_-]","",self.prefix_entry.get().strip().upper())[:8] or DEFAULT_PREFIX
        self.settings["prefix"]=prefix
        self.settings["operator"]=self.operator_entry.get().strip()
        self.settings["firmware_path"]=self.fw_entry.get().strip()
        self.settings["flash_address"]=self.addr_entry.get().strip() or "0x0"
        save_settings(self.settings)
        self.refresh_stats()
        messagebox.showinfo("تنظیمات","تنظیمات ذخیره شد.")

    def export_csv_ui(self):
        default=f"KhanehRemap-Devices-{datetime.now().strftime('%Y%m%d-%H%M')}.csv"
        p=filedialog.asksaveasfilename(defaultextension=".csv",initialfile=default,filetypes=[("CSV","*.csv")])
        if p:
            try:
                export_csv(Path(p))
                messagebox.showinfo("خروجی","فایل CSV ساخته شد.")
            except Exception as exc:
                messagebox.showerror("خروجی",str(exc))

    def backup_ui(self):
        default=f"KhanehRemap-SerialMaker-Backup-{datetime.now().strftime('%Y%m%d-%H%M')}.zip"
        p=filedialog.asksaveasfilename(defaultextension=".zip",initialfile=default,filetypes=[("ZIP","*.zip")])
        if p:
            try:
                made=backup_all(p)
                messagebox.showinfo("پشتیبان",f"نسخه پشتیبان ساخته شد:\n{made}")
            except Exception as exc:
                messagebox.showerror("پشتیبان",str(exc))

    def open_path(self,path):
        path=Path(path)
        try:
            if path.is_file():
                os.startfile(str(path))
            else:
                path.mkdir(parents=True,exist_ok=True)
                os.startfile(str(path))
        except Exception as exc:
            messagebox.showerror("باز کردن",str(exc))


if __name__=="__main__":
    if acquire_single_instance():
        SerialMakerApp().mainloop()
