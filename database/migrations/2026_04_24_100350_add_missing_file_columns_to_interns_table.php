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
        Schema::table('interns', function (Blueprint $table) {
            // Add your missing file columns here, for example:
            // $table->string('moa_file')->nullable();
            // $table->string('nda_file')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('interns', function (Blueprint $table) {
            // Drop the columns here if you rollback:
            // $table->dropColumn(['moa_file', 'nda_file']);
        });
    }
};