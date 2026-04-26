<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interns', function (Blueprint $table) {
            // This tells MySQL to actually create the columns
            $table->string('avatar_url')->nullable();
            $table->boolean('has_resume')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('interns', function (Blueprint $table) {
            $table->dropColumn('avatar_url');
            $table->dropColumn('has_resume');
        });
    }
};