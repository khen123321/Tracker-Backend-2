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
        Schema::table('attendance_logs', function (Blueprint $table) {
            // AM IN
            $table->string('am_in_status')->default('pending')->after('image_in');
            $table->integer('am_in_attempts')->default(0)->after('am_in_status');
            
            // LUNCH OUT
            $table->string('lunch_out_status')->default('pending')->after('lunch_out_selfie');
            $table->integer('lunch_out_attempts')->default(0)->after('lunch_out_status');
            
            // LUNCH IN
            $table->string('lunch_in_status')->default('pending')->after('lunch_in_selfie');
            $table->integer('lunch_in_attempts')->default(0)->after('lunch_in_status');
            
            // PM OUT
            $table->string('pm_out_status')->default('pending')->after('image_out');
            $table->integer('pm_out_attempts')->default(0)->after('pm_out_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropColumn([
                'am_in_status', 'am_in_attempts',
                'lunch_out_status', 'lunch_out_attempts',
                'lunch_in_status', 'lunch_in_attempts',
                'pm_out_status', 'pm_out_attempts'
            ]);
        });
    }
};