<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AttendanceLog extends Model
{
    use HasFactory;

    protected $table = 'attendance_logs';

    // ✨ FIXED: Updated to match your ACTUAL database columns!
    protected $fillable = [
        'intern_id',
        'date',
        'time_in',
        'lunch_out',
        'lunch_in',
        'time_out',
        'hours_rendered',
        'status',
        
        // Image & Status Columns
        'image_in', 'am_in_status', 'am_in_attempts',
        'lunch_out_selfie', 'lunch_out_status', 'lunch_out_attempts',
        'lunch_in_selfie', 'lunch_in_status', 'lunch_in_attempts',
        'image_out', 'pm_out_status', 'pm_out_attempts',
        
        // HR Warnings
        'is_flagged', 'notes',

        // New Appeal Columns
        'appeal_text',
        'appeal_file_path',
        'appeal_status',
        'appeal_rejection_reason',
        'appeal_submitted_at',
        'appeal_responded_at',
    ];

    protected $casts = [
        'date' => 'date',
        'time_in' => 'datetime',
        'lunch_out' => 'datetime',
        'lunch_in' => 'datetime',
        'time_out' => 'datetime',
        'appeal_submitted_at' => 'datetime',
        'appeal_responded_at' => 'datetime',
    ];

    /**
     * ✨ FIXED: Added the missing Intern relationship that caused the 500 Error!
     */
    public function intern()
    {
        return $this->belongsTo(Intern::class, 'intern_id');
    }

    /**
     * Check if this log has a pending appeal.
     */
    public function hasPendingAppeal()
    {
        return $this->appeal_status === 'pending';
    }

    /**
     * Check if this log can be appealed.
     */
    public function canBeAppealed()
    {
        // Can only be appealed if it hasn't been appealed yet
        return is_null($this->appeal_status);
    }

    /**
     * Submit an appeal for this attendance log.
     */
    public function submitAppeal($appealText, $appealFilePath = null)
    {
        $this->update([
            'appeal_text' => $appealText,
            'appeal_file_path' => $appealFilePath,
            'appeal_status' => 'pending',
            'appeal_submitted_at' => now(),
        ]);

        return $this;
    }

    /**
     * Approve an appeal.
     */
    public function approveAppeal()
    {
        $this->update([
            'appeal_status' => 'approved',
            'appeal_responded_at' => now(),
        ]);

        return $this;
    }

    /**
     * Reject an appeal with a reason.
     */
    public function rejectAppeal($reason)
    {
        $this->update([
            'appeal_status' => 'rejected',
            'appeal_rejection_reason' => $reason,
            'appeal_responded_at' => now(),
        ]);

        return $this;
    }
}