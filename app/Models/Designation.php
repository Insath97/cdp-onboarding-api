<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Designation extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'department_id',
        'level',
        'order_weight',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order_weight' => 'integer',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Automatically calculate order weight based on level if not explicitly provided
        static::saving(function ($designation) {
            $weights = [
                'entry' => 10,
                'mid' => 20,
                'senior' => 30,
                'lead' => 40,
                'executive' => 50,
                'Manager' => 60,
                'Director' => 70,
            ];
            
            // Map weight if level exists in the array and order_weight is not set/default
            if (isset($weights[$designation->level]) && ($designation->order_weight === 0 || empty($designation->order_weight))) {
                $designation->order_weight = $weights[$designation->level];
            }
        });
    }

    /**
     * Scope a query to only include active designations.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to search designations by name or code.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('code', 'like', "%{$search}%");
        });
    }

    /**
     * Get the department associated with the designation.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the employees with this designation.
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
