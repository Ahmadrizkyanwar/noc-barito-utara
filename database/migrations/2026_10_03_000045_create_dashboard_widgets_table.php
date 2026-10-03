<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `dashboard_widgets` — pilihan infografik yang tampil di Dashboard admin.
 *
 * Baris TETAP (di-seed, bukan CRUD bebas) — admin hanya mengedit `enabled`
 * lewat modal "Sesuaikan Widget" di halaman dashboard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_widgets', function (Blueprint $table) {
            $table->string('id', 32)->primary();
            $table->string('label', 100);
            $table->string('description', 255)->nullable();
            $table->boolean('enabled')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_widgets');
    }
};
