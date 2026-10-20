<?php
declare(strict_types=1);

require_once __DIR__ . '/nz-community.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

function nzmap_float(string $key, ?float $fallback = null): ?float {
    $raw = $_GET[$key] ?? null;
    if ($raw === null || $raw === '' || !is_numeric($raw)) return $fallback;
    return (float)$raw;
}
function nzmap_text(string $key, int $max = 80): string {
    return mb_substr(trim((string)($_GET[$key] ?? '')), 0, $max);
}
function nzmap_norm_city(string $city): string {
    $city = strtr(trim($city), ['ي'=>'ی','ى'=>'ی','ك'=>'ک','ۀ'=>'ه','ة'=>'ه']);
    return preg_replace('/[\x{200c}\x{200d}\s\-_]+/u', '', $city) ?? $city;
}
function nzmap_city_center(string $city): ?array {
    static $centers = [
        'تهران'=>[35.6892,51.3890], 'مشهد'=>[36.2605,59.6168], 'اصفهان'=>[32.6546,51.6680],
        'کرج'=>[35.8400,50.9391], 'شیراز'=>[29.5918,52.5837], 'تبریز'=>[38.0800,46.2919],
        'اهواز'=>[31.3183,48.6706], 'قم'=>[34.6416,50.8746], 'رشت'=>[37.2808,49.5832],
        'کرمانشاه'=>[34.3142,47.0650], 'ارومیه'=>[37.5527,45.0761], 'کرمان'=>[30.2839,57.0834],
        'یزد'=>[31.8974,54.3569], 'بندرعباس'=>[27.1832,56.2666], 'بندر عباس'=>[27.1832,56.2666],
        'اراک'=>[34.0917,49.6892], 'اردبیل'=>[38.2498,48.2933], 'ایلام'=>[33.6374,46.4227],
        'بجنورد'=>[37.4747,57.3290], 'بیرجند'=>[32.8663,59.2211], 'بوشهر'=>[28.9234,50.8203],
        'شهرکرد'=>[32.3256,50.8644], 'زنجان'=>[36.6769,48.4963], 'سمنان'=>[35.5769,53.3921],
        'سنندج'=>[35.3219,46.9862], 'قزوین'=>[36.2688,50.0041], 'گرگان'=>[36.8427,54.4439],
        'خرمآباد'=>[33.4878,48.3558], 'خرم آباد'=>[33.4878,48.3558], 'ساری'=>[36.5633,53.0601],
        'یاسوج'=>[30.6682,51.5880]
    ];
    $wanted = nzmap_norm_city($city);
    foreach ($centers as $name => $coords) if (nzmap_norm_city($name) === $wanted) return $coords;
    return null;
}
function nzmap_approx_coords(array $post): ?array {
    $center = nzmap_city_center((string)($post['city'] ?? ''));
    if (!$center) return null;
    $key = (string)($post['id'] ?? '') . '|' . (string)($post['title'] ?? '') . '|' . (string)($post['district'] ?? '');
    $seed = (int)hexdec(substr(hash('sha256', $key), 0, 8));
    $angle = (($seed % 360) / 180) * M_PI;
    $radius = 0.003 + (($seed % 1000) / 1000) * 0.009;
    return [$center[0] + sin($angle) * $radius, $center[1] + cos($angle) * $radius * 1.15];
}
function nzmap_demo_posts(): array {
    return [
        ['id'=>'demo_iphone','status'=>'published','demo'=>true,'title'=>'آیفون ۱۵ پرو ۲۵۶ گیگ، تمیز','category'=>'کالای دیجیتال','city'=>'تهران','district'=>'سعادت‌آباد','price'=>72500000,'featured'=>true,'created_at'=>date('c',time()-1200),'demo_image'=>'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=900&q=82'],
        ['id'=>'demo_home','status'=>'published','demo'=>true,'title'=>'آپارتمان ۱۲۵ متر، نورگیر و خوش‌نقشه','category'=>'املاک','city'=>'تهران','district'=>'پونک','price'=>7800000000,'featured'=>true,'created_at'=>date('c',time()-3900),'demo_image'=>'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=900&q=82'],
        ['id'=>'demo_207','status'=>'published','demo'=>true,'title'=>'پژو ۲۰۷ اتومات مدل ۱۴۰۳','category'=>'وسایل نقلیه','city'=>'شیراز','district'=>'معالی‌آباد','price'=>1360000000,'featured'=>false,'created_at'=>date('c',time()-2500),'demo_image'=>'https://images.unsplash.com/photo-1494976388531-d1058494cdd8?auto=format&fit=crop&w=900&q=82'],
        ['id'=>'demo_tv','status'=>'published','demo'=>true,'title'=>'تلویزیون سونی ۶۵ اینچ 4K','category'=>'خانه و آشپزخانه','city'=>'اصفهان','district'=>'مرداویج','price'=>48900000,'featured'=>false,'created_at'=>date('c',time()-5400),'demo_image'=>'https://images.unsplash.com/photo-1593359677879-a4bb92f829d1?auto=format&fit=crop&w=900&q=82'],
        ['id'=>'demo_mac','status'=>'published','demo'=>true,'title'=>'مک‌بوک ایر M2، رم ۸، حافظه ۲۵۶','category'=>'کالای دیجیتال','city'=>'مشهد','district'=>'سجاد','price'=>79000000,'featured'=>false,'created_at'=>date('c',time()-7200),'demo_image'=>'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?auto=format&fit=crop&w=900&q=82'],
        ['id'=>'demo_sofa','status'=>'published','demo'=>true,'title'=>'مبل راحتی ۷ نفره، بسیار تمیز','category'=>'خانه و آشپزخانه','city'=>'شیراز','district'=>'قصرالدشت','price'=>35000000,'featured'=>false,'created_at'=>date('c',time()-9100),'demo_image'=>'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=900&q=82'],
        ['id'=>'demo_service','status'=>'published','demo'=>true,'title'=>'سرویس و تعمیر کولر گازی در محل','category'=>'خدمات','city'=>'تهران','district'=>'غرب تهران','price'=>750000,'featured'=>false,'created_at'=>date('c',time()-12000),'demo_image'=>'https://images.unsplash.com/photo-1621905252507-b35492cc74b4?auto=format&fit=crop&w=900&q=82'],
        ['id'=>'demo_bike','status'=>'published','demo'=>true,'title'=>'موتورسیکلت یاماها، کم‌کار و سالم','category'=>'وسایل نقلیه','city'=>'کرج','district'=>'جهانشهر','price'=>285000000,'featured'=>false,'created_at'=>date('c',time()-15000),'demo_image'=>'https://images.unsplash.com/photo-1558981806-ec527fa84c39?auto=format&fit=crop&w=900&q=82'],
    ];
}
function nzmap_images(array $post): array {
    $result = [];
    if (!empty($post['demo_image']) && is_string($post['demo_image']) && str_starts_with($post['demo_image'], 'https://')) $result[] = $post['demo_image'];
    foreach ((array)($post['images'] ?? []) as $candidate) {
        $name = basename(trim((string)$candidate));
        if ($name === '' || $name === '.' || $name === '..') continue;
        $result[] = '/marketplace-uploads/' . rawurlencode($name);
        if (count($result) >= 3) break;
    }
    if (!$result) {
        $candidate = trim((string)($post['image'] ?? ''));
        if ($candidate !== '' && str_starts_with($candidate, '/')) $result[] = $candidate;
    }
    return array_values(array_unique($result));
}
function nzmap_matches_text(array $post, string $q): bool {
    if ($q === '') return true;
    $haystack = implode(' ', [(string)($post['title'] ?? ''),(string)($post['description'] ?? ''),(string)($post['category'] ?? ''),(string)($post['subcategory'] ?? ''),(string)($post['city'] ?? ''),(string)($post['district'] ?? '')]);
    return mb_stripos($haystack, $q) !== false;
}

try {
    $minLat = nzmap_float('minLat', 24.0);$maxLat = nzmap_float('maxLat', 40.0);
    $minLng = nzmap_float('minLng', 43.0);$maxLng = nzmap_float('maxLng', 64.0);
    if ($minLat === null || $maxLat === null || $minLng === null || $maxLng === null) throw new RuntimeException('محدوده نقشه معتبر نیست.');
    $minLat = max(24.0, min(40.0, $minLat));$maxLat = max(24.0, min(40.0, $maxLat));
    $minLng = max(43.0, min(64.0, $minLng));$maxLng = max(43.0, min(64.0, $maxLng));
    if ($minLat > $maxLat) [$minLat,$maxLat]=[$maxLat,$minLat];
    if ($minLng > $maxLng) [$minLng,$maxLng]=[$maxLng,$minLng];

    $city = nzmap_text('city', 60);$category = nzmap_text('category', 80);$q = nzmap_text('q', 120);$focus = nzmap_text('id', 120);
    $db = nzc_db_read();$rows = [];$total = 0;$focusRow = null;
    $posts = array_merge(nzmap_demo_posts(), (array)($db['posts'] ?? []));
    foreach ($posts as $post) {
        if (($post['status'] ?? '') !== 'published') continue;
        if ($city !== '' && $city !== 'همه' && nzmap_norm_city((string)($post['city'] ?? '')) !== nzmap_norm_city($city)) continue;
        if ($category !== '' && $category !== 'همه' && trim((string)($post['category'] ?? '')) !== $category) continue;
        if (!nzmap_matches_text($post,$q)) continue;

        $synthetic = false;
        if (isset($post['latitude'],$post['longitude']) && is_numeric($post['latitude']) && is_numeric($post['longitude'])) {
            $lat=(float)$post['latitude'];$lng=(float)$post['longitude'];
            if ($lat < 24.0 || $lat > 40.0 || $lng < 43.0 || $lng > 64.0) {
                $fallback = nzmap_approx_coords($post);if (!$fallback) continue;[$lat,$lng] = $fallback;$synthetic = true;
            }
        } else {
            $fallback = nzmap_approx_coords($post);if (!$fallback) continue;[$lat,$lng] = $fallback;$synthetic = true;
        }

        $mode=$synthetic ? 'approx' : (string)($post['location_mode'] ?? 'approx');$images=nzmap_images($post);
        $row=[
            'id'=>(string)($post['id'] ?? ''),'title'=>mb_substr((string)($post['title'] ?? 'آگهی'),0,160),'price'=>max(0,(int)($post['price'] ?? 0)),
            'city'=>mb_substr((string)($post['city'] ?? ''),0,60),'district'=>mb_substr((string)($post['district'] ?? ''),0,80),
            'street'=>$mode==='exact'?mb_substr((string)($post['street'] ?? ''),0,120):'','latitude'=>$lat,'longitude'=>$lng,
            'location_mode'=>$mode==='exact'?'exact':'approx','location_source'=>$synthetic?'city_approx':'post','category'=>mb_substr((string)($post['category'] ?? ''),0,80),
            'featured'=>!empty($post['featured']),'created_at'=>(string)($post['created_at'] ?? $post['published_at'] ?? ''),'image'=>(string)($images[0] ?? ''),'images'=>$images,
        ];
        if ($focus !== '' && $row['id'] === $focus) $focusRow = $row;
        if ($lat < $minLat || $lat > $maxLat || $lng < $minLng || $lng > $maxLng) continue;
        $total++;if (count($rows) < 600) $rows[]=$row;
    }

    usort($rows, static function(array $a,array $b): int {if (($a['featured']??false) !== ($b['featured']??false)) return !empty($a['featured']) ? -1 : 1;return strcmp((string)($b['created_at']??''),(string)($a['created_at']??''));});
    echo json_encode(['ok'=>true,'total'=>$total,'items'=>$rows,'focus'=>$focusRow,'limited'=>$total>count($rows)], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    http_response_code(400);echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
