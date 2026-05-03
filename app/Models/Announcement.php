<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Announcement extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'body',
        'type',
        'created_by',
        'expiry_date',
        'is_pinned',
    ];

    /**
     * The attributes that should be cast.
     * This ensures Laravel automatically converts these to the correct PHP types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_pinned' => 'boolean',
        'expiry_date' => 'date',
    ];

    /**
     * Relationship: The HR/Admin user who created the announcement.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship: The interns/users who have read this announcement.
     * This links to your 'announcement_reads' table.
     */
    public function readers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'announcement_reads')
                    ->withPivot('read_at')
                    ->withTimestamps(); // Optional: if your pivot table uses created_at/updated_at
    }
}