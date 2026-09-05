<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelHeadHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'channel_wise_employment_id',
        'employee_id',
        'effective_from',
        'effective_to',
        'remarks',
        'changed_by',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to'   => 'date',
    ];

    /**
     * The channel this record belongs to.
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(ChannelWiseEmployment::class, 'channel_wise_employment_id');
    }

    /**
     * The employee who is/was the Channel Head.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->with('user');
    }

    /**
     * The admin user who made this change.
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Scope: Only the currently active channel head records (effective_to IS NULL).
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('effective_to');
    }

    /**
     * Scope: Records that were active on a specific date (point-in-time query).
     */
    public function scopeAsOf(Builder $query, string $date): Builder
    {
        return $query->where('effective_from', '<=', $date)
                     ->where(function (Builder $q) use ($date) {
                         $q->whereNull('effective_to')
                           ->orWhere('effective_to', '>=', $date);
                     });
    }
}
