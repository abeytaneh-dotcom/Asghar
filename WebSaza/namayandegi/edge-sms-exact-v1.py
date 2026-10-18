from pathlib import Path

p = Path(__file__).resolve().parent / '_security.php'
text = p.read_text(encoding='utf-8')

old_url = "$url='https://api2.ippanel.com/api/v1/sms/pattern/normal/send';"
new_url = "$url='https://edge.ippanel.com/v1/api/send';"
old_payload = "$payload=['code'=>$pattern,'sender'=>$from,'recipient'=>$m,'variable'=>(object)$params];"
new_payload = "$payload=['sending_type'=>'pattern','from_number'=>$from,'code'=>$pattern,'recipients'=>['+98'.substr($m,1)],'params'=>(object)$params];"
old_header = "CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json','apikey: '.$key]"
new_header = "CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json','Authorization: '.$key]"

for old, new, label in [
    (old_url, new_url, 'endpoint'),
    (old_payload, new_payload, 'payload'),
    (old_header, new_header, 'header'),
]:
    if old not in text:
        raise SystemExit(f'{label} marker missing')
    text = text.replace(old, new, 1)

text = text.replace("sec_sms_log($settingsFile,'pattern_legacy'", "sec_sms_log($settingsFile,'pattern_edge_exact'", 1)
p.write_text(text, encoding='utf-8')
