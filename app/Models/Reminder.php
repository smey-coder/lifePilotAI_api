<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'remind_at',
        'frequency',
        'channel',
        'is_triggered',
    ];

    protected $casts = [
        'remind_at' => 'datetime',
        'is_triggered' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}