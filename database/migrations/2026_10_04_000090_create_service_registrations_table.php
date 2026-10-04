<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `service_registrations` — pendaftaran Domain & Hosting dari user terdaftar.
 *
 * Satu tabel untuk dua jenis (`type` = domain|hosting), mengikuti pola
 * `vps_requests`: kode unik harian, review admin/operator, dokumen pendukung.
 *
 * Kolom khusus tipe:
 *   - domain_name     : domain (wajib utk domain, opsional utk hosting)
 *   - hosting_package : key dari config('noc.service_registrations.hosting.packages')
 *   - duration        : TAHUN untuk domain, BULAN untuk hosting
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('type', 16); // domain | hosting
            $table->string('code', 24)->nullable()->unique();

            // Identitas pemohon (diisi di form, bukan dari profil)
            $table->string('name', 100);
            $table->string('nip', 30);
            $table->string('jabatan', 100);
            $table->string('instansi', 150);

            // Spesifikasi (nullable — relevan per tipe)
            $table->string('domain_name', 255)->nullable();
            $table->string('hosting_package', 64)->nullable();
            $table->unsignedSmallInteger('duration')->nullable();
            $table->text('purpose');

            // Dokumen pendukung
            $table->string('supporting_document', 255)->nullable();
            $table->timestamp('supporting_document_uploaded_at')->nullable();

            // Review admin/operator
            $table->string('status', 16)->default('pending');
            $table->text('admin_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();
            $table->index('status');
            $table->index('created_at');
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_registrations');
    }
};
