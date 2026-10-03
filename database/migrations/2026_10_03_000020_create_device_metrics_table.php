<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `device_metrics` — satu baris = satu siklus poll per perangkat.
 * Retensi: METRICS_RETENTION_DAYS (default 30 hari) via `metrics:prune`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();

            $table->timestamp('checked_at')->index();
            $table->string('status', 16)->default('unknown'); // up|down|unknown

            // ICMP
            $table->boolean('icmp_ok')->nullable();
            $table->double('icmp_rtt_ms')->nullable();
            $table->string('icmp_error', 255)->nullable();

            // SNMP
            $table->boolean('snmp_ok')->nullable();
            $table->integer('cpu')->nullable();
            $table->unsignedBigInteger('uptime_sec')->nullable();
            $table->string('board_name', 100)->nullable();
            $table->double('rx_bps')->nullable();
            $table->double('tx_bps')->nullable();
            $table->string('snmp_error', 255)->nullable();

            // RouterOS API
            $table->boolean('routeros_ok')->nullable();
            $table->integer('routeros_cpu')->nullable();
            $table->string('routeros_error', 255)->nullable();

            $table->index(['device_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_metrics');
    }
};
