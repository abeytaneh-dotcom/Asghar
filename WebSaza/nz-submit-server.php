<?php
declare(strict_types=1);
require_once __DIR__ . '/nz-controlled-validation.php';
require_once __DIR__ . '/nz-moderation.php';

function nzss_clean_attributes(mixed $raw): array {
    if (!is_array($raw)) return [];
    $out = [];
    foreach ($raw as $key => $value) {
        if (count($out) >= 30) break;
        $label = mb_substr(trim((string)$key), 0, 80);
        if ($label === '' || is_array($value)) continue;
        $val = mb_substr(trim((string)$value), 0, 300);
        if ($val !== '') $out[$label] = $val;
    }
    return $out;
}
function nzss_location_id(string $type, string $value): string {
    $normalized = nzm_normalize($value);
    return $normalized === '' ? '' : $type.'_'.substr(hash('sha256', $normalized),0,12);
}

function nzss_duplicate_risk(array $db, string $userId, string $title, string $description): ?string {
    $needle = nzm_normalize($title.' '.$description);
    if (mb_strlen($needle) < 20) return null;
    $cutoff = time() - 7 * 86400;
    foreach (array_reverse($db['posts'] ?? []) as $p) {
        if (($p['user_id'] ?? '') !== $userId || ($p['status'] ?? '') === 'deleted') continue;
        if (strtotime((string)($p['created_at'] ?? '1970-01-01')) < $cutoff) continue;
        $hay = nzm_normalize((string)($p['title'] ?? '').' '.(string)($p['description'] ?? ''));
        if ($hay === '') continue;
        similar_text($needle, $hay, $percent);
        if ($percent >= 92) return 'احتمال آگهی تکراری توسط همین کاربر';
    }
    return null;
}

function nzss_handle_create(): never {
    nzc_session_open();
    $db = nzc_db_read();
    $user = nzc_require_user($db);
    if (!nzc_csrf_valid()) throw new RuntimeException('درخواست نامعتبر است. صفحه را تازه‌سازی کنید.');

    $title = trim((string)($_POST['title'] ?? ''));
    $desc = trim((string)($_POST['description'] ?? ''));
    $category = trim((string)($_POST['category'] ?? ''));
    $subcategory = trim((string)($_POST['subcategory'] ?? ''));
    $province = trim((string)($_POST['province'] ?? ''));
    $city = trim((string)($_POST['city'] ?? ''));
    $district = mb_substr(trim((string)($_POST['district'] ?? '')), 0, 100);
    $street = mb_substr(trim((string)($_POST['street'] ?? '')), 0, 180);
    $mode = (string)($_POST['location_mode'] ?? 'exact');
    if (!in_array($mode, ['exact','approx'], true)) $mode = 'exact';

    if (mb_strlen($title) < 5) throw new RuntimeException('عنوان آگهی حداقل ۵ حرف باشد.');
    if (mb_strlen($desc) < 15) throw new RuntimeException('توضیحات آگهی را کامل‌تر بنویسید.');
    if ($category === '' || $subcategory === '') throw new RuntimeException('دسته و زیر‌دسته آگهی را انتخاب کنید.');
    if ($province === '' || $city === '') throw new RuntimeException('استان و شهر آگهی را انتخاب کنید.');

    $latRaw = $_POST['latitude'] ?? null;
    $lngRaw = $_POST['longitude'] ?? null;
    if (!is_numeric($latRaw) || !is_numeric($lngRaw)) throw new RuntimeException('موقعیت آگهی را روی نقشه مشخص کنید.');
    $lat = (float)$latRaw;
    $lng = (float)$lngRaw;
    if ($lat < 24 || $lat > 40.5 || $lng < 43 || $lng > 64) throw new RuntimeException('موقعیت انتخاب‌شده خارج از محدوده قابل قبول است.');
    if ($mode === 'approx') { $lat = round($lat, 3); $lng = round($lng, 3); }

    $attributes = nzss_clean_attributes($_POST['attributes'] ?? []);
    nzcv_validate($category, $subcategory, $attributes);

    $moderation = nzm_scan_submission($title, $desc, $attributes);
    $duplicateReason = nzss_duplicate_risk($db, (string)$user['id'], $title, $desc);
    if ($duplicateReason !== null) {
        $moderation['flagged'] = true;
        $moderation['priority'] = 'high';
        $moderation['score'] = max(85, (int)($moderation['score'] ?? 0));
        $moderation['reasons'][] = $duplicateReason;
        $moderation['reasons'] = array_values(array_unique($moderation['reasons']));
    }

    $images = nzcp_process_images($_FILES['images'] ?? []);
    if (!$images) throw new RuntimeException('حداقل یک عکس برای آگهی انتخاب کنید.');
    [$video, $videoDuration] = nzcp_process_video($_FILES['video'] ?? [], (float)($_POST['video_duration'] ?? 0));

    $provinceId=nzss_location_id('province',$province);
    $cityId=nzss_location_id('city',$province.'|'.$city);
    $districtId=$district!==''?nzss_location_id('district',$province.'|'.$city.'|'.$district):'';
    $post = [
        'id' => nzc_uid('p'),
        'user_id' => $user['id'],
        'title' => $title,
        'description' => $desc,
        'category' => $category,
        'subcategory' => $subcategory,
        'province' => $province,
        'province_id' => $provinceId,
        'city' => $city,
        'city_id' => $cityId,
        'district' => $district,
        'district_id' => $districtId,
        'street' => $street,
        'latitude' => $lat,
        'longitude' => $lng,
        'location_mode' => $mode,
        'location' => ['province'=>$province,'province_id'=>$provinceId,'city'=>$city,'city_id'=>$cityId,'district'=>$district,'district_id'=>$districtId,'street'=>$street,'lat'=>$lat,'lng'=>$lng,'mode'=>$mode],
        'attributes' => $attributes,
        'price' => max(0, (int)($_POST['price'] ?? 0)),
        'images' => $images,
        'video' => $video,
        'video_duration' => $videoDuration,
        'chat_enabled' => isset($_POST['chat_enabled']),
        'phone_visible' => isset($_POST['phone_visible']),
        'status' => 'pending',
        'review_status' => 'pending',
        'review_priority' => !empty($moderation['flagged']) ? 'high' : 'normal',
        'moderation' => $moderation,
        'featured' => false,
        'views' => 0,
        'created_at' => nzc_now(),
        'submitted_at' => nzc_now(),
        'updated_at' => nzc_now(),
    ];

    nzc_db_write(function (&$db) use ($post) {
        $db['posts'][] = $post;
        if (!empty($post['moderation']['flagged'])) nzm_add_system_report($db, $post);
        $suffix = !empty($post['moderation']['flagged']) ? ' این آگهی توسط پایش خودکار برای بررسی فوری مدیر علامت‌گذاری شده است.' : '';
        nzc_support_message($db, $post['user_id'], 'آگهی «'.$post['title'].'» دریافت شد و برای بررسی مدیر ارسال شد. تا زمان تأیید مدیر برای سایر کاربران نمایش داده نمی‌شود.'.$suffix);
        nzc_notify($db, $post['user_id'], 'آگهی «'.$post['title'].'» در انتظار تأیید مدیر است.');
    });

    if (!empty($moderation['flagged'])) {
        nzc_flash('آگهی ثبت شد و به دلیل تشخیص محتوای مشکوک با اولویت بالا برای بررسی مدیر ارسال شد. تا تصمیم مدیر منتشر نمی‌شود.', 'warn');
    } else {
        nzc_flash('آگهی برای بررسی مدیر ارسال شد. نتیجه تأیید یا رد در چت پشتیبانی برای شما فرستاده می‌شود.', 'ok');
    }
    nzc_redirect('/nz?page=myposts');
}
