<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    // This array tells Laravel exactly which database columns we are allowed to write to.
    protected $fillable = [
        'user_id',
        'title',
        'message',
        'type',
        'read_at',
        'data'
    ];
}