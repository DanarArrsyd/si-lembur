<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\User;

it('renders the login screen', function () {
    $this->get('/login')->assertOk();
});

it('logs in with username and password', function () {
    $user = User::factory()->create(['username' => 'admin', 'password' => 'rahasia123']);

    $this->post('/login', ['username' => 'admin', 'password' => 'rahasia123'])
        ->assertRedirect('/panel');

    $this->assertAuthenticatedAs($user);
});

it('rejects a wrong password', function () {
    User::factory()->create(['username' => 'admin', 'password' => 'rahasia123']);

    $this->post('/login', ['username' => 'admin', 'password' => 'salah'])
        ->assertSessionHasErrors('username');

    $this->assertGuest();
});

it('rejects inactive accounts before creating a session and logs the failed attempt', function (string $role) {
    $factory = $role === 'admin' ? User::factory()->admin() : User::factory()->supervisor();
    $user = $factory->create(['is_active' => false, 'password' => 'rahasia123']);

    $this->from('/login')->post('/login', [
        'username' => $user->username,
        'password' => 'rahasia123',
        'remember' => '1',
    ])->assertRedirect('/login')->assertSessionHasErrors(['username' => 'Nama pengguna atau kata sandi salah.']);

    $this->assertGuest();
    $this->assertDatabaseHas('users', ['id' => $user->id, 'remember_token' => $user->remember_token]);
    $audit = AuditLog::query()->sole();

    expect($audit->action)->toBe('auth.login_failed')
        ->and($audit->user_id)->toBeNull()
        ->and($audit->auditable_id)->toBe($user->id);
})->with(['admin', 'supervisor']);

it('audits a successful login without storing credentials', function () {
    $password = 'rahasia123';
    $user = User::factory()->create(['password' => $password]);

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
        ->withHeader('User-Agent', 'SI-LEMBUR Test')
        ->post('/login', ['username' => $user->username, 'password' => $password])
        ->assertRedirect('/panel');

    $audit = AuditLog::query()->sole();
    expect($audit->action)->toBe('auth.login_succeeded')
        ->and($audit->user_id)->toBe($user->id)
        ->and($audit->auditable_id)->toBe($user->id)
        ->and($audit->ip)->toBe('192.0.2.10')
        ->and($audit->user_agent)->toBe('SI-LEMBUR Test')
        ->and($audit->before)->toBeNull()
        ->and($audit->after)->toBeNull()
        ->and($audit->toJson())->not->toContain($password, $user->password);
});

it('audits wrong credentials without copying the attempted secrets', function () {
    $user = User::factory()->create(['password' => 'rahasia123']);
    $attempted = 'salah123';
    $unknown = 'unknown-user';

    $this->post('/login', ['username' => $user->username, 'password' => $attempted])
        ->assertSessionHasErrors(['username' => 'Nama pengguna atau kata sandi salah.']);
    $this->post('/login', ['username' => $unknown, 'password' => $attempted])
        ->assertSessionHasErrors(['username' => 'Nama pengguna atau kata sandi salah.']);

    $this->assertGuest();
    $audits = AuditLog::query()->orderBy('id')->get();
    expect($audits)->toHaveCount(2)
        ->and($audits->pluck('action')->all())->toBe(['auth.login_failed', 'auth.login_failed'])
        ->and($audits->pluck('user_id')->all())->toBe([null, null])
        ->and($audits->pluck('auditable_id')->all())->toBe([$user->id, null])
        ->and($audits->toJson())->not->toContain($attempted, $unknown, $user->password);
});

it('does not accept the email field', function () {
    User::factory()->create(['username' => 'admin', 'email' => 'admin@example.com', 'password' => 'rahasia123']);

    $this->post('/login', ['email' => 'admin@example.com', 'password' => 'rahasia123'])
        ->assertSessionHasErrors('username');

    $this->assertGuest();
});

it('throttles after five attempts per username and ip', function () {
    User::factory()->create(['username' => 'admin', 'password' => 'rahasia123']);

    foreach (range(1, 5) as $_) {
        $this->post('/login', ['username' => 'admin', 'password' => 'salah']);
    }

    $this->post('/login', ['username' => 'admin', 'password' => 'rahasia123'])
        ->assertTooManyRequests();

    $this->assertGuest();
});

it('logs out', function () {
    $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect('/');

    $this->assertGuest();
});

it('has no registration, recovery or self-service settings routes', function (string $method, string $uri) {
    $this->actingAs(User::factory()->create())
        ->call($method, $uri)
        ->assertNotFound();
})->with([
    ['GET', '/register'],
    ['POST', '/register'],
    ['GET', '/forgot-password'],
    ['POST', '/forgot-password'],
    ['GET', '/reset-password/token'],
    ['GET', '/two-factor-challenge'],
    ['GET', '/user/confirm-password'],
    ['GET', '/settings/profile'],
    ['GET', '/settings/security'],
    ['GET', '/settings/appearance'],
    ['GET', '/dashboard'],
]);
