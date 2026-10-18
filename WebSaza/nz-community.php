<?php
declare(strict_types=1);

const NZC_SUPPORT_POST = '__support__';
const NZC_SUPPORT_SENDER = 'support';

function nzc_data_file(): string { return __DIR__ . '/.marketplace-data/data.json'; }
function nzc_now(): string { return date('c'); }
function nzc_uid(string $prefix): string { return $prefix . '_' . bin2hex(random_bytes(6)); }
function nzc_e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function nzc_default_db(): array { return ['users'=>[], 'posts'=>[], 'favorites'=>[], 'conversations'=>[], 'messages'=>[], 'reports'=>[], 'saved_searches'=>[], 'notifications'=>[], 'payments'=>[]]; }

function nzc_db_read(): array {
    $file = nzc_data_file();
    if (!is_file($file)) return nzc_default_db();
    $raw = @file_get_contents($file);
    $db = json_decode($raw ?: '', true);
    return is_array($db) ? array_replace(nzc_default_db(), $db) : nzc_default_db();
}

function nzc_db_write(callable $mutator): mixed {
    $file = nzc_data_file();
    $dir = dirname($file);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $fh = @fopen($file, 'c+');
    if (!$fh) throw new RuntimeException('امکان دسترسی به اطلاعات نیازستان وجود ندارد.');
    flock($fh, LOCK_EX);
    rewind($fh);
    $db = json_decode(stream_get_contents($fh) ?: '', true);
    if (!is_array($db)) $db = nzc_default_db();
    $db = array_replace(nzc_default_db(), $db);
    $result = $mutator($db);
    rewind($fh);
    ftruncate($fh, 0);
    fwrite($fh, json_encode($db, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    return $result;
}

function nzc_session_open(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
}
function nzc_session_close(): void {
    if (session_status() === PHP_SESSION_ACTIVE) @session_write_close();
}
function nzc_flash(string $message, string $type='ok'): void {
    nzc_session_open();
    $_SESSION['flash'] = [$message, $type];
}
function nzc_redirect(string $url): never {
    header('Location: ' . $url, true, 302);
    exit;
}
function nzc_csrf_valid(): bool {
    return isset($_SESSION['csrf'], $_POST['csrf']) && is_string($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
}
function nzc_current_user(array $db): ?array {
    $id = $_SESSION['uid'] ?? null;
    if (!$id) return null;
    foreach ($db['users'] as $u) if (($u['id'] ?? '') === $id) return $u;
    return null;
}
function nzc_is_admin(?array $user): bool {
    if (!$user) return false;
    if (in_array(($user['role'] ?? ''), ['admin','superadmin'], true) || !empty($user['is_admin'])) return true;
    $phone = (string)($user['phone'] ?? '');
    foreach (['NIAZESTAN_ADMIN_PHONE','WEBSAZA_ADMIN_PHONE'] as $key) {
        $configured = trim((string)getenv($key));
        if ($configured !== '' && $phone !== '' && preg_replace('/\D+/', '', $configured) === preg_replace('/\D+/', '', $phone)) return true;
    }
    return false;
}
function nzc_require_user(array $db): array {
    $user = nzc_current_user($db);
    if (!$user) { nzc_flash('برای ادامه وارد حساب شوید.', 'warn'); nzc_redirect('/nz?page=login'); }
    return $user;
}
function nzc_require_admin(array $db): array {
    $user = nzc_require_user($db);
    if (!nzc_is_admin($user)) { nzc_flash('دسترسی مدیریت برای این حساب فعال نیست.', 'error'); nzc_redirect('/nz?page=profile'); }
    return $user;
}

function nzc_support_conversation(array &$db, string $userId): string {
    foreach ($db['conversations'] as &$c) {
        if (($c['type'] ?? '') === 'support' && in_array($userId, $c['members'] ?? [], true)) return (string)$c['id'];
        if (($c['post_id'] ?? '') === NZC_SUPPORT_POST && in_array($userId, $c['members'] ?? [], true)) {
            $c['type'] = 'support';
            return (string)$c['id'];
        }
    }
    unset($c);
    $id = nzc_uid('c');
    $db['conversations'][] = ['id'=>$id,'type'=>'support','post_id'=>NZC_SUPPORT_POST,'members'=>[$userId],'created_at'=>nzc_now(),'updated_at'=>nzc_now()];
    return $id;
}

function nzc_support_message(array &$db, string $userId, string $text): string {
    $cid = nzc_support_conversation($db, $userId);
    $db['messages'][] = ['id'=>nzc_uid('m'),'conversation_id'=>$cid,'sender_id'=>NZC_SUPPORT_SENDER,'sender_name'=>'پشتیبانی','text'=>mb_substr($text,0,1600),'created_at'=>nzc_now()];
    foreach ($db['conversations'] as &$c) if (($c['id'] ?? '') === $cid) $c['updated_at'] = nzc_now();
    unset($c);
    return $cid;
}

function nzc_notify(array &$db, string $userId, string $text): void {
    $db['notifications'][] = ['id'=>nzc_uid('n'),'user_id'=>$userId,'text'=>$text,'read'=>false,'created_at'=>nzc_now()];
}

function nzc_post_title(array $db, string $postId): string {
    foreach ($db['posts'] as $p) if (($p['id'] ?? '') === $postId) return (string)($p['title'] ?? 'آگهی');
    return str_starts_with($postId, 'demo_') ? 'آگهی نمایشی نیازستان' : 'آگهی';
}

function nzc_prepare_pending_create(): void {
    $userId = (string)($_SESSION['uid'] ?? '');
    if ($userId === '') return;
    $before = [];
    foreach (nzc_db_read()['posts'] as $p) if (!empty($p['id'])) $before[(string)$p['id']] = true;

    register_shutdown_function(function() use ($before, $userId): void {
        try {
            $changed = [];
            nzc_db_write(function(&$db) use ($before, $userId, &$changed) {
                foreach ($db['posts'] as &$p) {
                    $id = (string)($p['id'] ?? '');
                    if ($id === '' || isset($before[$id]) || ($p['user_id'] ?? '') !== $userId || ($p['status'] ?? '') === 'deleted') continue;
                    $p['status'] = 'pending';
                    $p['review_status'] = 'pending';
                    $p['submitted_at'] = nzc_now();
                    $p['updated_at'] = nzc_now();
                    $changed[] = ['id'=>$id,'title'=>(string)($p['title'] ?? 'آگهی')];
                }
                unset($p);
                if (!$changed) return;
                $changedTitles = array_column($changed, 'title');
                $db['notifications'] = array_values(array_filter($db['notifications'], function($n) use ($userId, $changedTitles) {
                    if (($n['user_id'] ?? '') !== $userId) return true;
                    $text = (string)($n['text'] ?? '');
                    foreach ($changedTitles as $title) if (str_contains($text, 'آگهی «'.$title.'» منتشر شد')) return false;
                    return true;
                }));
                foreach ($changed as $row) {
                    $msg = 'آگهی «'.$row['title'].'» دریافت شد و برای بررسی مدیر ارسال شد. تا زمان تأیید مدیر برای سایر کاربران نمایش داده نمی‌شود.';
                    nzc_support_message($db, $userId, $msg);
                    nzc_notify($db, $userId, 'آگهی «'.$row['title'].'» در انتظار تأیید مدیر است.');
                }
            });
            if ($changed) {
                nzc_session_open();
                $_SESSION['flash'] = ['آگهی برای بررسی مدیر ارسال شد. نتیجه تأیید یا رد در چت پشتیبانی برای شما فرستاده می‌شود.', 'ok'];
                header('Location: /nz?page=myposts', true, 302);
            }
        } catch (Throwable $ignored) { }
    });
}

function nzc_handle_special_post(string $action): never {
    $db = nzc_db_read();
    $user = nzc_require_user($db);
    if (!nzc_csrf_valid()) { nzc_flash('درخواست نامعتبر است. صفحه را تازه‌سازی کنید.', 'error'); nzc_redirect('/nz'); }

    if ($action === 'report_post') {
        $postId = trim((string)($_POST['post_id'] ?? ''));
        if ($postId === '') { nzc_flash('آگهی برای گزارش مشخص نیست.', 'error'); nzc_redirect('/nz'); }
        $reason = trim((string)($_POST['reason'] ?? 'سایر'));
        $allowed = ['کلاهبرداری یا سوءاستفاده','محتوای نامناسب','کالای ممنوع یا غیرقانونی','آگهی تکراری یا گمراه‌کننده','اطلاعات تماس مشکوک','سایر'];
        if (!in_array($reason, $allowed, true)) $reason = 'سایر';
        $details = mb_substr(trim((string)($_POST['details'] ?? '')), 0, 1000);
        $title = nzc_post_title($db, $postId);
        nzc_db_write(function(&$db) use ($postId,$reason,$details,$title,$user) {
            foreach ($db['reports'] as $r) if (($r['post_id'] ?? '') === $postId && ($r['reporter_id'] ?? '') === $user['id'] && ($r['status'] ?? 'open') === 'open') throw new RuntimeException('گزارش شما برای این آگهی قبلاً ثبت شده و در حال بررسی است.');
            $rid = nzc_uid('r');
            $db['reports'][] = ['id'=>$rid,'post_id'=>$postId,'post_title'=>$title,'reporter_id'=>$user['id'],'reason'=>$reason,'details'=>$details,'status'=>'open','created_at'=>nzc_now()];
            nzc_support_message($db, $user['id'], 'گزارش شما برای «'.$title.'» ثبت شد. پشتیبانی آن را بررسی می‌کند. شماره گزارش: '.$rid);
        });
        nzc_flash('گزارش با موفقیت برای پشتیبانی ارسال شد. ممنون که به امنیت نیازستان کمک می‌کنید.');
        nzc_redirect('/nz?id='.rawurlencode($postId));
    }

    if ($action === 'start_support_chat') {
        $cid = nzc_db_write(function(&$db) use ($user) {
            $cid = nzc_support_conversation($db, $user['id']);
            $hasMessage = false;
            foreach ($db['messages'] as $m) if (($m['conversation_id'] ?? '') === $cid) { $hasMessage = true; break; }
            if (!$hasMessage) nzc_support_message($db, $user['id'], 'سلام، من پشتیبانی نیازستان هستم. پیام‌تان را همین‌جا بنویسید تا بررسی شود.');
            return $cid;
        });
        nzc_redirect('/nz?page=chat&cid='.rawurlencode((string)$cid));
    }

    if ($action === 'support_send') {
        $cid = trim((string)($_POST['conversation_id'] ?? ''));
        $text = mb_substr(trim((string)($_POST['text'] ?? '')), 0, 1600);
        if ($text === '') { nzc_flash('پیام خالی است.', 'warn'); nzc_redirect('/nz?page=chat&cid='.rawurlencode($cid)); }
        $isAdmin = nzc_is_admin($user);
        nzc_db_write(function(&$db) use ($cid,$text,$user,$isAdmin) {
            $ok = false;
            foreach ($db['conversations'] as &$c) {
                if (($c['id'] ?? '') !== $cid || (($c['type'] ?? '') !== 'support' && ($c['post_id'] ?? '') !== NZC_SUPPORT_POST)) continue;
                if ($isAdmin || in_array($user['id'], $c['members'] ?? [], true)) { $ok = true; $c['type']='support'; $c['updated_at']=nzc_now(); break; }
            }
            unset($c);
            if (!$ok) throw new RuntimeException('دسترسی به این گفتگوی پشتیبانی ندارید.');
            $db['messages'][] = ['id'=>nzc_uid('m'),'conversation_id'=>$cid,'sender_id'=>$isAdmin?NZC_SUPPORT_SENDER:$user['id'],'sender_name'=>$isAdmin?'پشتیبانی':($user['name']??'کاربر'),'text'=>$text,'created_at'=>nzc_now()];
        });
        nzc_redirect('/nz?page=chat&cid='.rawurlencode($cid));
    }

    $admin = nzc_require_admin($db);
    if ($action === 'approve_post' || $action === 'reject_post') {
        $postId = trim((string)($_POST['post_id'] ?? ''));
        $reason = mb_substr(trim((string)($_POST['reason'] ?? '')), 0, 600);
        $approved = $action === 'approve_post';
        nzc_db_write(function(&$db) use ($postId,$reason,$approved,$admin) {
            $found = false;
            foreach ($db['posts'] as &$p) {
                if (($p['id'] ?? '') !== $postId) continue;
                $found = true;
                if (($p['status'] ?? '') === 'deleted') throw new RuntimeException('آگهی حذف شده است.');
                $p['status'] = $approved ? 'published' : 'rejected';
                $p['review_status'] = $approved ? 'approved' : 'rejected';
                $p['reviewed_at'] = nzc_now();
                $p['reviewed_by'] = $admin['id'];
                $p['rejection_reason'] = $approved ? '' : ($reason !== '' ? $reason : 'مطابق قوانین انتشار نیازستان نیست.');
                $p['updated_at'] = nzc_now();
                $owner = (string)($p['user_id'] ?? '');
                $title = (string)($p['title'] ?? 'آگهی');
                if ($owner !== '') {
                    if ($approved) {
                        $text = 'آگهی «'.$title.'» تأیید شد و اکنون در نیازستان منتشر است.';
                    } else {
                        $text = 'آگهی «'.$title.'» تأیید نشد. دلیل: '.$p['rejection_reason'];
                    }
                    nzc_support_message($db, $owner, $text);
                    nzc_notify($db, $owner, $text);
                }
                break;
            }
            unset($p);
            if (!$found) throw new RuntimeException('آگهی پیدا نشد.');
        });
        nzc_flash($approved ? 'آگهی تأیید و منتشر شد.' : 'آگهی رد شد و نتیجه برای کاربر ارسال شد.');
        nzc_redirect('/nz?page=admin');
    }

    if ($action === 'resolve_report') {
        $reportId = trim((string)($_POST['report_id'] ?? ''));
        nzc_db_write(function(&$db) use ($reportId,$admin) {
            $found=false;
            foreach ($db['reports'] as &$r) if (($r['id'] ?? '') === $reportId) { $r['status']='resolved'; $r['resolved_at']=nzc_now(); $r['resolved_by']=$admin['id']; $found=true; break; }
            unset($r);
            if (!$found) throw new RuntimeException('گزارش پیدا نشد.');
        });
        nzc_flash('گزارش به‌عنوان بررسی‌شده ثبت شد.');
        nzc_redirect('/nz?page=admin#reports');
    }

    nzc_redirect('/nz');
}

function nzc_prepare_request(): void {
    static $prepared = false;
    if ($prepared) return;
    $prepared = true;
    nzc_session_open();
    $action = (string)($_POST['action'] ?? '');
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && in_array($action, ['report_post','start_support_chat','support_send','approve_post','reject_post','resolve_report'], true)) {
        nzc_handle_special_post($action);
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $action === 'create_post') nzc_prepare_pending_create();
    nzc_session_close();
}

function nzc_status_fa(string $status): string {
    return match($status) { 'pending'=>'در انتظار تأیید', 'published'=>'منتشر شده', 'rejected'=>'رد شده', 'deleted'=>'حذف شده', default=>$status };
}

function nzc_support_chat_html(array $db, array $user, string $cid): ?string {
    $conv = null;
    foreach ($db['conversations'] as $c) if (($c['id'] ?? '') === $cid && (($c['type'] ?? '') === 'support' || ($c['post_id'] ?? '') === NZC_SUPPORT_POST)) { $conv=$c; break; }
    if (!$conv) return null;
    $isAdmin = nzc_is_admin($user);
    if (!$isAdmin && !in_array($user['id'], $conv['members'] ?? [], true)) return '<main class="page page-narrow"><div class="surface"><h1>گفتگو پیدا نشد</h1></div></main>';
    $ownerId = (string)(($conv['members'] ?? [])[0] ?? '');
    $owner = null;
    foreach ($db['users'] as $u) if (($u['id'] ?? '') === $ownerId) { $owner=$u; break; }
    $messages = array_values(array_filter($db['messages'], fn($m)=>($m['conversation_id'] ?? '') === $cid));
    usort($messages, fn($a,$b)=>strtotime($a['created_at']??'now') <=> strtotime($b['created_at']??'now'));
    $html = '<main class="chat-page nzc-support-chat"><div class="nzc-chat-head"><div><b>پشتیبانی نیازستان</b><small>'.($isAdmin && $owner ? nzc_e(($owner['name']??'کاربر').' · '.($owner['phone']??'')) : 'گفتگوی مستقیم با پشتیبانی').'</small></div>'.($isAdmin?'<a class="btn ghost" href="/nz?page=admin">مدیریت</a>':'').'</div><div class="messages" id="messages">';
    foreach ($messages as $m) {
        $sender = (string)($m['sender_id'] ?? '');
        $support = $sender === NZC_SUPPORT_SENDER;
        $mine = (!$isAdmin && $sender === $user['id']) || ($isAdmin && $support);
        $name = $support ? 'پشتیبانی' : 'کاربر';
        $html .= '<div class="msg '.($mine?'me':'').' '.($support?'nzc-support-msg':'').'"><b class="nzc-sender">'.nzc_e($name).'</b>'.nl2br(nzc_e($m['text']??'')).'<div class="subtle">'.nzc_e(date('H:i', strtotime($m['created_at']??'now'))).'</div></div>';
    }
    $html .= '</div><form method="post" class="compose"><input type="hidden" name="csrf" value="'.nzc_e($_SESSION['csrf']??'').'"><input type="hidden" name="action" value="support_send"><input type="hidden" name="conversation_id" value="'.nzc_e($cid).'"><input name="text" maxlength="1600" required placeholder="پیام برای پشتیبانی..."><button class="btn primary">ارسال</button></form></main><script>const nzcM=document.getElementById("messages");if(nzcM)nzcM.scrollTop=nzcM.scrollHeight</script>';
    return $html;
}

function nzc_admin_html(array $db, array $admin): string {
    $pending = array_values(array_filter($db['posts'], fn($p)=>($p['status']??'')==='pending'));
    usort($pending, fn($a,$b)=>strtotime($b['submitted_at']??$b['created_at']??'now') <=> strtotime($a['submitted_at']??$a['created_at']??'now'));
    $reports = array_values(array_filter($db['reports'], fn($r)=>($r['status']??'open')==='open'));
    usort($reports, fn($a,$b)=>strtotime($b['created_at']??'now') <=> strtotime($a['created_at']??'now'));
    $supports = array_values(array_filter($db['conversations'], fn($c)=>(($c['type']??'')==='support'||($c['post_id']??'')===NZC_SUPPORT_POST)));
    usort($supports, fn($a,$b)=>strtotime($b['updated_at']??'now') <=> strtotime($a['updated_at']??'now'));
    $csrf = nzc_e($_SESSION['csrf']??'');
    $html = '<main class="page nzc-admin"><div class="section-head"><div><h1>مدیریت نیازستان</h1><p>تأیید آگهی‌ها، گزارش‌های کاربران و پشتیبانی</p></div></div><div class="nzc-stats"><div><b>'.count($pending).'</b><span>در انتظار تأیید</span></div><div><b>'.count($reports).'</b><span>گزارش باز</span></div><div><b>'.count($supports).'</b><span>گفتگوی پشتیبانی</span></div></div><section class="surface"><h2>آگهی‌های در انتظار تأیید</h2>';
    if (!$pending) $html .= '<div class="empty">آگهی در انتظار بررسی وجود ندارد.</div>';
    foreach ($pending as $p) {
        $owner=''; foreach($db['users'] as $u) if(($u['id']??'')===($p['user_id']??'')) {$owner=(string)($u['phone']??$u['name']??'');break;}
        $html .= '<article class="nzc-review"><img src="'.nzc_e(function_exists('post_image')?post_image($p):'').'" alt=""><div class="nzc-review-body"><h3>'.nzc_e($p['title']??'آگهی').'</h3><small>'.nzc_e(($p['city']??'').' · '.$owner).'</small><p>'.nzc_e(mb_substr((string)($p['description']??''),0,260)).'</p><div class="nzc-admin-actions"><form method="post"><input type="hidden" name="csrf" value="'.$csrf.'"><input type="hidden" name="action" value="approve_post"><input type="hidden" name="post_id" value="'.nzc_e($p['id']??'').'"><button class="btn primary">تأیید و انتشار</button></form><form method="post" class="nzc-reject"><input type="hidden" name="csrf" value="'.$csrf.'"><input type="hidden" name="action" value="reject_post"><input type="hidden" name="post_id" value="'.nzc_e($p['id']??'').'"><input name="reason" maxlength="600" placeholder="دلیل رد آگهی"><button class="btn danger">رد آگهی</button></form></div></div></article>';
    }
    $html .= '</section><section class="surface" id="reports"><h2>گزارش‌های کاربران</h2>';
    if (!$reports) $html .= '<div class="empty">گزارش باز وجود ندارد.</div>';
    foreach ($reports as $r) {
        $reporter='کاربر'; foreach($db['users'] as $u) if(($u['id']??'')===($r['reporter_id']??'')) {$reporter=(string)($u['phone']??$u['name']??'کاربر');break;}
        $html .= '<article class="nzc-report-card"><b>'.nzc_e($r['post_title']??nzc_post_title($db,(string)($r['post_id']??''))).'</b><span>'.nzc_e($r['reason']??'سایر').' · گزارش‌دهنده: '.nzc_e($reporter).'</span>'.(!empty($r['details'])?'<p>'.nzc_e($r['details']).'</p>':'').'<div><a class="btn ghost" href="/nz?id='.rawurlencode((string)($r['post_id']??'')).'">مشاهده آگهی</a><form method="post" style="display:inline"><input type="hidden" name="csrf" value="'.$csrf.'"><input type="hidden" name="action" value="resolve_report"><input type="hidden" name="report_id" value="'.nzc_e($r['id']??'').'"><button class="btn">بررسی شد</button></form></div></article>';
    }
    $html .= '</section><section class="surface"><h2>چت‌های پشتیبانی</h2>';
    if (!$supports) $html .= '<div class="empty">گفتگوی پشتیبانی وجود ندارد.</div>';
    foreach ($supports as $c) {
        $ownerId=(string)(($c['members']??[])[0]??'');$label='کاربر';foreach($db['users'] as $u)if(($u['id']??'')===$ownerId){$label=(string)(($u['name']??'کاربر').' · '.($u['phone']??''));break;}
        $html .= '<a class="menu-item" style="margin-bottom:8px" href="/nz?page=chat&cid='.rawurlencode((string)($c['id']??'')).'"><span><b>'.nzc_e($label).'</b><small class="subtle" style="display:block;margin-top:4px">آخرین بروزرسانی '.nzc_e(date('Y/m/d H:i',strtotime($c['updated_at']??'now'))).'</small></span><span>‹</span></a>';
    }
    return $html.'</section></main>';
}

function nzc_support_landing_html(array $db, ?array $user): string {
    if (!$user) return '<main class="page page-narrow"><div class="surface"><h1>پشتیبانی نیازستان</h1><p class="lead">برای گفتگو با پشتیبانی وارد حساب شوید.</p><a class="btn primary" style="width:100%" href="/nz?page=login">ورود / ثبت‌نام</a></div></main>';
    $cid=''; foreach($db['conversations'] as $c) if((($c['type']??'')==='support'||($c['post_id']??'')===NZC_SUPPORT_POST)&&in_array($user['id'],$c['members']??[],true)){$cid=(string)$c['id'];break;}
    if ($cid !== '') return '<main class="page page-narrow"><div class="surface"><h1>پشتیبانی نیازستان</h1><p class="lead">برای پیگیری آگهی، گزارش تخلف یا هر سؤال دیگری مستقیماً با پشتیبانی گفتگو کنید.</p><a class="btn primary" style="width:100%" href="/nz?page=chat&cid='.rawurlencode($cid).'">باز کردن گفتگوی پشتیبانی</a></div></main>';
    return '<main class="page page-narrow"><div class="surface"><h1>پشتیبانی نیازستان</h1><p class="lead">برای پیگیری آگهی، گزارش تخلف یا هر سؤال دیگری مستقیماً با پشتیبانی گفتگو کنید.</p><form method="post"><input type="hidden" name="csrf" value="'.nzc_e($_SESSION['csrf']??'').'"><input type="hidden" name="action" value="start_support_chat"><button class="btn primary" style="width:100%">شروع گفتگو با پشتیبانی</button></form></div></main>';
}

function nzc_replace_main(string $html, string $replacement): string {
    $patterns = [
        '/<main class="main">.*?<\/main>/su',
        '/<main class="chat-page">.*?<\/main>(?:<script>.*?<\/script>)?/su',
        '/<div class="empty">گفتگو پیدا نشد\.<\/div>/su'
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $html)) return preg_replace($pattern, $replacement, $html, 1) ?? $html;
    }
    return str_replace('<nav class="mobile-nav">', $replacement.'<nav class="mobile-nav">', $html);
}

function nzc_transform(string $html): string {
    nzc_session_open();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    $db = nzc_db_read();
    $user = nzc_current_user($db);
    $page = (string)($_GET['page'] ?? 'home');

    if ($page === 'admin') {
        $replacement = nzc_is_admin($user) ? nzc_admin_html($db, $user) : '<main class="page page-narrow"><div class="surface"><h1>مدیریت</h1><p class="lead">این بخش فقط برای مدیر سیستم قابل دسترسی است.</p><a class="btn" href="/nz?page=profile">بازگشت</a></div></main>';
        $html = nzc_replace_main($html, $replacement);
    } elseif ($page === 'support') {
        $html = nzc_replace_main($html, nzc_support_landing_html($db, $user));
    } elseif ($page === 'chat' && !empty($_GET['cid']) && $user) {
        $custom = nzc_support_chat_html($db, $user, (string)$_GET['cid']);
        if ($custom !== null) $html = nzc_replace_main($html, $custom);
    }

    $html = str_replace('چند اطلاعات ساده وارد کن؛ انتشار آگهی رایگان است.', 'چند اطلاعات ساده وارد کن؛ آگهی پس از بررسی و تأیید مدیر منتشر می‌شود.', $html);
    $html = str_replace('>انتشار رایگان آگهی</button>', '>ارسال آگهی برای بررسی</button>', $html);

    if ($page === 'profile' && $user) {
        $extra = '<a class="menu-item" href="/nz?page=support"><span>پشتیبانی نیازستان</span><span>‹</span></a>';
        if (nzc_is_admin($user)) $extra .= '<a class="menu-item" href="/nz?page=admin"><span>مدیریت نیازستان</span><span>‹</span></a>';
        $html = str_replace('</div><form method="post" style="margin-top:18px">', $extra.'</div><form method="post" style="margin-top:18px">', $html);
    }

    if ($page === 'chats' && $user) {
        $supportLink = '<a class="menu-item nzc-support-link" style="margin-bottom:12px" href="/nz?page=support"><span><b>پشتیبانی نیازستان</b><small class="subtle" style="display:block;margin-top:4px">گفتگوی آزاد با پشتیبانی و پیگیری وضعیت آگهی‌ها</small></span><span>‹</span></a>';
        $html = str_replace('<main class="page page-narrow"><div class="section-head">', '<main class="page page-narrow">'.$supportLink.'<div class="section-head">', $html);
    }

    if ($page === 'myposts' && $user) {
        $rows='';
        foreach ($db['posts'] as $p) if (($p['user_id']??'')===$user['id'] && ($p['status']??'')!=='deleted') {
            $status=(string)($p['status']??'pending');
            $rows .= '<div class="nzc-status-row"><span>'.nzc_e($p['title']??'آگهی').'</span><b class="nzc-status nzc-'.$status.'">'.nzc_e(nzc_status_fa($status)).'</b></div>';
            if ($status !== 'published' && !empty($p['id'])) $html = str_replace('/nz?id='.nzc_e($p['id']), '/nz?page=myposts', $html);
        }
        if ($rows !== '') $html = str_replace('<main class="page"><div class="section-head">', '<main class="page"><div class="surface nzc-status-box"><h3>وضعیت انتشار</h3>'.$rows.'</div><div class="section-head">', $html);
    }

    $postId = trim((string)($_GET['id'] ?? ''));
    if ($postId !== '' && str_contains($html, '<main class="detail">')) {
        if ($user) {
            $report = '<details class="nzc-report-box"><summary>⚑ گزارش این آگهی</summary><form method="post"><input type="hidden" name="csrf" value="'.nzc_e($_SESSION['csrf']).'"><input type="hidden" name="action" value="report_post"><input type="hidden" name="post_id" value="'.nzc_e($postId).'"><label>دلیل گزارش</label><select name="reason" required><option>کلاهبرداری یا سوءاستفاده</option><option>محتوای نامناسب</option><option>کالای ممنوع یا غیرقانونی</option><option>آگهی تکراری یا گمراه‌کننده</option><option>اطلاعات تماس مشکوک</option><option>سایر</option></select><label>توضیحات تکمیلی</label><textarea name="details" maxlength="1000" placeholder="اگر نکته‌ای برای بررسی پشتیبانی دارید بنویسید"></textarea><button class="btn danger" type="submit">ارسال گزارش به پشتیبانی</button></form></details>';
        } else {
            $report = '<div class="nzc-report-box"><b>⚑ گزارش آگهی</b><p>برای گزارش سوءاستفاده یا تخلف وارد حساب شوید.</p><a class="btn ghost" href="/nz?page=login">ورود برای ثبت گزارش</a></div>';
        }
        $html = preg_replace('/(<div class="surface"><h3 style="margin-top:0">توضیحات<\/h3>.*?<\/div>)/su', '$1'.$report, $html, 1) ?? $html;
    }

    $css = <<<'HTML'
<style id="nz-community-style">
.nzc-report-box{margin-top:14px;border:1px solid #f0d9db;background:#fffafa;border-radius:16px;padding:14px}.nzc-report-box summary{font-weight:900;color:#a31620;cursor:pointer}.nzc-report-box form{display:grid;gap:9px;margin-top:14px}.nzc-report-box label{font-size:12px;font-weight:800;color:#555}.nzc-report-box select,.nzc-report-box textarea,.nzc-reject input{width:100%;border:1px solid #dfe2e6;border-radius:11px;padding:10px 11px;font:inherit;background:#fff}.nzc-report-box textarea{min-height:90px;resize:vertical}.nzc-support-link{border-color:#d9e8ff;background:#f8fbff}.nzc-status-box{margin-bottom:14px}.nzc-status-box h3{margin:0 0 10px}.nzc-status-row{display:flex;justify-content:space-between;gap:10px;padding:10px 0;border-top:1px solid #eee}.nzc-status{font-size:11px;border-radius:999px;padding:5px 8px;white-space:nowrap}.nzc-pending{background:#fff4d8;color:#8b5b00}.nzc-published{background:#e8f7ee;color:#14733d}.nzc-rejected{background:#fff0f1;color:#a31620}.nzc-admin{max-width:1050px}.nzc-admin>.surface{margin-bottom:16px}.nzc-admin h2{font-size:18px;margin:0 0 14px}.nzc-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px}.nzc-stats>div{border:1px solid #e9ebee;border-radius:16px;padding:15px;background:#fff}.nzc-stats b{display:block;font-size:25px}.nzc-stats span{font-size:11px;color:#747b84}.nzc-review{display:grid;grid-template-columns:130px 1fr;gap:14px;padding:14px 0;border-top:1px solid #eceef1}.nzc-review img{width:130px;height:110px;object-fit:cover;border-radius:13px;background:#eee}.nzc-review h3{margin:0 0 4px;font-size:15px}.nzc-review p{line-height:1.9;color:#555}.nzc-admin-actions{display:flex;gap:8px;flex-wrap:wrap}.nzc-reject{display:flex;gap:7px;flex:1;min-width:260px}.nzc-reject input{flex:1}.nzc-report-card{border-top:1px solid #eceef1;padding:13px 0}.nzc-report-card>b,.nzc-report-card>span{display:block;margin-bottom:6px}.nzc-report-card>span{font-size:11px;color:#747b84}.nzc-report-card p{line-height:1.8}.nzc-chat-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;padding:10px 3px}.nzc-chat-head b,.nzc-chat-head small{display:block}.nzc-chat-head small{color:#747b84;margin-top:4px}.nzc-sender{display:block;font-size:10px;color:#b4232c;margin-bottom:3px}.nzc-support-msg{border-color:#dce8f7;background:#f8fbff}
@media(max-width:760px){.nzc-stats{grid-template-columns:1fr 1fr}.nzc-review{grid-template-columns:88px 1fr}.nzc-review img{width:88px;height:88px}.nzc-reject{min-width:100%;flex-direction:column}.nzc-admin-actions{display:grid;grid-template-columns:1fr}.nzc-admin-actions form,.nzc-admin-actions button{width:100%}}
</style>
HTML;
    $html = str_replace('</head>', $css.'\n</head>', $html);
    $html = str_replace('</body>', '<!-- nz-community-v1 -->\n</body>', $html);
    return $html;
}
