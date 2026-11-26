<?php

declare(strict_types=1);

use Database\Seeders\UserSeeder;

it('refuses to seed accounts with the fallback password in production', function () {
    $this->app['env'] = 'production';
    config(['seed.default_password' => null]);

    expect(fn () => app(UserSeeder::class)->run())->toThrow(RuntimeException::class);
});
