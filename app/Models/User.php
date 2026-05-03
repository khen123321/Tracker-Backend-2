<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail; // ✨ 1. UNCOMMENTED THIS LINE!
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; 


// ✨ 2. ADDED "implements MustVerifyEmail" RIGHT HERE!
class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'role',          // e.g., 'intern', 'hr', 'superadmin'
        'status',        // e.g., 'active', 'inactive'
        'school_id',
        'branch_id',
        'department_id',
        'permissions',   // Prevents crashes when updating HR access
        'created_by',
        'email_verified_at'     // Required to track who created the account
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'permissions' => 'array', // CRITICAL: Tells Laravel to treat this as an array
    ];

    // ==========================================
    // CLIMBS SYSTEM RELATIONSHIPS
    // ==========================================

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function intern()
    {
        return $this->hasOne(Intern::class, 'user_id'); 
    }

    public function attendance_logs()
    {
        return $this->hasMany(AttendanceLog::class, 'intern_id');
    }

    public function internRequests()
    {
        return $this->hasMany(InternRequest::class, 'user_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}