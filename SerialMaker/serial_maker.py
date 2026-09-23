import csv
import json
import os
import re
import subprocess
import sys
import tkinter as tk
from datetime import datetime
from pathlib import Path
from tkinter import ttk, messagebox, filedialog

import qrcode
import serial.tools.list_ports

APP_TITLE = "خانه ریمپ - Serial Maker"
PREFIX = "KR"
DB_FILE = "devices.csv"

def app_dir():
    if getattr(sys, "frozen", False):
        return Path(sys.executable).resolve().parent
    return Path(__file__).resolve().parent

def db_path():
    return app_dir() / DB_FILE

def load_devices():
    path = db_path()
    if not path.exists():
        return []
    rows = []
    with open(path, "r", encoding="utf-8-sig", newline="") as f:
        for row in csv.DictReader(f):
            rows.append(row)
    return rows

def save_device(row):
    path = db_path()
    exists = path.exists()
    with open(path, "a", encoding="utf-8-sig", newline="") as f:
        fields = ["serial","chip_id","mac","created_at"]
        w = csv.DictWriter(f, fieldnames=fields)
        if not exists:
            w.writeheader()
        w.writerow(row)

def next_serial():
    rows = load_devices()
    max_n = 0
    rx = re.compile(rf"^{PREFIX}-\d{{2}}-(\d+)$")
    for r in rows:
        m = rx.match(r.get("serial",""))
        if m:
            max_n = max(max_n, int(m.group(1)))
    yy = datetime.now().strftime("%y")
    return f"{PREFIX}-{yy}-{max_n+1:06d}"

def run_esptool(port):
    commands = [
        [sys.executable, "-m", "esptool", "--chip", "esp32c3", "--port", port, "chip_id"],
        ["esptool", "--chip", "esp32c3", "--port", port, "chip_id"],
    ]
    last = ""
    for cmd in commands:
        try:
            p = subprocess.run(cmd, capture_output=True, text=True, timeout=20)
            out = (p.stdout or "") + "\n" + (p.stderr or "")
            last = out
            if p.returncode == 0:
                return out
        except Exception as e:
            last = str(e)
    raise RuntimeError(last or "esptool اجرا نشد")

def parse_ids(text):
    chip_id = ""
    mac = ""
    m = re.search(r"Chip ID:\s*(0x[0-9a-fA-F]+)", text)
    if m:
        chip_id = m.group(1).upper()
    m = re.search(r"MAC:\s*([0-9a-fA-F:]{17})", text)
    if m:
        mac = m.group(1).upper()
    if not chip_id:
        m = re.search(r"MAC:\s*([0-9a-fA-F:]{17})", text)
        if m:
            chip_id = m.group(1).replace(":","").upper()
    return chip_id, mac

def qr_payload(serial_no, chip_id, mac):
    return json.dumps({
        "brand": "KHANEH_REMAP",
        "serial": serial_no,
        "chip_id": chip_id,
        "mac": mac
    }, ensure_ascii=False, separators=(",",":"))

class App(tk.Tk):
    def __init__(self):
        super().__init__()
        self.title(APP_TITLE)
        self.geometry("760x520")
        self.minsize(720, 480)
        self.configure(bg="#0b1220")

        style = ttk.Style(self)
        try:
            style.theme_use("clam")
        except:
            pass

        self.port_var = tk.StringVar()
        self.serial_var = tk.StringVar(value="-")
        self.chip_var = tk.StringVar(value="-")
        self.mac_var = tk.StringVar(value="-")
        self.status_var = tk.StringVar(value="برد ESP32-C3 را با USB وصل کنید.")

        title = tk.Label(self, text="خانه ریمپ | Serial Maker", font=("Segoe UI", 22, "bold"),
                         fg="#ffffff", bg="#0b1220")
        title.pack(pady=(22, 4))
        sub = tk.Label(self, text="ثبت سریال اختصاصی برای ESP32-C3 Smart OBD",
                       font=("Segoe UI", 11), fg="#8fb3d9", bg="#0b1220")
        sub.pack(pady=(0, 20))

        card = tk.Frame(self, bg="#121c2e", bd=0)
        card.pack(fill="x", padx=28, pady=8)

        row1 = tk.Frame(card, bg="#121c2e")
        row1.pack(fill="x", padx=18, pady=(18, 10))
        tk.Label(row1, text="پورت:", fg="white", bg="#121c2e", font=("Segoe UI", 11)).pack(side="right", padx=8)
        self.combo = ttk.Combobox(row1, textvariable=self.port_var, state="readonly", width=28)
        self.combo.pack(side="right")
        ttk.Button(row1, text="به‌روزرسانی پورت‌ها", command=self.refresh_ports).pack(side="right", padx=10)

        ttk.Button(card, text="خواندن شناسه ESP32-C3", command=self.read_device).pack(pady=12)
        ttk.Button(card, text="ثبت دستگاه جدید و ساخت QR", command=self.register_device).pack(pady=(0, 18))

        info = tk.Frame(self, bg="#0b1220")
        info.pack(fill="x", padx=28, pady=8)

        self.add_info(info, "شماره سریال", self.serial_var)
        self.add_info(info, "Chip ID", self.chip_var)
        self.add_info(info, "MAC", self.mac_var)

        tk.Label(self, textvariable=self.status_var, fg="#6ee7b7", bg="#0b1220",
                 font=("Segoe UI", 10)).pack(pady=10)

        bottom = tk.Frame(self, bg="#0b1220")
        bottom.pack(fill="x", padx=28, pady=8)
        ttk.Button(bottom, text="باز کردن پوشه اطلاعات", command=self.open_folder).pack(side="right", padx=6)
        ttk.Button(bottom, text="نمایش لیست دستگاه‌ها", command=self.show_devices).pack(side="right", padx=6)

        self.refresh_ports()

    def add_info(self, parent, label, var):
        f = tk.Frame(parent, bg="#121c2e")
        f.pack(fill="x", pady=4)
        tk.Label(f, text=label + ":", width=16, anchor="e", fg="#9fc5ef",
                 bg="#121c2e", font=("Segoe UI", 10, "bold")).pack(side="right", padx=8, pady=10)
        tk.Label(f, textvariable=var, anchor="w", fg="white", bg="#121c2e",
                 font=("Consolas", 11)).pack(side="left", fill="x", expand=True, padx=10)

    def refresh_ports(self):
        ports = [p.device for p in serial.tools.list_ports.comports()]
        self.combo["values"] = ports
        if ports:
            self.port_var.set(ports[0])
            self.status_var.set(f"{len(ports)} پورت پیدا شد.")
        else:
            self.port_var.set("")
            self.status_var.set("هیچ پورت سریالی پیدا نشد.")

    def read_device(self):
        port = self.port_var.get().strip()
        if not port:
            messagebox.showwarning("پورت", "ابتدا پورت ESP32-C3 را انتخاب کنید.")
            return
        self.status_var.set("در حال خواندن شناسه برد...")
        self.update_idletasks()
        try:
            out = run_esptool(port)
            chip_id, mac = parse_ids(out)
            if not chip_id and not mac:
                raise RuntimeError("شناسه معتبر از برد دریافت نشد. در صورت نیاز BOOT را نگه دارید و دوباره امتحان کنید.")
            self.chip_var.set(chip_id or "-")
            self.mac_var.set(mac or "-")
            self.status_var.set("شناسه برد با موفقیت خوانده شد.")
        except Exception as e:
            self.status_var.set("خطا در خواندن برد.")
            messagebox.showerror("خطا", str(e))

    def register_device(self):
        if self.chip_var.get() == "-" and self.mac_var.get() == "-":
            self.read_device()
            if self.chip_var.get() == "-" and self.mac_var.get() == "-":
                return

        rows = load_devices()
        chip = self.chip_var.get()
        mac = self.mac_var.get()
        for r in rows:
            if chip != "-" and r.get("chip_id") == chip:
                self.serial_var.set(r.get("serial","-"))
                messagebox.showinfo("قبلاً ثبت شده", f"این برد قبلاً با سریال {r.get('serial')} ثبت شده است.")
                return
            if mac != "-" and r.get("mac") == mac:
                self.serial_var.set(r.get("serial","-"))
                messagebox.showinfo("قبلاً ثبت شده", f"این برد قبلاً با سریال {r.get('serial')} ثبت شده است.")
                return

        sn = next_serial()
        row = {
            "serial": sn,
            "chip_id": chip,
            "mac": mac,
            "created_at": datetime.now().isoformat(timespec="seconds")
        }
        save_device(row)

        payload = qr_payload(sn, chip, mac)
        qr = qrcode.QRCode(version=None, box_size=10, border=4)
        qr.add_data(payload)
        qr.make(fit=True)
        img = qr.make_image(fill_color="black", back_color="white")
        qr_file = app_dir() / f"{sn}-QR.png"
        img.save(qr_file)

        self.serial_var.set(sn)
        self.status_var.set(f"دستگاه ثبت شد؛ QR: {qr_file.name}")
        messagebox.showinfo("ثبت شد", f"سریال: {sn}\n\nQR در کنار برنامه ذخیره شد.")

    def open_folder(self):
        folder = str(app_dir())
        try:
            os.startfile(folder)
        except:
            messagebox.showinfo("مسیر", folder)

    def show_devices(self):
        rows = load_devices()
        if not rows:
            messagebox.showinfo("دستگاه‌ها", "هنوز دستگاهی ثبت نشده است.")
            return
        win = tk.Toplevel(self)
        win.title("دستگاه‌های ثبت‌شده")
        win.geometry("760x380")
        cols = ("serial","chip_id","mac","created_at")
        tree = ttk.Treeview(win, columns=cols, show="headings")
        for c, t, w in [
            ("serial","Serial",140),
            ("chip_id","Chip ID",170),
            ("mac","MAC",170),
            ("created_at","Created",180),
        ]:
            tree.heading(c, text=t)
            tree.column(c, width=w, anchor="center")
        for r in rows:
            tree.insert("", "end", values=[r.get(c,"") for c in cols])
        tree.pack(fill="both", expand=True, padx=10, pady=10)

if __name__ == "__main__":
    App().mainloop()
