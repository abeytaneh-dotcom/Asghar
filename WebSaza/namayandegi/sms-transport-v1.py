from pathlib import Path

p = Path(__file__).resolve().parent / '_security.php'
text = p.read_text(encoding='utf-8')
old = "CURLOPT_CONNECTTIMEOUT=>6,CURLOPT_TIMEOUT=>12"
new = "CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>20,CURLOPT_IPRESOLVE=>CURL_IPRESOLVE_V4,CURLOPT_HTTP_VERSION=>CURL_HTTP_VERSION_1_1,CURLOPT_USERAGENT=>'WebSaza-SMS/1.0'"
count = text.count(old)
if count < 2:
    raise SystemExit(f'curl transport markers missing: {count}')
text = text.replace(old,new)
text = text.replace("'Content-Type: application/json','Authorization: '.$key", "'Content-Type: application/json','Accept: application/json','Expect:','Authorization: '.$key")
p.write_text(text,encoding='utf-8')
