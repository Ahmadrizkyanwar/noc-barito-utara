<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `devices.hosts` — daftar IP/hostname perangkat (JSON array).
 *
 * Satu router bisa punya >1 IP (mis. IP manajemen + IP publik).
 * - IP PERTAMA = IP utama → tetap tersimpan di kolom `host`
 *   (dipakai SNMP/RouterOS/daftar/telusuri — kompatibel mundur).
 * - ICMP mengecek SEMUA IP: perangkat dianggap UP bila SATU SAJA merespons.
 * - Baris lama di-backfill dari kolom `host`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->json('hosts')->nullable()->after('host');
        });

        foreach (DB::table('devices')->get(['id', 'host']) as $device) {
            DB::table('devices')->where('id', $device->id)->update([
                'hosts' => json_encode([$device->host]),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn('hosts');
        });
    }
};
