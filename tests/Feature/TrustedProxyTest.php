<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    public function test_forwarded_https_headers_are_used_for_generated_livewire_urls(): void
    {
        Route::middleware('web')->get('/proxy-scheme-check', function (Request $request) {
            return response()->json([
                'secure' => $request->isSecure(),
                'scheme' => $request->getScheme(),
                'root' => url('/'),
                'livewire_update' => url('/livewire/update'),
            ]);
        });

        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_HOST' => 'internal-container',
            'HTTP_X_FORWARDED_HOST' => 'isitdown.appz.run',
            'HTTP_X_FORWARDED_PORT' => '443',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ])->get('/proxy-scheme-check');

        $response->assertOk()->assertExactJson([
            'secure' => true,
            'scheme' => 'https',
            'root' => 'https://isitdown.appz.run',
            'livewire_update' => 'https://isitdown.appz.run/livewire/update',
        ]);
    }
}
