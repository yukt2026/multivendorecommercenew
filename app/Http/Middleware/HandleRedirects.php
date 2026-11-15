<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\RedirectRule;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class HandleRedirects
{
    /**
     * Handle an incoming request and apply active 301/302 redirects.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only inspect safe read requests (GET / HEAD)
        if (!$request->isMethodSafe()) {
            return $next($request);
        }

        // Never redirect administrative, seller console, API, or system health endpoints
        if ($request->is('admin*', 'seller*', 'api*', '_ignition*', 'up')) {
            return $next($request);
        }

        // Normalize path: e.g. 'old-category/' -> '/old-category'
        $path = '/' . ltrim(rtrim($request->path(), '/'), '/');

        // Look up active redirect rule from cached mapping (fast in-memory/file lookup)
        try {
            $rulesMap = Cache::remember('redirect_rules.map', 3600, function () {
                return RedirectRule::active()
                    ->get(['id', 'source_path', 'target_path', 'status_code'])
                    ->keyBy('source_path')
                    ->all();
            });
            $rule = $rulesMap[$path] ?? null;
        } catch (\Throwable) {
            $rule = null;
        }

        if ($rule) {
            // Guard against self-redirect loops
            if ($rule->target_path === $path) {
                return $next($request);
            }

            $rule->recordHit();

            $target = $rule->target_path;
            $queryString = $request->getQueryString();

            // Append query string if target doesn't already have one
            if ($queryString && !str_contains($target, '?')) {
                $target .= '?' . $queryString;
            }

            $targetUrl = str_starts_with($target, 'http://') || str_starts_with($target, 'https://')
                ? $target
                : url($target);

            return redirect($targetUrl, (int) $rule->status_code);
        }

        return $next($request);
    }
}
