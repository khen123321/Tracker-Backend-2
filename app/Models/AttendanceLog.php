<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon; // ✨ Added Carbon to handle the date formatting

class AttendanceLog extends Model
{
    use HasFactory;

    // 👇 This allows our Controller to save these specific columns securely 👇
    protected $fillable = [
        'intern_id', 
        'date', 
        'time_in', 
        'lunch_out', 
        'lunch_in', 
        'time_out', 
        'hours_rendered', 
        'status', 
        'image_in', 
        'image_out', 
        'is_flagged', 
        'notes'
    ];

    // ✨ 1. Tell Laravel to automatically append this field when sending to React
    protected $appends = ['day_of_week'];

    // ✨ 2. The Accessor: Magically calculates the day of the week on the fly!
    public function getDayOfWeekAttribute()
    {
        if (!$this->date) return null;
        
        // This takes the '2026-04-26' date and turns it into 'Sun', 'Mon', etc.
        return Carbon::parse($this->date)->format('D'); 
    }

    // 👇 Defines the relationship so an Attendance Log belongs to an Intern 👇
    public function intern()
    {
        return $this->belongsTo(Intern::class, 'intern_id');
    }
}