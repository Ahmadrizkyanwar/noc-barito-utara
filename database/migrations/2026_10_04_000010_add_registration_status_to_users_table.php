<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registrasi user publik + validasi admin/operator.
 *
 * - `status` registrasi: pending|approved|rejected.
 *   Default 'approved' agar user lama (admin/bawaan) tidak terkunci;
 *   controller registrasi SELALU menyetel 'pending' untuk akun baru.
 * - Data identitas instansi (NIP/jabatan/instansi) diisi pada FORM REQUEST VPS,
 *   bukan di tabel users — lihat migration vps_requests.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status', 16)->default('approved')->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
