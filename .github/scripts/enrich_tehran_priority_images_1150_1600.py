from __future__ import annotations
import pathlib
src=pathlib.Path('.github/scripts/enrich_tehran_priority_images.py').read_text('utf-8')
needle="rows=(report.get('rows') or [])[:450]"
replacement="rows=(report.get('rows') or [])[1150:1600]"
if needle not in src: raise SystemExit('priority source slice marker not found')
exec(compile(src.replace(needle,replacement,1),'.github/scripts/enrich_tehran_priority_images_1150_1600.runtime.py','exec'),{'__name__':'__main__'})
