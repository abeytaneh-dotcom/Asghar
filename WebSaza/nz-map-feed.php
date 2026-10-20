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
function nzmap_image(array $post): string {
    $images = (array)($post['images'] ?? []);
    if (!empty($images[0])) return '/marketplace-uploads/' . rawurlencode(basename((string)$images[0]));
    $candidate = trim((string)($post['image'] ?? ''));
    if ($candidate !== '' && str_starts_with($candidate, '/')) return $candidate;
    return '';
}
function nzmap_matches_text(array $post, string $q): bool {
    if ($q === '') return true;
    $haystack = implode(' ', [
        (string)($post['title'] ?? ''),
        (string)($post['description'] ?? ''),
        (string)($post['category'] ?? ''),
        (string)($post['subcategory'] ?? ''),
        (string)($post['city'] ?? ''),
        (string)($post['district'] ?? ''),
    ]);
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
    foreach (($db['posts'] ?? []) as $post) {
        if (($post['status'] ?? '') !== 'published') continue;
        if (!isset($post['latitude'],$post['longitude']) || !is_numeric($post['latitude']) || !is_numeric($post['longitude'])) continue;
        $lat=(float)$post['latitude'];$lng=(float)$post['longitude'];
        if ($lat < 24.0 || $lat > 40.0 || $lng < 43.0 || $lng > 64.0) continue;
        if ($city !== '' && $city !== 'همه' && trim((string)($post['city'] ?? '')) !== $city) continue;
        if ($category !== '' && $category !== 'همه' && trim((string)($post['category'] ?? '')) !== $category) continue;
        if (!nzmap_matches_text($post,$q)) continue;

        $mode=(string)($post['location_mode'] ?? 'approx');
        $row=[
            'id'=>(string)($post['id'] ?? ''),
            'title'=>mb_substr((string)($post['title'] ?? 'آگهی'),0,160),
            'price'=>max(0,(int)($post['price'] ?? 0)),
            'city'=>mb_substr((string)($post['city'] ?? ''),0,60),
            'district'=>mb_substr((string)($post['district'] ?? ''),0,80),
            'street'=>$mode==='exact'?mb_substr((string)($post['street'] ?? ''),0,120):'',
            'latitude'=>$lat,
            'longitude'=>$lng,
            'location_mode'=>$mode==='exact'?'exact':'approx',
            'category'=>mb_substr((string)($post['category'] ?? ''),0,80),
            'featured'=>!empty($post['featured']),
            'created_at'=>(string)($post['created_at'] ?? $post['published_at'] ?? ''),
            'image'=>nzmap_image($post),
        ];
        if ($focus !== '' && $row['id'] === $focus) $focusRow = $row;
        if ($lat < $minLat || $lat > $maxLat || $lng < $minLng || $lng > $maxLng) continue;
        $total++;
        if (count($rows) < 600) $rows[]=$row;
    }

    usort($rows, static function(array $a,array $b): int {
        if (($a['featured']??false) !== ($b['featured']??false)) return !empty($a['featured']) ? -1 : 1;
        return strcmp((string)($b['created_at']??''),(string)($a['created_at']??''));
    });
    echo json_encode(['ok'=>true,'total'=>$total,'items'=>$rows,'focus'=>$focusRow,'limited'=>$total>count($rows)], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
