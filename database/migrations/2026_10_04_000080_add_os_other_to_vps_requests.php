<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vps_requests', function (Blueprint $table) {
            $table->string('os_other', 100)->nullable()->after('os');
        });
    }

    public function down(): void
    {
        Schema::table('vps_requests', function (Blueprint $table) {
            $table->dropColumn('os_other');
        });
    }
};
