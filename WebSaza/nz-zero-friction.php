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
    $tourCss = '<link rel="stylesheet" href="/nz-onboarding.css?v=20261020b">';
    $js  = '<script src="/nz-zero-friction.js?v=20261020" defer></script>';
    $fix = '<script src="/nz-zero-friction-fixes.js?v=20261020" defer></script>';
    $tourJs = '<script src="/nz-onboarding.js?v=20261020c" defer></script>';

    if (!str_contains($html, '/nz-zero-friction.css')) {
        $html = str_replace('</head>', $css."\n</head>", $html);
    }
    if (!str_contains($html, '/nz-onboarding.css')) {
        $html = str_replace('</head>', $tourCss."\n</head>", $html);
    }
    if (!str_contains($html, '/nz-zero-friction.js')) {
        $html = str_replace('</body>', $js."\n</body>", $html);
    }
    if (!str_contains($html, '/nz-zero-friction-fixes.js')) {
        $html = str_replace('</body>', $fix."\n</body>", $html);
    }
    if (!str_contains($html, '/nz-onboarding.js')) {
        $html = str_replace('</body>', $tourJs."\n</body>", $html);
    }
    return $html;
}
