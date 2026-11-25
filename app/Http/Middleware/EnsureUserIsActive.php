<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Keluarkan pengguna yang dinonaktifkan / masa akunnya habis, walau sesinya masih ada. */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && ! $user->canAccess()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Akun Anda nonaktif atau masa berlakunya sudah habis.']);
        }

        // Verifikasi dua langkah hanya untuk peran yang diizinkan (GM, Direktur, MR).
        if ($user && $request->is('user/two-factor-*') && ! $request->isMethod('GET') && ! $request->isMethod('DELETE') && ! $user->can('akun.2fa')) {
            abort(403, 'Verifikasi dua langkah tidak tersedia untuk peran Anda.');
        }

        return $next($request);
    }
}
