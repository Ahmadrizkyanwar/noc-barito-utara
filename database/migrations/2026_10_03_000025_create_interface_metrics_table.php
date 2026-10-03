<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `interface_metrics` — trafik PER INTERFACE tiap siklus poll.
 *
 * Satu baris = satu interface pada satu waktu poll (rx/tx dalam bit/s).
 * `rx_bytes`/`tx_bytes` = counter mentah (bisa dipakai ulang bila cache
 * delta hilang — mis. container restart).
 *
 * Retensi: INTERFACE_RETENTION_DAYS (default 7 hari) — lebih pendek dari
 * device_metrics karena baris per interface jauh lebih banyak.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interface_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();

            $table->timestamp('checked_at')->index();
            $table->string('if_name', 64);
            $table->string('oper_status', 16)->nullable(); // up|down|unknown
            $table->unsignedBigInteger('speed')->nullable(); // bit/s (ifSpeed / properti ROS)

            $table->double('rx_bps')->nullable(); // bit/s (delta)
            $table->double('tx_bps')->nullable();
            $table->unsignedBigInteger('rx_bytes')->nullable(); // counter mentah
            $table->unsignedBigInteger('tx_bytes')->nullable();

            $table->index(['device_id', 'checked_at']);
            $table->index(['device_id', 'if_name', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interface_metrics');
    }
};
