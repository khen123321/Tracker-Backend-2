<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- ANNOUNCEMENTS ---
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->enum('type', ['event', 'holiday', 'reminder', 'general'])->default('general');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->date('expiry_date')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();
        });

        // --- ANNOUNCEMENT READ RECEIPTS ---
        // Tracks which interns have read each announcement
        Schema::create('announcement_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamp('read_at');
            $table->unique(['announcement_id', 'user_id']); // One read receipt per user per announcement
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_reads');
        Schema::dropIfExists('announcements');
    }
};
