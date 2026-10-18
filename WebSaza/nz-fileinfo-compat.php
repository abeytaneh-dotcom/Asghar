<?php
declare(strict_types=1);

if (!defined('FILEINFO_MIME_TYPE')) {
    define('FILEINFO_MIME_TYPE', 16);
}

if (!class_exists('finfo')) {
    class finfo {
        public function __construct(int $flags = 0, ?string $magic_database = null) {}

        public function file(string $filename, int $flags = 0, mixed $context = null): string|false {
            if (!is_file($filename) || !is_readable($filename)) return false;

            if (function_exists('getimagesize')) {
                $img = @getimagesize($filename);
                if (is_array($img) && !empty($img['mime'])) {
                    $mime = strtolower((string)$img['mime']);
                    if (in_array($mime, ['image/jpeg','image/png','image/webp'], true)) return $mime;
                }
            }

            $head = @file_get_contents($filename, false, null, 0, 64);
            if (!is_string($head) || $head === '') return 'application/octet-stream';

            if (strlen($head) >= 12 && substr($head, 0, 4) === "RIFF" && substr($head, 8, 4) === "WEBP") return 'image/webp';
            if (strlen($head) >= 4 && substr($head, 0, 4) === "\x1A\x45\xDF\xA3") return 'video/webm';
            if (strlen($head) >= 12 && substr($head, 4, 4) === 'ftyp') {
                $brand = substr($head, 8, 4);
                return $brand === 'qt  ' ? 'video/quicktime' : 'video/mp4';
            }

            return 'application/octet-stream';
        }
    }
}
