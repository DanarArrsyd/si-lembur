<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pemakaian: ->middleware('role:ADMIN') atau ->middleware('role:ADMIN,SUPERVISOR').
 * Akun nonaktif langsung dikeluarkan. Middleware ini hanya gerbang kasar;
 * akses per data tetap dicek lewat Policy di setiap aksi.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->guest(route('login'));
        }

        if (! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'Akun ini sudah tidak aktif. Hubungi admin.');
        }

        $allowed = array_map(fn (string $role) => UserRole::from($role), $roles);

        abort_unless(in_array($user->role, $allowed, true), Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
