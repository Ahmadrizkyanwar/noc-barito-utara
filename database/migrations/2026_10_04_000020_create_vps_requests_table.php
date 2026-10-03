<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `vps_requests` — permintaan VPS dari user terdaftar (sudah disetujui admin).
 *
 * Status: pending|approved|rejected — direview admin/operator.
 * `ports` = array JSON pilihan service port (key dari config('noc.vps_ports')).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vps_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Identitas pelapor (diisi di form, bukan dari profil)
            $table->string('name', 100);
            $table->string('nip', 30);
            $table->string('jabatan', 100);
            $table->string('instansi', 150);

            // Spesifikasi
            $table->unsignedSmallInteger('cores');
            $table->unsignedSmallInteger('ram_gb');
            $table->unsignedTinyInteger('public_ips');
            $table->json('ports'); // ["22", "443", ...]
            $table->text('purpose'); // penggunaan untuk

            // Review admin/operator
            $table->string('status', 16)->default('pending');
            $table->text('admin_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vps_requests');
    }
};
