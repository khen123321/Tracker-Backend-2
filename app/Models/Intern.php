<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Intern extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'school_id',
        'branch_id',
        'department_id',
        'course',
        'batch',
        'required_hours',
        'rendered_hours',
        'date_started',
        
        // ✨ NEWLY ADDED FIELDS FOR FILE UPLOADS ✨
        'avatar_url', 
        'has_resume',
        
        'has_moa',
        'has_endorsement',
        'has_pledge',
        'has_nda',
        'profile_photo_uploaded',
        'id_card_status',
        'certificate_status',
        'status',
        'emergency_name',
        'emergency_number',
        'emergency_address',
    ];

    /**
     * Link back to the main User account
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    // This allows $intern->attendance_logs
    public function attendance_logs()
    {
        return $this->hasMany(AttendanceLog::class);
    }
}