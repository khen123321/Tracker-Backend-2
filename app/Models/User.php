<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     * Added 'permissions' to allow the Super Admin to save access keys.
     */
    protected $fillable = [
        'first_name', 
        'middle_name', 
        'last_name', 
        'email', 
        'password', 
        'role', 
        'status',
        'permissions', // <--- IMPORTANT: Added for RBAC
        'emergency_contact_name', 
        'emergency_contact_phone', 
        'emergency_contact_address', 
        'emergency_relationship',
        'course_program', 
        'school_university', 
        'assigned_branch', 
        'assigned_department', 
        'date_started',
        'has_moa', 
        'has_endorsement', 
        'has_pledge', 
        'has_nda'
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password', 
        'remember_token'
    ];

    /**
     * The attributes that should be cast.
     * This converts the JSON database column into a usable PHP/React array automatically.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'permissions' => 'array', // <--- IMPORTANT: Turns JSON into a list
        'has_moa' => 'boolean',
        'has_endorsement' => 'boolean',
        'has_pledge' => 'boolean',
        'has_nda' => 'boolean',
    ];

    /**
     * Relationship to the Intern profile.
     */
    public function intern()
    {
        return $this->hasOne(Intern::class, 'user_id', 'id');
    }

    /**
     * Relationship to Attendance Logs (Bridged through Intern profile).
     * This allows $user->attendance_logs to work directly.
     */
    public function attendance_logs()
    {
        return $this->hasManyThrough(
            \App\Models\AttendanceLog::class, 
            \App\Models\Intern::class,
            'user_id',    // Foreign key on the interns table
            'intern_id',  // Foreign key on the attendance_logs table
            'id',         // Local key on the users table
            'id'          // Local key on the interns table
        );
    }
}