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
        Schema::table('device_location_assigns', function (Blueprint $table) {
            $table->enum('is_type', ['check_in', 'check_out'])
            ->nullable()
            ->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_location_assigns', function (Blueprint $table) {
            $table->dropColumn('is_type');
        });
    }
};
