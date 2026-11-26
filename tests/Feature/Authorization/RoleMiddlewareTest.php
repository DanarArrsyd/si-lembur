<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\Route;

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

it('logs out inactive users and sends them to login', function () {
    $user = User::factory()->admin()->create(['is_active' => false]);

    $this->actingAs($user)->get('/_test/panel')->assertRedirect('/login');

    $this->assertGuest();
});
