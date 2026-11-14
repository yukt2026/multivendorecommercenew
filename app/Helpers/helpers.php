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

if (!function_exists('image_url')) {
    /**
     * Resolve any image path or URL into a browser-loadable URL matching the current request host.
     *
     * Handles:
     * - Full URLs with localhost:8000 or 127.0.0.1:8000 (normalizes to current request host)
     * - Relative paths: 'stores/logos/...', 'products/images/...', 'categories/...'
     * - Paths prefixed with 'storage/' or '/storage/'
     * - Data URIs and external CDN URLs
     *
     * @param string|null $path
     * @param string|null $fallback
     * @return string|null
     */
    function image_url(?string $path, ?string $fallback = null): ?string
    {
        if (empty($path)) {
            return $fallback;
        }

        // Inline Data URIs or SVGs
        if (str_starts_with($path, 'data:image')) {
            return $path;
        }

        // Check if it has http:// or https://
        if (preg_match('#^https?://#i', $path)) {
            $parsed = parse_url($path);
            $host = strtolower($parsed['host'] ?? '');
            // If the host is local (localhost, 127.0.0.1, etc.), normalize to current request scheme & host
            if ($host === 'localhost' || $host === '127.0.0.1' || $host === '0.0.0.0' || str_ends_with($host, '.test')) {
                $pathPart = ltrim($parsed['path'] ?? '', '/');
                return asset($pathPart);
            }
            return $path;
        }

        $clean = ltrim($path, '/');
        if (str_starts_with($clean, 'storage/')) {
            return asset($clean);
        }

        return asset('storage/' . $clean);
    }
}
