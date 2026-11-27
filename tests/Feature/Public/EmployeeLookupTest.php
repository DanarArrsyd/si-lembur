<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

function lookupUrl(Department|int $department): string
{
    $id = $department instanceof Department ? $department->id : $department;

    return "/api/departments/{$id}/employees";
}

it('returns only id, nik and name of active employees in the department, sorted by name', function () {
    $department = Department::factory()->create();
    Employee::factory()->for($department)->create(['name' => 'Siti Rahayu', 'nik' => '20230002']);
    Employee::factory()->for($department)->create(['name' => 'Budi Santoso', 'nik' => '20230001']);
    Employee::factory()->for($department)->create(['name' => 'Agus Nonaktif', 'is_active' => false]);
    Employee::factory()->create(['name' => 'Dewi Departemen Lain']);

    $response = $this->getJson(lookupUrl($department))->assertOk();

    expect($response->json())->toBe([
        ['id' => Employee::query()->where('nik', '20230001')->value('id'), 'nik' => '20230001', 'name' => 'Budi Santoso'],
        ['id' => Employee::query()->where('nik', '20230002')->value('id'), 'nik' => '20230002', 'name' => 'Siti Rahayu'],
    ]);
});

it('returns an empty list for an active department without active employees', function () {
    $this->getJson(lookupUrl(Department::factory()->create()))->assertOk()->assertExactJson([]);
});

it('returns 404 for an inactive or unknown department', function () {
    $inactive = Department::factory()->create(['is_active' => false]);
    Employee::factory()->for($inactive)->create();

    $this->getJson(lookupUrl($inactive))->assertNotFound();
    $this->getJson(lookupUrl(999_999))->assertNotFound();
});

it('is public and does not require a login', function () {
    $this->get(lookupUrl(Department::factory()->create()))->assertOk();
});

it('allows 60 requests per minute per IP, then 429', function () {
    $url = lookupUrl(Department::factory()->create());

    foreach (range(1, 60) as $_) {
        $this->getJson($url)->assertOk();
    }

    $this->getJson($url)->assertTooManyRequests();

    // IP lain punya kuota sendiri (HP di Wi-Fi pabrik lain / data seluler).
    $this->withServerVariables(['REMOTE_ADDR' => '10.9.9.9'])->getJson($url)->assertOk();
});

describe('trusted proxies', function () {
    beforeEach(function () {
        Route::get('/__test/ip', fn (Request $request) => $request->ip());
    });

    it('ignores X-Forwarded-For when TRUSTED_PROXIES is empty', function () {
        config(['app.trusted_proxies' => null]);

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->withHeader('X-Forwarded-For', '203.0.113.5')
            ->get('/__test/ip')
            ->assertSeeText('10.0.0.1');
    });

    it('uses the forwarded client IP from a listed proxy', function () {
        config(['app.trusted_proxies' => '10.0.0.1, 10.0.0.2']);

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->withHeader('X-Forwarded-For', '203.0.113.5')
            ->get('/__test/ip')
            ->assertSeeText('203.0.113.5');
    });

    it('does not trust a proxy that is not listed', function () {
        config(['app.trusted_proxies' => '10.0.0.2']);

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->withHeader('X-Forwarded-For', '203.0.113.5')
            ->get('/__test/ip')
            ->assertSeeText('10.0.0.1');
    });
});
