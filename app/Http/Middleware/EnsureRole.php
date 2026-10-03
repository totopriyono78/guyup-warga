<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Pemakaian: ->middleware('role:admin,rt') */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_unless($user && in_array($user->role, $roles, true), 403, 'Anda tidak memiliki akses ke halaman ini.');

        // Pengurus RT yang RT-nya sudah dihapus tidak boleh mengakses halaman pengurus
        abort_if($user->role === $user::ROLE_RT && $user->rt_id === null, 403, 'Akun pengurus RT Anda belum terhubung ke RT mana pun. Hubungi pengurus RW.');

        return $next($request);
    }
}
