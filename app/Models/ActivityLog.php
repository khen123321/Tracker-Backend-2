<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = ['user_id', 'action', 'description'];

    // Allows us to get the name of the HR who did the action
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}