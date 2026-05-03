<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            // Links to the HR/Admin who performed the action
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); 
            $table->string('action'); // e.g., "Created User", "Deleted Intern"
            $table->text('description'); // e.g., "Created HR Intern account for Khen."
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
