<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->text('appeal_text')->nullable()->comment('Appeal reason text');
            $table->string('appeal_file_path')->nullable()->comment('Path to appeal document/image');
            $table->enum('appeal_status', ['pending', 'approved', 'rejected'])->nullable()->default(null)->comment('Status of the appeal');
            $table->text('appeal_rejection_reason')->nullable()->comment('Reason for rejecting appeal');
            $table->timestamp('appeal_submitted_at')->nullable()->comment('When appeal was submitted');
            $table->timestamp('appeal_responded_at')->nullable()->comment('When HR responded to appeal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropColumn([
                'appeal_text',
                'appeal_file_path',
                'appeal_status',
                'appeal_rejection_reason',
                'appeal_submitted_at',
                'appeal_responded_at',
            ]);
        });
    }
};
