<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterviewApproval extends Model
{
    use HasFactory;

    protected $fillable = [
        'candidate_id',
        'actor_id',
        'assigned_to_user_id',
        'stage',
        'action',
        'interview_date',
        'assigned_date',
        'comments',
        'status',
    ];

    protected $casts = [
        'interview_date' => 'date',
        'assigned_date'  => 'date',
    ];

    /**
     * Get the candidate associated with this approval record.
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    /**
     * Get the user who performed this action.
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Get the user this candidate was assigned/delegated to.
     */
    public function assignedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }
}
