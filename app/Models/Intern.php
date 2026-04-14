<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Intern extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     * Every column from your interns table MUST be listed here.
     */
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
        'has_moa',
        'has_endorsement',
        'has_pledge',
        'has_nda',
        'profile_photo_uploaded',
        'id_card_status',
        'certificate_status',
        'status',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * Link back to the main User account
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Link to the Schools table
     */
    public function school()
    {
        return $this->belongsTo(School::class); // Make sure you have a School model!
    }

    /**
     * Link to the Branches table
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class); // Make sure you have a Branch model!
    }

    /**
     * Link to the Departments table
     */
    public function department()
    {
        return $this->belongsTo(Department::class); // Make sure you have a Department model!
    }
}