<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

protected $fillable = [
    'first_name', 'middle_name', 'last_name', 'email', 'password', 'role', 'status',
    'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_address', 'emergency_relationship',
    'course_program', 'school_university', 'assigned_branch', 'assigned_department', 'date_started',
    'has_moa', 'has_endorsement', 'has_pledge', 'has_nda'
];

    protected $hidden = ['password', 'remember_token'];
}