<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // 👈 Crucial for your API login!

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

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
        'permissions'    // ✨ ADDED: Prevents crashes when updating HR access
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
        'permissions' => 'array', 
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
        // If your 'interns' table connects to the user using 'user_id', leave this. 
        // If it uses 'intern_id', change the second parameter!
        return $this->hasOne(Intern::class, 'user_id'); 
    }

    public function attendance_logs()
    {
        // We tell Laravel specifically to look for 'intern_id' instead of 'user_id'
        return $this->hasMany(AttendanceLog::class, 'intern_id');
    }

    // ✨ NEW: Tells Laravel this user can have many form requests ✨
    public function internRequests()
    {
        return $this->hasMany(InternRequest::class, 'user_id');
    }
}