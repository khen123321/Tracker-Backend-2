<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('first_name');
    $table->string('middle_name')->nullable();
    $table->string('last_name');
    $table->string('email')->unique();
    $table->string('password');
    
    // --- ADD THESE TWO LINES ---
    $table->string('role')->default('intern'); 
    $table->string('status')->default('active'); 
    // ---------------------------

    $table->string('emergency_contact_name')->nullable();
    $table->string('emergency_contact_phone')->nullable();
    $table->string('emergency_contact_address')->nullable();
    $table->string('emergency_relationship')->nullable();
    $table->string('course_program')->nullable();
    $table->string('school_university')->nullable();
    $table->string('assigned_branch')->nullable();
    $table->string('assigned_department')->nullable();
    $table->date('date_started')->nullable();
    $table->boolean('has_moa')->default(false);
    $table->boolean('has_endorsement')->default(false);
    $table->boolean('has_pledge')->default(false);
    $table->boolean('has_nda')->default(false);
    $table->rememberToken();
    $table->timestamps();
});
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
