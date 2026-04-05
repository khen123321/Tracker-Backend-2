<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('school_id')->constrained()->onDelete('restrict');
            $table->foreignId('branch_id')->constrained()->onDelete('restrict');
            $table->foreignId('department_id')->constrained()->onDelete('restrict');

            // Academic info
            $table->string('course');
            $table->string('batch')->nullable(); // e.g. "2024-2025 1st Sem"

            // OJT Hours
            $table->integer('required_hours')->default(486);
            $table->decimal('rendered_hours', 8, 2)->default(0.00);
            $table->date('date_started')->nullable();

            // Documents checklist (from registration form)
            $table->boolean('has_moa')->default(false);
            $table->boolean('has_endorsement')->default(false);
            $table->boolean('has_pledge')->default(false);
            $table->boolean('has_nda')->default(false);

            // Profile photo status
            // Photo is uploaded after first login (not during registration)
            $table->boolean('profile_photo_uploaded')->default(false);

            // ID Card status
            $table->enum('id_card_status', ['pending', 'printed', 'distributed'])
                  ->default('pending');

            // Certificate of Completion status
            $table->enum('certificate_status', ['not_started', 'in_progress', 'completed', 'distributed'])
                  ->default('not_started');

            // Account status
            $table->enum('status', ['active', 'inactive', 'completed'])
                  ->default('active');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interns');
    }
};
