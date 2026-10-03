<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `devices` — perangkat jaringan yang dipantau (router/switch/server/website).
 *
 * Tiga metode probe bisa dikombinasikan per perangkat (flag use_*):
 *   - ICMP   → keterjangkauan + RTT (binary `ping`)
 *   - SNMP   → CPU/RAM/uptime/trafik (ekstensi PHP `snmp`)
 *   - RouterOS API → resource + interface (paket evilfreelancer/routeros-api-php)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('host', 255);
            $table->string('type', 32)->default('router'); // router|switch|server|website|lainnya
            $table->string('location', 150)->nullable();
            $table->boolean('enabled')->default(true);

            // ── flag metode ──
            $table->boolean('use_icmp')->default(true);
            $table->boolean('use_snmp')->default(false);
            $table->boolean('use_routeros')->default(false);

            // ── kredensial SNMP (kosong = pakai config/noc.php) ──
            $table->string('snmp_version', 4)->nullable();
            $table->string('snmp_community', 128)->nullable();
            $table->integer('snmp_port')->nullable();

            // ── kredensial RouterOS API ──
            $table->integer('routeros_port')->default(8728);
            $table->string('routeros_user', 64)->default('admin');
            $table->string('routeros_password')->default('');
            $table->integer('routeros_timeout')->default(5);

            // ── status terakhir (denormalisasi untuk daftar cepat) ──
            $table->string('status', 16)->default('unknown'); // up|down|unknown
            $table->timestamp('last_checked_at')->nullable();
            $table->double('last_rtt_ms')->nullable();
            $table->integer('last_cpu')->nullable();
            $table->unsignedBigInteger('last_uptime_sec')->nullable();

            $table->timestamps();
            $table->index('enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
