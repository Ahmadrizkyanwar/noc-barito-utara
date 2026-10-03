<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `tickets` + `ticket_activities` — layanan ticketing laporan gangguan.
 *
 * Tiket bisa dibuat TANPA login (publik, kolom reporter_* diisi) atau dari
 * User Dashboard (user_id terisi). Aktivitas = riwayat status/catatan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('code', 24)->unique(); // TKT-20261003-0001
            $table->string('title', 200);
            $table->string('category', 50)->default('Jaringan');
            $table->text('description');
            $table->string('location', 200)->nullable();
            $table->string('photo', 255)->nullable();

            $table->string('status', 16)->default('open'); // open|proses|selesai
            $table->string('priority', 16)->default('normal'); // low|normal|high

            // Pelapor: user login DAN/ATAU data publik
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reporter_name', 100)->nullable();
            $table->string('reporter_contact', 100)->nullable();

            // Penanggung jawab (admin)
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();
            $table->index('status');
            $table->index('created_at');
        });

        Schema::create('ticket_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 32); // created|status|note|assign
            $table->string('old_value', 50)->nullable();
            $table->string('new_value', 50)->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_activities');
        Schema::dropIfExists('tickets');
    }
};
