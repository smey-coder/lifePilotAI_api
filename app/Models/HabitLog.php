<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HabitLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'habit_id',
        'completed_date',
    ];

    protected $casts = [
        'completed_date' => 'date',
    ];

    public function habit()
    {
        return $this->belongsTo(Habit::class);
    }
}