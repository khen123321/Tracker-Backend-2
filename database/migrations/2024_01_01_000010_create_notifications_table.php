<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // In-app notifications for both interns and HR
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('message');

            // Notification types based on system spec
            $table->enum('type', [
                'clock_reminder',       // Clock-in/out reminders
                'form_approved',        // Form status: approved
                'form_rejected',        // Form status: rejected
                'new_announcement',     // New announcement posted
                'hour_milestone',       // 75%, 90%, 100% completion
                'incomplete_entry',     // HR notified of incomplete log at day end
                'new_intern',           // HR notified of new intern registration
                'general'
            ])->default('general');

            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();

            // Optional: link to the related record
            $table->string('related_type')->nullable();  // e.g. 'AbsentReport', 'AttendanceLog'
            $table->unsignedBigInteger('related_id')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
