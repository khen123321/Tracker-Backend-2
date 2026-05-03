<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory;

    protected $fillable = ['school_id', 'name'];

    // This links the course back to the school
    public function school()
    {
        return $this->belongsTo(School::class);
    }
}