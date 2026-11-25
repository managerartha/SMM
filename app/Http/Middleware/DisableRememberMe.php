<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** "Ingat saya" dimatikan supaya sesi selalu berakhir setelah tidak aktif (SESSION_LIFETIME). */
class DisableRememberMe
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->request->remove('remember');

        return $next($request);
    }
}
