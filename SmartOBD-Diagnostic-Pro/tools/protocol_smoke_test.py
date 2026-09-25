from pathlib import Path

root=Path(__file__).resolve().parents[1]
fw=(root/'firmware/src/main.cpp').read_text(encoding='utf-8')
java=(root/'android/app/src/main/java/ir/khanehremap/smartobdpro/MainActivity.java').read_text(encoding='utf-8')
html=(root/'android/app/src/main/assets/index.html').read_text(encoding='utf-8')
cat=(root/'android/app/src/main/assets/catalog.js').read_text(encoding='utf-8')

uuids=[
'7f640201-7c7d-4f0a-8b6f-4f484f4d4501',
'7f640202-7c7d-4f0a-8b6f-4f484f4d4501',
'7f640203-7c7d-4f0a-8b6f-4f484f4d4501']
for u in uuids:
    assert u in fw, u
    assert u in java, u

for cmd in ['GET_SERIAL','SET_SERIAL:','STATUS','REDETECT','SELECT|','ECU_INFO','READ_DTC','CLEAR_DTC','LIVE_START','LIVE_STOP','ECU_RESET']:
    assert cmd in fw, cmd
    assert cmd in html or cmd in java, cmd

for token in ['پژو 206 فرانسوی','J34','ایران خودرو','سایپا','پارس خودرو','مدیران خودرو (MVM)','کرمان موتور','بهمن موتور','فردا موتور','رنو','هیوندای','کیا','جیلی','لیفان','لکسوس']:
    assert token in cat, token

for token in ['جعبه ابزار ایسیو','تعریف سوییچ و ریموت','تنظیم کیلومتر','اتصال OBD','اسیلسکوپ','تست باتری','تنظیمات','به زودی...']:
    assert token in html, token

assert 'ALARM:LOW_COOLANT' in fw and 'ALARM:LOW_COOLANT' in html and 'ALARM:LOW_COOLANT' in java
assert '0x11,0x01' in fw
assert 'PRG|' not in fw
print('SMART OBD protocol/UI smoke test: PASS')
