<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // System Audit Log — based on Section 6.9 (Settings > View Audit Log)
        // Every edit, approval, form action, and login is recorded here with full timestamps
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('action');                  // e.g. "updated_required_hours", "approved_absent_report"
            $table->string('model_type')->nullable();  // e.g. "Intern", "AttendanceLog"
            $table->unsignedBigInteger('model_id')->nullable();
            $table->json('old_values')->nullable();    // What it was before
            $table->json('new_values')->nullable();    // What it changed to
            $table->string('ip_address')->nullable();
            $table->text('notes')->nullable();         // e.g. reason provided by HR when editing required hours
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
