<?php

declare(strict_types=1);

use App\Enums\DayType;
use App\Enums\OvertimeSource;
use App\Enums\OvertimeStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Durasi disimpan sebagai menit integer. Datetime disimpan sebagai waktu lokal Asia/Jakarta.
 * Tidak ada soft/hard delete untuk data lembur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtimes', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('employee_id')->constrained();
            // Snapshot departemen saat lembur; tidak ikut berubah bila karyawan pindah.
            $table->foreignId('department_id')->constrained();
            $table->date('overtime_date');
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->unsignedSmallInteger('break_minutes');
            $table->unsignedSmallInteger('total_minutes');
            $table->enum('day_type', DayType::values());
            $table->boolean('exceeds_daily_limit')->default(false);
            $table->boolean('exceeds_weekly_limit')->default(false);
            $table->string('description', 500);
            $table->enum('status', OvertimeStatus::values());
            $table->enum('source', OvertimeSource::values());
            $table->string('submitted_ip', 45)->nullable();
            $table->string('submitted_user_agent', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users');
            $table->dateTime('rejected_at')->nullable();
            $table->string('reject_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'start_at', 'end_at']);
            $table->index(['department_id', 'status', 'overtime_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtimes');
    }
};
