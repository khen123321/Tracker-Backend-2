<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceLog extends Model
{
    use HasFactory;

    // We removed user_id and added intern_id!
    protected $fillable = [
        'intern_id', 
        'date', 
        'time_in', 
        'time_out', 
        'status'
    ];
}