<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the public form page at the root', function () {
    $this->get('/')->assertInertia(fn (Assert $page) => $page->component('public/form'));
});

it('renders the panel dashboard for signed-in users', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/panel')
        ->assertInertia(fn (Assert $page) => $page->component('panel/dashboard'));
});

it('shares only the fields the UI needs about the signed-in user', function () {
    $department = Department::factory()->create(['name' => 'Produksi']);
    $user = User::factory()->supervisor($department)->create([
        'name' => 'Supervisor Produksi',
        'username' => 'spv.produksi',
        'email' => 'spv@example.com',
    ]);

    $this->actingAs($user)->get('/panel')->assertInertia(fn (Assert $page) => $page
        ->where('auth.user', [
            'id' => $user->id,
            'name' => 'Supervisor Produksi',
            'username' => 'spv.produksi',
            'role' => 'SUPERVISOR',
            'department' => ['id' => $department->id, 'name' => 'Produksi'],
        ]));
});

it('shares a null user for guests', function () {
    $this->get('/')->assertInertia(fn (Assert $page) => $page->where('auth.user', null));
});

it('ships a light-only document without theme scripts', function () {
    $html = $this->get('/')->getContent();

    expect($html)->toContain('<html lang="id"')
        ->not->toContain('prefers-color-scheme')
        ->not->toContain('appearance')
        ->not->toMatch('/class="[^"]*\bdark\b/');
});
