<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Menulis audit log (ARCHITECTURE.md §5). $user null = aksi publik tanpa login.
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function log(
        string $action,
        ?Model $auditable = null,
        ?array $before = null,
        ?array $after = null,
        ?string $reason = null,
        ?User $user = null,
    ): AuditLog {
        $request = request();
        $userAgent = $request->userAgent();

        return AuditLog::query()->create([
            'user_id' => $user?->id,
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
            'ip' => $request->ip(),
            'user_agent' => $userAgent === null ? null : Str::limit($userAgent, 500, ''),
        ]);
    }
}
