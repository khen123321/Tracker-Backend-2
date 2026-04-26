<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InternRequest extends Model
{
    use HasFactory;

    // Specifies which columns can be mass-assigned
    protected $fillable = [
        'user_id', 
        'type', 
        'date_of_absence', 
        'reason', 
        'additional_details', 
        'attachment_path', 
        'status'
    ];

    // ✨ Links the request to the specific Intern (User)
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}