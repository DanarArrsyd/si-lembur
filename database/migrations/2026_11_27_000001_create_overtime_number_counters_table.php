<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penghitung nomor pengajuan per hari (LBR-YYYYMMDD-NNNN). Satu baris per tanggal dibuat,
 * dikunci dengan SELECT ... FOR UPDATE agar nomor unik walau pengajuan masuk bersamaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_number_counters', function (Blueprint $table) {
            $table->date('date')->primary();
            $table->unsignedInteger('last_number')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_number_counters');
    }
};
