<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- ABSENT REPORTS ---
        Schema::create('absent_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intern_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->enum('reason_type', ['sick', 'emergency', 'personal', 'other']);
            $table->text('description');
            $table->string('attachment_path')->nullable(); // e.g. medical certificate photo
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('hr_remarks')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        // --- HALF-DAY FORMS ---
        Schema::create('half_day_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intern_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->enum('type', ['am', 'pm']); // Which half of the day
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('hr_remarks')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        // --- OVERTIME FORMS ---
        Schema::create('overtime_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intern_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->decimal('expected_hours', 4, 2); // How many overtime hours expected
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('hr_remarks')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        // --- CORRECTION REQUESTS ---
        Schema::create('correction_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intern_id')->constrained()->onDelete('cascade');
            $table->foreignId('attendance_log_id')->constrained()->onDelete('cascade');
            $table->date('date'); // Denormalized for easier querying
            $table->text('description'); // What the intern says is wrong
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('hr_remarks')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correction_requests');
        Schema::dropIfExists('overtime_forms');
        Schema::dropIfExists('half_day_forms');
        Schema::dropIfExists('absent_reports');
    }
};
