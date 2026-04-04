<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    /**
     * Handle an incoming API request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $plainTextToken = $request->bearerToken();

        if (! is_string($plainTextToken) || trim($plainTextToken) === '') {
            return new JsonResponse([
                'message' => 'A valid bearer token is required.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $apiKey = ApiKey::query()
            ->with('user')
            ->active()
            ->where('token_hash', ApiKey::hashToken($plainTextToken))
            ->first();

        if ($apiKey === null || $apiKey->user === null) {
            return new JsonResponse([
                'message' => 'The provided API key is invalid, expired, revoked, or no longer linked to a user.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $request->setUserResolver(fn () => $apiKey->user);
        $request->attributes->set('apiKey', $apiKey);

        $apiKey->forceFill([
            'last_used_at' => now(),
        ])->save();

        return $next($request);
    }
}
