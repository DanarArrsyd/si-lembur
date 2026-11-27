<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\Employee;
use App\Models\Overtime;
use App\Services\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-10 11:59', 'Asia/Jakarta'));
    $this->employee = Employee::factory()->create();
    $this->payload = [
        'department_id' => $this->employee->department_id,
        'employee_id' => $this->employee->id,
        'date_choice' => 'YESTERDAY',
        'overtime_date' => '2026-11-09',
        'start_time' => '23:00',
        'end_time' => '02:00',
        'break_minutes' => 30,
        'description' => 'Menyelesaikan pesanan produksi',
        'website' => '',
    ];
});

it('renders active departments and date choices from the server clock', function () {
    Department::factory()->create(['is_active' => false]);

    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->component('public/form')
        ->has('departments', 1)
        ->where('serverTime', '11:59')
        ->where('maxGrossMinutes', 960)
        ->where('dateChoices.0.label', 'Hari ini (10 Nov 2026)')
        ->where('dateChoices.1.label', 'Kemarin (09 Nov 2026)'));

    $this->travel(1)->minutes();

    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->has('dateChoices', 1)
        ->where('serverTime', '12:00'));
});

it('previews overnight overtime without writing data, audit or a number', function () {
    $this->from('/')->post('/lembur/periksa', $this->payload)
        ->assertRedirect('/')
        ->assertInertiaFlash('review.date', '2026-11-09')
        ->assertInertiaFlash('review.start_at', '2026-11-09 23:00')
        ->assertInertiaFlash('review.end_at', '2026-11-10 02:00')
        ->assertInertiaFlash('review.total_minutes', 150)
        ->assertInertiaFlash('review.employee.name', $this->employee->name);

    $this->assertDatabaseEmpty('overtimes');
    $this->assertDatabaseEmpty('audit_logs');
    $this->assertDatabaseEmpty('overtime_number_counters');
});

it('returns the overlap error without changing the existing entry', function () {
    Overtime::factory()->for($this->employee)->create([
        'number' => 'LBR-20261109-0001',
        'overtime_date' => '2026-11-09',
        'start_at' => '2026-11-09 22:00:00',
        'end_at' => '2026-11-10 01:00:00',
    ]);

    $this->from('/')->post('/lembur/periksa', $this->payload)
        ->assertSessionHasErrors(['start_time' => 'Sudah ada lembur LBR-20261109-0001 di jam yang sama.']);
    $this->assertDatabaseCount('overtimes', 1);
});

it('rejects yesterday after the cutoff', function () {
    $this->travel(1)->minutes();

    $this->from('/')->post('/lembur/periksa', $this->payload)
        ->assertSessionHasErrors(['overtime_date' => 'Batas input lembur kemarin sudah lewat (12:00). Hubungi supervisor untuk input lembur kemarin.']);
});

it('rejects a date choice that shifted while the page was open', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-11 00:01', 'Asia/Jakarta'));

    $this->from('/')->post('/lembur/periksa', [...$this->payload, 'date_choice' => 'TODAY', 'overtime_date' => '2026-11-10'])
        ->assertSessionHasErrors(['overtime_date' => 'Tanggal lembur sudah berubah. Pilih tanggal lagi.']);
});

it('rejects an overtime that has not ended yet', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-10 01:45', 'Asia/Jakarta'));

    $this->from('/')->post('/lembur/periksa', $this->payload)
        ->assertSessionHasErrors(['end_time' => 'Isi lembur setelah jam selesai.']);
});

it('returns field errors for a short description and an invalid date choice', function () {
    $this->from('/')->post('/lembur/periksa', [...$this->payload, 'description' => ' abc '])
        ->assertSessionHasErrors(['description' => 'Tulis kegiatan lembur minimal 5 huruf.']);
    $this->from('/')->post('/lembur/periksa', [...$this->payload, 'date_choice' => 'TOMORROW'])
        ->assertSessionHasErrors(['date_choice']);
});

it('returns a daily warning using the configured limit without saving', function () {
    app(Settings::class)->set('daily_limit_hours', 3.5);

    $this->from('/')->post('/lembur/periksa', [...$this->payload, 'start_time' => '21:00'])
        ->assertRedirect('/')
        ->assertInertiaFlash('review.exceeds_daily_limit', true)
        ->assertInertiaFlash('review.daily_limit_minutes', 210);

    $this->assertDatabaseEmpty('overtimes');
});

it('uses the same chronological weekly threshold as the persisted overtime', function () {
    app(Settings::class)->set('weekly_limit_hours', 4);
    Overtime::factory()->for($this->employee)->create([
        'overtime_date' => '2026-11-09',
        'start_at' => '2026-11-09 17:00:00',
        'end_at' => '2026-11-09 20:00:00',
        'total_minutes' => 180,
    ]);

    $this->from('/')->post('/lembur/periksa', $this->payload)
        ->assertRedirect('/')
        ->assertInertiaFlash('review.exceeds_weekly_limit', true)
        ->assertInertiaFlash('review.weekly_limit_minutes', 240);
});

it('rejects and logs a filled honeypot without saving', function () {
    Log::spy();

    $this->postJson('/lembur/periksa', [...$this->payload, 'website' => 'bot'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Pengajuan tidak bisa diproses. Muat ulang halaman lalu coba lagi.');

    Log::shouldHaveReceived('notice')->once();
    $this->assertDatabaseEmpty('overtimes');
});

it('limits preview to ten requests per minute independently of lookup', function () {
    $lookup = '/api/departments/'.$this->employee->department_id.'/employees';

    foreach (range(1, 10) as $_) {
        $this->getJson($lookup)->assertOk();
        $this->from('/')->post('/lembur/periksa', $this->payload)->assertRedirect('/');
    }

    $this->postJson('/lembur/periksa', $this->payload)->assertTooManyRequests();
    $this->getJson($lookup)->assertOk();
});
