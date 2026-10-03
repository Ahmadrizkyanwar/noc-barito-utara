<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `telegram_webhooks` — 2 entri TETAP per fungsi (bukan CRUD bebas):
 *   - `tiket`    : laporan gangguan baru
 *   - `jaringan` : transisi status perangkat up/down
 *
 * Baris di-seed; admin hanya mengedit token/chat id/toggle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_webhooks', function (Blueprint $table) {
            $table->string('id', 16)->primary();
            $table->string('label', 50);
            $table->string('description', 255)->nullable();
            $table->boolean('enabled')->default(false);
            $table->string('bot_token', 255)->default('');
            $table->string('chat_id', 64)->default('');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_webhooks');
    }
};
