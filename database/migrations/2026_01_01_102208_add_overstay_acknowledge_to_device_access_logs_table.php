<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('device_access_logs', function (Blueprint $table) {
            $table->boolean('overstay_acknowledge')->default(false)->after('acknowledge_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_access_logs', function (Blueprint $table) {
            $table->dropColumn('overstay_acknowledge');
        });
    }
};
