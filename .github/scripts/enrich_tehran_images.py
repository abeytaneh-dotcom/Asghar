from __future__ import annotations

import base64
import gzip
import hashlib
import io
import json
import os
import pathlib
import re
import sys
import time
import unicodedata
import zipfile
from concurrent.futures import ThreadPoolExecutor, as_completed

from curl_cffi import requests
from PIL import Image
from rapidfuzz import fuzz, process


work = pathlib.Path(sys.argv[1])
work.mkdir(parents=True, exist_ok=True)
(work / "images").mkdir(exist_ok=True)
(work / "bundles").mkdir(exist_ok=True)

seed_file = pathlib.Path("TehranSupermarket/pyramid_seed_1400_data.php")
text = seed_file.read_text("utf-8", errors="replace")
m = re.search(r"return\s+'([^']+)'\s*;", text, re.S)
if not m:
    raise SystemExit("seed payload not found")
payload = gzip.decompress(base64.b64decode(m.group(1))).decode("utf-8")

targets: list[dict[str, str]] = []
for line in payload.splitlines():
    line = line.strip()
    if not line:
        continue
    r = json.loads(line)
    if r.get("code2") and r.get("name"):
        targets.append(
            {
                "code2": str(r["code2"]).strip(),
                "barcode": str(r.get("barcode") or "").strip(),
                "name": str(r["name"]).strip(),
            }
        )
print("TARGETS", len(targets), flush=True)

tr = str.maketrans(
    {
        "ي": "ی",
        "ى": "ی",
        "ك": "ک",
        "ة": "ه",
        "ۀ": "ه",
        "ؤ": "و",
        "إ": "ا",
        "أ": "ا",
        "آ": "ا",
        "۰": "0",
        "۱": "1",
        "۲": "2",
        "۳": "3",
        "۴": "4",
        "۵": "5",
        "۶": "6",
        "۷": "7",
        "۸": "8",
        "۹": "9",
    }
)
stop = {
    "محصول",
    "بسته",
    "عددی",
    "عدد",
    "گرمی",
    "گرم",
    "کیلوگرم",
    "کیلو",
    "میلی",
    "لیتر",
    "مدل",
    "نوع",
    "با",
    "بدون",
}


def norm(s: object) -> str:
    out = unicodedata.normalize("NFKC", str(s)).translate(tr).lower()
    out = re.sub(r"[\u064b-\u065f\u0670\u200c\u200d\ufeff]", " ", out)
    out = re.sub(r"[^0-9a-zآ-ی]+", " ", out)
    return " ".join(out.split())


def toks(s: object) -> list[str]:
    return [x for x in norm(s).split() if x not in stop]


def nums(s: object) -> set[str]:
    return set(re.findall(r"\d+(?:[.,]\d+)?", norm(s)))


def barcode_like(v: object) -> str:
    x = re.sub(r"\D", "", str(v or ""))
    return x if 8 <= len(x) <= 14 else ""


def walk(obj: object, path: str = ""):
    if isinstance(obj, dict):
        for k, v in obj.items():
            yield from walk(v, path + "/" + str(k))
    elif isinstance(obj, list):
        for i, v in enumerate(obj):
            yield from walk(v, path + f"/{i}")
    else:
        yield path, obj


def pick_image(p: dict) -> str:
    cand: list[tuple[int, str]] = []
    for path, v in walk(p):
        if not isinstance(v, str):
            continue
        u = v.replace("\\u002F", "/").replace("\\/", "/").strip()
        if u.startswith("//"):
            u = "https:" + u
        if not u.startswith("http"):
            continue
        low = (path + " " + u).lower()
        score = 0
        if any(k in path.lower() for k in ["image", "picture", "photo", "thumbnail", "media"]):
            score += 5
        if "asset.okala.com" in low:
            score += 5
        if re.search(r"\.(?:jpe?g|png|webp)(?:\?|$)", u, re.I):
            score += 3
        if "icon" in low or "logo" in low:
            score -= 5
        if score > 0:
            cand.append((score, u))
    cand.sort(reverse=True)
    return cand[0][1] if cand else ""


def pick_barcode(p: dict) -> str:
    for path, v in walk(p):
        if re.search(r"(barcode|ean|gtin)", path, re.I):
            b = barcode_like(v)
            if b:
                return b
    return ""


sess = requests.Session(impersonate="chrome")
hdr = {
    "Accept": "application/json",
    "Accept-Language": "fa-IR,fa;q=0.9,en;q=0.7",
    "Origin": "https://www.okala.com",
    "Referer": "https://www.okala.com/",
    "source": "okala",
    "ui-version": "2.0",
    "x-user-unique-id": hashlib.md5(os.urandom(16)).hexdigest(),
}
lat, lon = 35.7372, 51.4154


def get_json(url: str, params: dict | None = None) -> dict:
    last = None
    for a in range(3):
        try:
            r = sess.get(url, params=params, headers=hdr, timeout=35)
            if r.status_code == 200:
                body = r.json()
                return body if isinstance(body, dict) else {}
            last = f"HTTP {r.status_code}"
        except Exception as e:
            last = repr(e)
        time.sleep(0.7 * (a + 1))
    print("WARN request failed", url, last, flush=True)
    return {}


tree = get_json(
    "https://apigateway.okala.com/api/unicorn/v3/categories/nearby",
    {"lat": lat, "lon": lon},
)
roots = ((tree.get("data") or {}).get("categories") or []) if isinstance(tree, dict) else []
leaves: list[str] = []
allnodes: list[str] = []


def rec(nodes: list[dict]) -> None:
    for n in nodes or []:
        slug = n.get("latinName")
        if slug:
            allnodes.append(slug)
        ch = n.get("children") or []
        if ch:
            rec(ch)
        elif slug:
            leaves.append(slug)


rec(roots)
slugs = list(dict.fromkeys(leaves + allnodes))[:220]
print("OKALA_SLUGS", len(slugs), flush=True)

sources: dict[str, dict] = {}
source_by_barcode: dict[str, dict] = {}
sample_keys: set[str] = set()
for idx, slug in enumerate(slugs, 1):
    body = get_json(
        "https://apigateway.okala.com/api/unicorn/v2/products/nearby",
        {"slug": slug, "lat": lat, "lon": lon},
    )
    groups = (body.get("data") or []) if isinstance(body, dict) else []
    for g in groups:
        for p in (g.get("products") or []):
            if not isinstance(p, dict):
                continue
            name = str(p.get("name") or "").strip()
            img = pick_image(p)
            if not name or not img:
                continue
            n = norm(name)
            if not n:
                continue
            b = pick_barcode(p)
            row = {"name": name, "norm": n, "image": img, "barcode": b, "source": "okala.com"}
            old = sources.get(n)
            if old is None or len(img) > len(old["image"]):
                sources[n] = row
            if b:
                source_by_barcode.setdefault(b, row)
            sample_keys.update(p.keys())
    if idx % 20 == 0:
        print("CRAWL", idx, "sources", len(sources), flush=True)
    time.sleep(0.18)

print(
    "SOURCE_PRODUCTS",
    len(sources),
    "BARCODES",
    len(source_by_barcode),
    "SAMPLE_KEYS",
    sorted(sample_keys)[:50],
    flush=True,
)
if not sources:
    raise SystemExit("Okala returned no product images")

src_rows = list(sources.values())
src_norms = [r["norm"] for r in src_rows]
exact = {r["norm"]: r for r in src_rows}
matches: list[dict] = []
for t in targets:
    tb = barcode_like(t["barcode"])
    chosen = None
    score = 0.0
    if tb and tb in source_by_barcode:
        chosen = source_by_barcode[tb]
        score = 100.0
    tn = norm(t["name"])
    if chosen is None and tn in exact:
        chosen = exact[tn]
        score = 100.0
    if chosen is None and tn:
        hit = process.extractOne(tn, src_norms, scorer=fuzz.WRatio, score_cutoff=91)
        if hit:
            _, score, ix = hit
            cand = src_rows[ix]
            at = set(toks(tn))
            bt = set(toks(cand["norm"]))
            overlap = at & bt
            an = nums(tn)
            bn = nums(cand["norm"])
            numeric_ok = not an or not bn or bool(an & bn)
            min_overlap = 1 if min(len(at), len(bt)) <= 2 else 2
            if numeric_ok and len(overlap) >= min_overlap and (
                score >= 95 or (score >= 92 and len(overlap) >= 2)
            ):
                chosen = cand
    if chosen:
        matches.append(
            {
                "code2": t["code2"],
                "barcode": t["barcode"],
                "target_name": t["name"],
                "source_name": chosen["name"],
                "url": chosen["image"],
                "source": chosen["source"],
                "score": round(float(score), 1),
            }
        )

print("HIGH_CONFIDENCE_MATCHES", len(matches), flush=True)
(work / "matches.json").write_text(json.dumps(matches, ensure_ascii=False, indent=2), "utf-8")

imgdir = work / "images"


def download_one(match: dict) -> dict | None:
    key = match["barcode"] or match["code2"]
    fname = "auto_" + hashlib.sha1(key.encode()).hexdigest()[:20] + ".webp"
    path = imgdir / fname
    try:
        r = requests.get(
            match["url"],
            impersonate="chrome",
            headers={"Referer": "https://www.okala.com/"},
            timeout=35,
        )
        if r.status_code != 200 or len(r.content) < 500:
            return None
        im = Image.open(io.BytesIO(r.content))
        im.load()
        if getattr(im, "is_animated", False):
            im.seek(0)
        if im.mode not in ("RGB", "RGBA"):
            im = im.convert("RGBA" if "A" in im.getbands() else "RGB")
        im.thumbnail((700, 700), Image.Resampling.LANCZOS)
        im.save(path, "WEBP", quality=82, method=6)
        if path.stat().st_size > 130000:
            im.thumbnail((600, 600), Image.Resampling.LANCZOS)
            im.save(path, "WEBP", quality=76, method=6)
        if path.stat().st_size > 170000:
            im.thumbnail((520, 520), Image.Resampling.LANCZOS)
            im.save(path, "WEBP", quality=70, method=6)
        out = dict(match)
        out["file"] = fname
        out["bytes"] = path.stat().st_size
        return out
    except Exception:
        try:
            path.unlink(missing_ok=True)
        except Exception:
            pass
        return None


good: list[dict] = []
with ThreadPoolExecutor(max_workers=8) as ex:
    futs = [ex.submit(download_one, item) for item in matches]
    for i, future in enumerate(as_completed(futs), 1):
        result = future.result()
        if result:
            good.append(result)
        if i % 250 == 0:
            print("DOWNLOAD", i, "ok", len(good), flush=True)

print("IMAGES_READY", len(good), flush=True)
if not good:
    raise SystemExit("no images downloaded")

sizes = [x["bytes"] for x in good]
print("IMAGE_BYTES avg", sum(sizes) // len(sizes), "max", max(sizes), flush=True)
chunk = 220
for bi in range(0, len(good), chunk):
    part = good[bi : bi + chunk]
    zpath = work / "bundles" / f"image-bundle-{bi // chunk:03d}.zip"
    with zipfile.ZipFile(zpath, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=6) as z:
        z.writestr("mapping.json", json.dumps(part, ensure_ascii=False, separators=(",", ":")))
        for row in part:
            z.write(imgdir / row["file"], "images/" + row["file"])

summary = {
    "targets": len(targets),
    "source_products": len(sources),
    "matches": len(matches),
    "images_ready": len(good),
    "bundles": len(list((work / "bundles").glob("*.zip"))),
    "avg_bytes": sum(sizes) // len(sizes),
    "max_bytes": max(sizes),
}
(work / "summary.json").write_text(json.dumps(summary, ensure_ascii=False, indent=2), "utf-8")
print(json.dumps(summary, ensure_ascii=False), flush=True)
