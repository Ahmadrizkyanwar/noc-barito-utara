<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `lat`/`lng` — titik koordinat laporan (opsional).
 *
 * Diisi pelapor lewat "share lokasi" (GPS) atau klik peta di form lapor.
 * Keduanya selalu berpasangan: isi satu wajib mengisi yang lain (validasi
 * `required_with`), atau keduanya kosong.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->decimal('lat', 10, 7)->nullable()->after('location');
            $table->decimal('lng', 10, 7)->nullable()->after('lat');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['lat', 'lng']);
        });
    }
};
