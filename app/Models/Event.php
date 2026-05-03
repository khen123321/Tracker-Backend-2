<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    // These must match the columns in your migration!
    protected $fillable = [
        'title',
        'date',
        'time',
        'description',
        'type',
        'created_by',
        'school',  
        'course',
        'audience',
        'is_pinned' // ✨ ADDED: Allows the pin status to be saved to the database
    ];

    /**
     * Relationship: Get the HR user who created the event.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}