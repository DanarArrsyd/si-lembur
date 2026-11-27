<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('/_test/client-ip', fn (Request $request) => response()->json(['ip' => $request->ip()]));
});

it('uses the connecting IP when no proxies are configured', function () {
    config(['trustedproxy.proxies' => []]);

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
        ->getJson('/_test/client-ip', ['X-Forwarded-For' => '198.51.100.10'])
        ->assertExactJson(['ip' => '192.0.2.10']);
});

it('uses the forwarded client IP from a configured proxy', function () {
    config(['trustedproxy.proxies' => ['192.0.2.0/24']]);

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
        ->getJson('/_test/client-ip', ['X-Forwarded-For' => '198.51.100.10'])
        ->assertExactJson(['ip' => '198.51.100.10']);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->getJson('/_test/client-ip', ['X-Forwarded-For' => '198.51.100.10'])
        ->assertExactJson(['ip' => '203.0.113.10']);
});
