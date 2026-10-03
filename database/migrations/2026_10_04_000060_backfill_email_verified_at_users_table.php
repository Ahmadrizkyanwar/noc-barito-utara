<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Wajib verifikasi email sebelum login (fitur baru).
 *
 * User LAMA (admin/bawaan yang sudah ada sebelum fitur ini) ditandai sudah
 * diverifikasi agar tidak terkunci — verifikasi hanya berlaku untuk
 * registrasi BARU (email_verified_at = null).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // Tidak dibalik — data siapa yang diverifikasi saat migration tidak dicatat.
    }
};
