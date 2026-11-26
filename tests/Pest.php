<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

// Test paralel: beberapa proses PHP terpisah harus melihat data yang sudah di-commit,
// jadi tidak boleh dibungkus transaksi RefreshDatabase. Hanya jalan di MySQL/MariaDB.
pest()->extend(TestCase::class)
    ->use(DatabaseTruncation::class)
    ->beforeEach(function () {
        if (! in_array(config('database.default'), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Test paralel butuh MySQL/MariaDB.');
        }
    })
    ->in('Concurrency');
