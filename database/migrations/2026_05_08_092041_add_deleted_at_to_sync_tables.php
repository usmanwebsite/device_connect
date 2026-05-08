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
        Schema::table('visitor_types', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('paths', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('device_location_assigns', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visitor_types', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('paths', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('device_location_assigns', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
