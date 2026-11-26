<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Nomor pengajuan LBR-YYYYMMDD-NNNN (tanggal = tanggal dibuat, NNNN urut per hari).
 *
 * Wajib dipanggil di dalam transaksi yang sama dengan penyimpanan pengajuan:
 * baris penghitung tetap terkunci sampai transaksi selesai, dan nomor ikut batal bila penyimpanan gagal.
 * Pemanggil memakai DB::transaction(..., attempts: 3) untuk mengulang bila InnoDB tetap melaporkan deadlock.
 */
class OvertimeNumberGenerator
{
    public function next(CarbonImmutable $now): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('OvertimeNumberGenerator::next() harus dipanggil di dalam DB::transaction().');
        }

        $date = $now->toDateString();

        // Satu pernyataan atomik (MySQL: ON DUPLICATE KEY UPDATE, SQLite: ON CONFLICT DO UPDATE)
        // yang langsung mengambil exclusive lock pada baris tanggal ini. Pola INSERT IGNORE lalu
        // SELECT ... FOR UPDATE terbukti deadlock di InnoDB (shared lock yang di-upgrade).
        DB::table('overtime_number_counters')->upsert(
            [['date' => $date, 'last_number' => 1]],
            ['date'],
            ['last_number' => DB::raw('last_number + 1')],
        );

        $next = (int) DB::table('overtime_number_counters')->where('date', $date)->value('last_number');

        return sprintf('LBR-%s-%04d', $now->format('Ymd'), $next);
    }
}
