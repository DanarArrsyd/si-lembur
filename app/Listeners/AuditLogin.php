<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;

final class AuditLogin
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Login|Failed $event): void
    {
        if ($event->guard !== 'web') {
            return;
        }

        $user = $event->user instanceof User ? $event->user : null;

        if ($event instanceof Login) {
            if ($user?->is_active) {
                $this->audit->log('auth.login_succeeded', $user, user: $user);
            }

            return;
        }

        $this->audit->log('auth.login_failed', $user, reason: 'Kredensial tidak valid atau akun tidak aktif.');
    }
}
