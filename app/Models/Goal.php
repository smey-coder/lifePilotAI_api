<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Goal extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'target_date',
        'progress_percentage',
        'status',
    ];

    protected $casts = [
        'target_date' => 'date',
        'progress_percentage' => 'integer',
    ];

    /**
     * Relationship: A Goal belongs to a User.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: A Goal has many Milestones.
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(GoalMilestone::class);
    }

    /**
     * Helper method to auto-recalculate and update progress_percentage based on milestones.
     */
    public function recalculateProgress(): void
    {
        $totalMilestones = $this->milestones()->count();

        if ($totalMilestones === 0) {
            $percentage = 0;
        } else {
            $completedMilestones = $this->milestones()->where('is_completed', true)->count();
            $percentage = (int) round(($completedMilestones / $totalMilestones) * 100);
        }

        $status = $this->status;
        if ($percentage === 100) {
            $status = 'completed';
        } elseif ($status === 'completed' && $percentage < 100) {
            $status = 'on_track';
        }

        $this->update([
            'progress_percentage' => $percentage,
            'status' => $status,
        ]);
    }
}