<?php

declare(strict_types=1);

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
