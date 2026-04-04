<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Support\ApiKeyPermissions;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiKeyHasPermission
{
    /**
     * Handle an incoming API request.
     */
    public function handle(Request $request, Closure $next, string $resource, string $action): Response
    {
        /** @var ApiKey|null $apiKey */
        $apiKey = $request->attributes->get('apiKey');
        $permission = ApiKeyPermissions::permission($resource, $action);

        if ($apiKey === null || ! $apiKey->hasPermission($permission)) {
            return new JsonResponse([
                'message' => "This API key does not have the required permission [{$permission}].",
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
