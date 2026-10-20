<?php
declare(strict_types=1);

require_once __DIR__ . '/nz-storage.php';

/**
 * Keep the legacy marketplace renderer's JSON view in sync with the
 * authoritative SQLite store. The renderer still reads data.json, while
 * newer modules (including OTP login) write through nzstore_* to SQLite.
 * Without this bridge a freshly authenticated user can exist in SQLite but
 * be invisible to the next rendered page, causing an endless login loop.
 */
function nzlegacy_sync_snapshot(): void {
    $pdo = nzstore_sqlite();
    if (!$pdo) return;

    try {
        $lastWriteRaw = nzstore_meta_get($pdo, 'last_write_at');
        $lastWrite = $lastWriteRaw !== '' ? (int)(strtotime($lastWriteRaw) ?: 0) : 0;
        $lastSnapshot = (int)nzstore_meta_get($pdo, 'last_json_snapshot');

        if ($lastWrite <= 0 || $lastSnapshot >= $lastWrite) return;

        $defaults = [
            'users' => [], 'posts' => [], 'favorites' => [],
            'conversations' => [], 'messages' => [], 'reports' => [],
            'saved_searches' => [], 'notifications' => [], 'payments' => [],
            'blocks' => [], 'drafts' => [],
        ];
        $db = nzstore_read($defaults);
        nzstore_atomic_json(nzstore_legacy_file(), $db);
        nzstore_meta_set($pdo, 'last_json_snapshot', (string)time());
    } catch (Throwable $e) {
        // Compatibility sync must never take the public site down.
    }
}
