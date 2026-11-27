<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Route::middleware(['web', 'auth', 'role:ADMIN'])->get('/_test/admin-only', fn () => 'ok');
    Route::middleware(['web', 'auth', 'role:ADMIN,SUPERVISOR'])->get('/_test/panel', fn () => 'ok');
});

it('redirects guests from the panel to login', function () {
    $this->get('/panel')->assertRedirect('/login');
});

it('lets admins and supervisors open the panel', function (string $role) {
    $user = $role === 'admin'
        ? User::factory()->admin()->create()
        : User::factory()->supervisor()->create();

    $this->actingAs($user)->get('/panel')->assertOk();
})->with(['admin', 'supervisor']);

it('forbids supervisors on admin-only routes', function () {
    $this->actingAs(User::factory()->supervisor(Department::factory()->create())->create())
        ->get('/_test/admin-only')
        ->assertForbidden();
});

it('allows admins on admin-only routes', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/_test/admin-only')
        ->assertOk();
});

it('ends a session when the account becomes inactive and shows the login message', function () {
    $user = User::factory()->supervisor()->create();
    $this->actingAs($user)->get('/panel')->assertOk();

    $user->update(['is_active' => false]);

    $this->get('/panel')
        ->assertRedirect('/login')
        ->assertSessionHas('status', 'Akun ini sudah tidak aktif. Hubungi admin.');
    $this->assertGuest();

    $this->get('/login')->assertInertia(fn (AssertableInertia $page) => $page
        ->component('auth/login')
        ->where('status', 'Akun ini sudah tidak aktif. Hubungi admin.'));
});

it('does not report a successful login when an inactive account returns with an old remember cookie', function () {
    $user = User::factory()->supervisor()->create(['is_active' => false]);
    $cookie = Auth::guard('web')->getRecallerName();
    $value = $user->id.'|'.$user->getRememberToken().'|'.$user->getAuthPassword();

    $this->withCookie($cookie, $value)->get('/panel')
        ->assertRedirect('/login')
        ->assertSessionHas('status', 'Akun ini sudah tidak aktif. Hubungi admin.');

    $this->assertGuest();
    expect(AuditLog::query()->where('action', 'auth.login_succeeded')->count())->toBe(0);
});
