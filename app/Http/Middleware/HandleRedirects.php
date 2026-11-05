<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\RedirectRule;
use Closure;
use Illuminate\Http\Request;
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

        // Normalize path: e.g. 'old-category/' -> '/old-category'
        $path = '/' . ltrim(rtrim($request->path(), '/'), '/');

        // Look up active redirect rule
        $rule = RedirectRule::active()
            ->where('source_path', $path)
            ->first();

        if ($rule) {
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
