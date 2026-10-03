<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * vps_requests:
 * - `code`              : kode unik VPS-YYYYMMDD-NNNN (berbeda dari TKT-)
 *                         → muncul di sistem ticketing (Layanan).
 * - `credential_file`   : dokumen kredensial (IP/root/dll) diunggah admin
 *                         SETELAH request disetujui.
 * - `credential_uploaded_at`
 *
 * Baris lama di-backfill agar semua request punya kode.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vps_requests', function (Blueprint $table) {
            $table->string('code', 24)->nullable()->after('id');
            $table->string('credential_file', 255)->nullable()->after('reviewed_at');
            $table->timestamp('credential_uploaded_at')->nullable()->after('credential_file');
        });

        // ── Backfill kode untuk baris lama (urut id, sekuens per hari) ──
        $rows = DB::table('vps_requests')->orderBy('id')->get(['id', 'created_at']);
        $seq = [];

        foreach ($rows as $row) {
            $day = Carbon::parse($row->created_at)->format('Ymd');
            $seq[$day] = ($seq[$day] ?? 0) + 1;

            DB::table('vps_requests')->where('id', $row->id)->update([
                'code' => 'VPS-'.$day.'-'.str_pad((string) $seq[$day], 4, '0', STR_PAD_LEFT),
            ]);
        }

        Schema::table('vps_requests', function (Blueprint $table) {
            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::table('vps_requests', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'credential_file', 'credential_uploaded_at']);
        });
    }
};
