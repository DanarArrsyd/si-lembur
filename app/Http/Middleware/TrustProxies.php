<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;

/**
 * Proxy tepercaya dari `TRUSTED_PROXIES` (.env → config app.trusted_proxies).
 *
 * Kosong = tidak ada proxy dipercaya, header X-Forwarded-For diabaikan. Isi dengan IP proxy
 * (pisahkan koma) atau `*` bila aplikasi berada di belakang proxy/Cloudflare Hostinger, supaya
 * throttle per IP memakai IP HP karyawan, bukan IP proxy (docs/ARCHITECTURE.md §5).
 */
class TrustProxies extends Middleware
{
    /**
     * @return array<int, string>|string|null
     */
    protected function proxies()
    {
        $value = trim((string) config('app.trusted_proxies'));

        if ($value === '') {
            return null;
        }

        if ($value === '*') {
            return '*';
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }
}
