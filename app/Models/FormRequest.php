<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormRequest extends Model
{
    use HasFactory;

    protected $guarded = [];

    // Link this request to the intern
    public function intern()
    {
        return $this->belongsTo(Intern::class);
    }
}