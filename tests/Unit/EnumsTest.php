<?php

declare(strict_types=1);

use App\Enums\DayType;
use App\Enums\OvertimeSource;
use App\Enums\OvertimeStatus;
use App\Enums\UserRole;

it('defines enum values matching the database enums', function (string $enum, array $values) {
    expect(array_column($enum::cases(), 'value'))->toBe($values);
})->with([
    [OvertimeStatus::class, ['PENDING', 'APPROVED', 'REJECTED']],
    [OvertimeSource::class, ['PUBLIC', 'SUPERVISOR']],
    [DayType::class, ['HARI_KERJA', 'HARI_LIBUR']],
    [UserRole::class, ['ADMIN', 'SUPERVISOR']],
]);

it('provides Indonesian UI labels', function (BackedEnum $case, string $label) {
    expect($case->label())->toBe($label);
})->with([
    [OvertimeStatus::PENDING, 'Menunggu'],
    [OvertimeStatus::APPROVED, 'Disetujui'],
    [OvertimeStatus::REJECTED, 'Ditolak'],
    [OvertimeSource::PUBLIC, 'Karyawan'],
    [OvertimeSource::SUPERVISOR, 'Supervisor'],
    [DayType::HARI_KERJA, 'Hari kerja'],
    [DayType::HARI_LIBUR, 'Hari libur'],
    [UserRole::ADMIN, 'Admin'],
    [UserRole::SUPERVISOR, 'Supervisor'],
]);

it('lists values for migrations', function () {
    expect(OvertimeStatus::values())->toBe(['PENDING', 'APPROVED', 'REJECTED']);
});
