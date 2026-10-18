from pathlib import Path
import os
import subprocess
import sys

root = Path(__file__).resolve().parent
steps = [
    'build-patch.py',
    'security-v2.py',
    'iranpayamak-api-v1.py',
    'pin-remember-v1.py',
    'customer-retention-v1.py',
    'customer-lifecycle-v1.py',
    'core-upgrade-v3.py',
    'smart-upgrade-v3.py',
    'finance-upgrade-v3.py',
    'admin-upgrade-v3.py',
    'lifecycle-sms-v3.py',
    'security-upgrade-v3.py',
    'single-engine-v3.py',
    'transaction-upgrade-v4.py',
    'queue-upgrade-v4.py',
    'hardening-v5.py',
]
for step in steps:
    path = root / step
    if not path.is_file():
        raise SystemExit(f'missing build step: {step}')
    subprocess.run([sys.executable, str(path)], cwd=str(root), env=os.environ.copy(), check=True)

# Bundle static assets exactly once after all source patches.
style = root / 'style.css'
with style.open('a', encoding='utf-8') as out:
    out.write('\n')
    for name in ['ux.css','simple-v2.css','polish-v6.css','market-v8.css','remember-fix-v1.css']:
        out.write((root/name).read_text(encoding='utf-8'))
        out.write('\n')
app = root / 'app.js'
with app.open('a', encoding='utf-8') as out:
    out.write('\n')
    for name in ['simple-v2.js','polish-v6.js','market-v8.js','performance-v1.js','seen-v3.js']:
        out.write((root/name).read_text(encoding='utf-8'))
        out.write('\n')
print('WEBSAZA_BUILD_V5_OK')
