<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies;

/** Proxy tepercaya dibaca dari config (tetap berfungsi saat config di-cache). Bawaan: tidak ada. */
class TrustConfiguredProxies extends TrustProxies
{
    protected function proxies()
    {
        $p = (string) config('smm.trusted_proxies');

        return $p === '' ? null : ($p === '*' ? '*' : array_map('trim', explode(',', $p)));
    }
}
