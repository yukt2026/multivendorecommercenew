<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\StoreConnectionType;
use App\Models\StoreConnection;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyCustomApiCredentials
{
    /**
     * Handle an incoming Custom API integration request with API Key, Secret, and Scopes.
     */
    public function handle(Request $request, Closure $next, ?string $requiredScope = null): Response
    {
        $apiKey = $request->header('X-Api-Key')
            ?? $request->header('X-OneStall-Key')
            ?? $request->header('X-OneStall-Api-Key');

        $apiSecret = $request->header('X-Api-Secret')
            ?? $request->header('X-OneStall-Secret');

        $signature = $request->header('X-OneStall-Signature');

        // Check Bearer Token "key:secret" format fallback
        if (empty($apiKey) && $request->bearerToken()) {
            $parts = explode(':', $request->bearerToken(), 2);
            $apiKey = $parts[0] ?? null;
            $apiSecret = $parts[1] ?? null;
        }

        if (empty($apiKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Missing X-Api-Key or Authorization header.',
            ], 401);
        }

        $connection = StoreConnection::where('type', StoreConnectionType::CUSTOM_API)
            ->where('api_key', $apiKey)
            ->where('status', 'active')
            ->first();

        if (!$connection) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or revoked API key.',
            ], 401);
        }

        // Verify Secret or HMAC Signature
        $secretValid = false;
        if (!empty($apiSecret) && $connection->verifyApiSecret($apiSecret)) {
            $secretValid = true;
        } elseif (!empty($signature)) {
            $rawPayload = $request->getContent();
            if ($connection->verifyWebhookSignature($rawPayload, $signature)) {
                $secretValid = true;
            }
        } elseif (empty($connection->api_secret_hash)) {
            // No secret required if secret was omitted
            $secretValid = true;
        }

        if (!$secretValid) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid API Secret or signature authentication failed.',
            ], 401);
        }

        // Verify Scopes if specified
        if ($requiredScope) {
            $allowedScopes = $connection->config['scopes'] ?? [];
            if (!in_array($requiredScope, $allowedScopes, true) && !in_array('*', $allowedScopes, true)) {
                return response()->json([
                    'success' => false,
                    'message' => "Forbidden: this API key lacks the '{$requiredScope}' scope.",
                ], 403);
            }
        }

        $request->attributes->set('store_connection', $connection);
        $request->attributes->set('store', $connection->store);
        $request->attributes->set('seller', $connection->store?->seller);

        return $next($request);
    }
}
