<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeHierarchyHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'reporting_manager_id',
        'effective_from',
        'effective_to',
        'reason',
        'changed_by',
    ];

    protected $casts = [
        'effective_from' => 'date:Y-m-d',
        'effective_to'   => 'date:Y-m-d',
    ];

    /**
     * The employee whose hierarchy history is being tracked.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * The reporting manager during this timeframe.
     */
    public function reportingManager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reporting_manager_id');
    }

    /**
     * The user who updated/changed the reporting hierarchy.
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Scope: Active history records (where effective_to IS NULL).
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('effective_to');
    }

    /**
     * Scope: Historical record active as of a specific date.
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
