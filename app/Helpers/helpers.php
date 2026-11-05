<?php

use App\Models\Setting;

if (!function_exists('setting')) {
    /**
     * Retrieve a setting by key, with cached lookup and fallback default.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return Setting::get($key, $default);
    }
}

if (!function_exists('format_currency')) {
    /**
     * Format a monetary decimal value into display currency with symbol.
     *
     * @param float|int|string|null $amount
     * @param string|null $currencySymbol
     * @return string
     */
    function format_currency(float|int|string|null $amount, ?string $currencySymbol = null): string
    {
        $symbol = $currencySymbol ?? setting('currency_symbol', '₹');
        $val = (float) ($amount ?? 0);

        return $symbol . ' ' . number_format($val, 2);
    }
}

if (!function_exists('clean_html')) {
    /**
     * Sanitize rich-text HTML input to eliminate XSS vectors.
     * Strips dangerous tags, event handlers (on*), and pseudo-protocols (javascript:).
     *
     * @param string|null $html
     * @return string
     */
    function clean_html(?string $html): string
    {
        if (empty($html)) {
            return '';
        }

        // 1. Remove dangerous script, style, iframe, embed tags
        $clean = preg_replace('/<(script|style|iframe|object|embed|applet)[^>]*>.*?<\/\1>/si', '', $html);

        // 2. Allow only safe markup tags
        $allowedTags = '<p><br><b><strong><i><em><u><ul><ol><li><a><h1><h2><h3><h4><h5><h6><blockquote><table><thead><tbody><tr><th><td><hr>';
        $clean = strip_tags((string) $clean, $allowedTags);

        // 3. Strip inline event handlers: onclick, onmouseover, onerror, etc.
        $clean = preg_replace('/\s*on\w+\s*=\s*([\'\"]).*?\1/si', '', $clean);
        $clean = preg_replace('/\s*on\w+\s*=\s*[^>\s]+/si', '', $clean);

        // 4. Strip dangerous href protocols like javascript:, data:, vbscript:
        $clean = preg_replace('/href\s*=\s*([\'\"])\s*(javascript|vbscript|data):.*?\1/si', 'href="#"', $clean);

        return $clean;
    }
}
