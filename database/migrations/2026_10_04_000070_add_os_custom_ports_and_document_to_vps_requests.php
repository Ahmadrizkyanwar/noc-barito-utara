<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vps_requests', function (Blueprint $table) {
            $table->string('os', 50)->nullable()->after('public_ips');
            $table->string('custom_ports', 255)->nullable()->after('ports');
            $table->string('supporting_document', 255)->nullable()->after('purpose');
            $table->timestamp('supporting_document_uploaded_at')->nullable()->after('supporting_document');
        });
    }

    public function down(): void
    {
        Schema::table('vps_requests', function (Blueprint $table) {
            $table->dropColumn([
                'os',
                'custom_ports',
                'supporting_document',
                'supporting_document_uploaded_at',
            ]);
        });
    }
};
