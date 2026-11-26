<?php

declare(strict_types=1);

it('uses Asia/Jakarta as application timezone', function () {
    expect(config('app.timezone'))->toBe('Asia/Jakarta')
        ->and(now()->getTimezone()->getName())->toBe('Asia/Jakarta');
});

it('uses Indonesian locale and the sync queue', function () {
    expect(config('app.locale'))->toBe('id')
        ->and(config('queue.default'))->toBe('sync');
});

it('serves the public root', function () {
    $this->get('/')->assertOk();
});
