<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Closure;
use Illuminate\Auth\AuthManager;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\LoginRateLimiter;
use LogicException;

final class AttemptActiveLogin
{
    public function __construct(
        private readonly AuthManager $auth,
        private readonly LoginRateLimiter $limiter,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $guard = $this->auth->guard('web');

        if (! $guard instanceof SessionGuard) {
            throw new LogicException('The Fortify login pipeline requires the web session guard.');
        }

        if ($guard->attemptWhen(
            $request->only(Fortify::username(), 'password'),
            fn (Authenticatable $user): bool => $user instanceof User && $user->is_active,
            $request->boolean('remember'),
        )) {
            return $next($request);
        }

        $this->limiter->increment($request);

        throw ValidationException::withMessages([
            Fortify::username() => [trans('auth.failed')],
        ]);
    }
}
