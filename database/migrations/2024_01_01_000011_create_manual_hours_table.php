<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Used when HR manually adjusts an intern's rendered hours
        // (e.g. Midterm Internship Report, credited seminar, school event)
        // Based on Section 3.8: Required Hours Adjustment
        Schema::create('manual_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intern_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->decimal('hours', 5, 2); // Can be positive (add) or negative (deduct)
            $table->text('reason');          // Required — logged in audit trail
            $table->foreignId('added_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_hours');
    }
};
