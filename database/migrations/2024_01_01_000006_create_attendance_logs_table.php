<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            // --- CHANGED THIS FROM intern_id TO user_id ---
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('date');

            // --- TIME IN ---
            $table->timestamp('time_in')->nullable();
            $table->timestamp('time_in_official')->nullable();
            $table->string('time_in_selfie')->nullable();
            $table->decimal('time_in_lat', 10, 7)->nullable();
            $table->decimal('time_in_lng', 10, 7)->nullable();
            $table->boolean('time_in_selfie_approved')->nullable();

            // --- LUNCH OUT ---
            $table->timestamp('lunch_out')->nullable();
            $table->string('lunch_out_selfie')->nullable();
            $table->decimal('lunch_out_lat', 10, 7)->nullable();
            $table->decimal('lunch_out_lng', 10, 7)->nullable();
            $table->boolean('lunch_out_selfie_approved')->nullable();

            // --- LUNCH IN ---
            $table->timestamp('lunch_in')->nullable();
            $table->string('lunch_in_selfie')->nullable();
            $table->decimal('lunch_in_lat', 10, 7)->nullable();
            $table->decimal('lunch_in_lng', 10, 7)->nullable();
            $table->boolean('lunch_in_selfie_approved')->nullable();

            // --- TIME OUT ---
            $table->timestamp('time_out')->nullable();
            $table->string('time_out_selfie')->nullable();
            $table->decimal('time_out_lat', 10, 7)->nullable();
            $table->decimal('time_out_lng', 10, 7)->nullable();
            $table->boolean('time_out_selfie_approved')->nullable();

            // --- Computed / Derived ---
            $table->decimal('hours_rendered', 5, 2)->default(0.00);

            $table->enum('status', [
                'present',
                'late',
                'absent',
                'incomplete',
                'half_day',
                'overtime'
            ])->default('incomplete');

            $table->boolean('is_late')->default(false);
            $table->boolean('is_flagged')->default(false);

            // Half-day details
            $table->enum('half_day_type', ['am', 'pm'])->nullable();

            // Overtime
            $table->decimal('overtime_hours', 5, 2)->default(0.00);

            $table->text('notes')->nullable();

            // --- CHANGED THIS TO user_id ---
            $table->unique(['user_id', 'date']); 

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
    }
};