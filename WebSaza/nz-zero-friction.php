<?php
declare(strict_types=1);

/**
 * Lightweight presentation layer for Niazestan.
 * Keeps marketplace/domain logic untouched and only injects the zero-friction UX assets.
 */
function nzux_output_filter(string $html): string {
    if (function_exists('nzext_output_filter')) {
        $html = nzext_output_filter($html);
    }
    if ($html === '') return $html;

    $css = '<link rel="stylesheet" href="/nz-zero-friction.css?v=20261020">';
    $js  = '<script src="/nz-zero-friction.js?v=20261020" defer></script>';
    $fix = '<script src="/nz-zero-friction-fixes.js?v=20261020" defer></script>';

    if (!str_contains($html, '/nz-zero-friction.css')) {
        $html = str_replace('</head>', $css."\n</head>", $html);
    }
    if (!str_contains($html, '/nz-zero-friction.js')) {
        $html = str_replace('</body>', $js."\n</body>", $html);
    }
    if (!str_contains($html, '/nz-zero-friction-fixes.js')) {
        $html = str_replace('</body>', $fix."\n</body>", $html);
    }
    return $html;
}
