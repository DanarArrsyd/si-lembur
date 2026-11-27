<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\Employee;
use App\Models\Overtime;
use App\Services\Settings;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-10 11:59'));
    $this->employee = Employee::factory()->create(['name' => 'Budi Santoso']);
    $this->input = [
        'department_id' => $this->employee->department_id,
        'employee_id' => $this->employee->id,
        'date_choice' => 'YESTERDAY',
        'start_time' => '23:00',
        'end_time' => '02:00',
        'break_minutes' => 30,
        'description' => 'Menyelesaikan pesanan',
        'website' => '',
    ];
});

it('provides active departments and date choices using the server clock', function () {
    Department::factory()->create(['is_active' => false]);

    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->component('public/form')
        ->where('departments', [['id' => $this->employee->department_id, 'name' => $this->employee->department->name]])
        ->where('dateChoices', [
            ['value' => 'TODAY', 'label' => 'Hari ini (10 Nov 2026)', 'date' => '2026-11-10'],
            ['value' => 'YESTERDAY', 'label' => 'Kemarin (09 Nov 2026)', 'date' => '2026-11-09'],
        ])
        ->where('serverTime', '11:59')
        ->where('maxGrossMinutes', 960));

    $this->travel(1)->minute();
    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->where('dateChoices', [['value' => 'TODAY', 'label' => 'Hari ini (10 Nov 2026)', 'date' => '2026-11-10']]));
});

it('flashes an overnight review without writing overtime, audit, or number data', function () {
    $this->post('/lembur/periksa', $this->input)
        ->assertRedirect('/')
        ->assertInertiaFlash('review.date', '2026-11-09')
        ->assertInertiaFlash('review.start_at', '2026-11-09 23:00')
        ->assertInertiaFlash('review.end_at', '2026-11-10 02:00')
        ->assertInertiaFlash('review.total_minutes', 150)
        ->assertInertiaFlash('review.employee.name', 'Budi Santoso');

    $this->assertDatabaseCount('overtimes', 0);
    $this->assertDatabaseCount('audit_logs', 0);
    $this->assertDatabaseCount('overtime_number_counters', 0);
});

it('returns field errors for invalid review data', function (array $overrides, string $field, string $message) {
    $this->from('/')->post('/lembur/periksa', [...$this->input, ...$overrides])
        ->assertRedirect('/')
        ->assertSessionHasErrors([$field => $message])
        ->assertInertiaFlashMissing('review');
})->with([
    'quarter hour' => [['start_time' => '23:05'], 'start_time', 'Jam harus kelipatan 15 menit.'],
    'employee' => [['employee_id' => 999999], 'employee_id', 'Nama tidak ditemukan di departemen ini.'],
    'description' => [['description' => ' ab '], 'description', 'Tulis kegiatan lembur minimal 5 huruf.'],
    'unfinished' => [['date_choice' => 'TODAY', 'start_time' => '11:00', 'end_time' => '12:00'], 'end_time', 'Jam selesai belum lewat. Catat lembur setelah selesai.'],
]);

it('rejects yesterday at the cutoff and reloads the available dates', function () {
    $this->travel(1)->minute();

    $this->from('/')->post('/lembur/periksa', $this->input)
        ->assertRedirect('/')
        ->assertSessionHasErrors(['overtime_date' => 'Batas input lembur kemarin sudah lewat (12:00). Hubungi supervisor untuk input lembur kemarin.']);

    $this->get('/')->assertInertia(fn (Assert $page) => $page->has('dateChoices', 1));
});

it('rejects a displayed date that no longer matches its choice after midnight', function () {
    $this->from('/')->post('/lembur/periksa', [...$this->input, 'overtime_date' => '2026-11-08'])
        ->assertSessionHasErrors(['overtime_date' => 'Tanggal lembur sudah berubah. Pilih tanggal kembali.'])
        ->assertInertiaFlashMissing('review');
});

it('rejects overlap and never changes existing records during review', function () {
    $existing = Overtime::factory()->for($this->employee)->for($this->employee->department)->create([
        'overtime_date' => '2026-11-09',
        'start_at' => '2026-11-09 22:00',
        'end_at' => '2026-11-09 23:30',
    ]);

    $this->from('/')->post('/lembur/periksa', $this->input)
        ->assertSessionHasErrors(['start_time' => "Sudah ada lembur {$existing->number} di jam yang sama."]);
    $this->assertDatabaseCount('overtimes', 1);
});

it('warns using configured limits and prior work without updating their flags', function () {
    app(Settings::class)->set('daily_limit_hours', 3.5);
    app(Settings::class)->set('weekly_limit_hours', 3.5);
    $existing = Overtime::factory()->for($this->employee)->for($this->employee->department)->create([
        'overtime_date' => '2026-11-09',
        'start_at' => '2026-11-09 18:00',
        'end_at' => '2026-11-09 20:00',
        'total_minutes' => 120,
    ]);

    $this->post('/lembur/periksa', $this->input)
        ->assertInertiaFlash('review.exceeds_daily_limit', true)
        ->assertInertiaFlash('review.exceeds_weekly_limit', true)
        ->assertInertiaFlash('review.daily_limit_minutes', 210);

    expect($existing->fresh()->exceeds_daily_limit)->toBeFalse();
    $this->assertDatabaseCount('overtimes', 1);
});

it('rejects a filled honeypot and limits reviews to ten per IP', function () {
    $this->postJson('/lembur/periksa', [...$this->input, 'website' => 'https://example.test'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Pengajuan tidak bisa diproses. Muat ulang halaman lalu coba lagi.');

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.20']);
    for ($request = 0; $request < 10; $request++) {
        $this->post('/lembur/periksa', $this->input)->assertRedirect('/');
    }
    $this->post('/lembur/periksa', $this->input)->assertTooManyRequests();
});
