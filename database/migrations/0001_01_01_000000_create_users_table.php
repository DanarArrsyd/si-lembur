<?php

declare(strict_types=1);

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('email')->nullable();
            $table->string('password');
            $table->enum('role', UserRole::values());
            // Tidak memakai cascade/set null: MySQL melarang kolom generated di bawah
            // bergantung pada kolom FK yang punya aksi referensial.
            $table->foreignId('department_id')->nullable()->constrained();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            // Penegak "1 departemen maksimal 1 SUPERVISOR aktif" di level DB.
            // Bernilai department_id hanya untuk supervisor aktif, selain itu NULL;
            // UNIQUE mengabaikan NULL sehingga admin dan akun nonaktif tidak terhitung.
            $table->unsignedBigInteger('active_supervisor_department_id')
                ->nullable()
                ->storedAs("CASE WHEN role = 'SUPERVISOR' AND is_active = 1 THEN department_id ELSE NULL END")
                ->unique();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('sessions');
    }
};
