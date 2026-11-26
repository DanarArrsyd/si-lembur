<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Overtime;
use App\Models\User;
use App\Services\AuditLogger;

it('records actor, subject, payload, reason and request info', function () {
    $user = User::factory()->create();
    $overtime = Overtime::factory()->create();
    $this->app['request']->server->set('REMOTE_ADDR', '10.0.0.7');
    $this->app['request']->headers->set('User-Agent', 'Mozilla/5.0 Test');

    $log = app(AuditLogger::class)->log('overtime.rejected', $overtime, ['status' => 'PENDING'], ['status' => 'REJECTED'], 'Jam tidak sesuai', $user);

    expect($log->fresh())
        ->user_id->toBe($user->id)
        ->action->toBe('overtime.rejected')
        ->auditable_type->toBe(Overtime::class)
        ->auditable_id->toBe($overtime->id)
        ->before->toBe(['status' => 'PENDING'])
        ->after->toBe(['status' => 'REJECTED'])
        ->reason->toBe('Jam tidak sesuai')
        ->ip->toBe('10.0.0.7')
        ->user_agent->toBe('Mozilla/5.0 Test');
});

it('records anonymous public actions without a user', function () {
    $log = app(AuditLogger::class)->log('overtime.submitted', Overtime::factory()->create());

    expect($log->user_id)->toBeNull()
        ->and(AuditLog::query()->count())->toBe(1);
});
