<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'group_id',
        'address_line1',
        'address_line2',
        'city',
        'postal_code',
        'zone_id',
        'region_id',
        'province_id',
        'phone_primary',
        'phone_secondary',
        'email',
        'fax',
        'opening_date',
        'branch_type',
        'latitude',
        'longitude',
        'is_active',
        'is_head_office',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_head_office' => 'boolean',
        'opening_date' => 'date',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    /**
     * Scope a query to only include active branches.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to search branches by name, code, or city.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('code', 'like', "%{$search}%")
              ->orWhere('city', 'like', "%{$search}%");
        });
    }

    /**
     * Get the group that owns the branch.
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Get the province associated with the branch.
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /**
     * Get the zonal associated with the branch.
     */
    public function zonal(): BelongsTo
    {
        return $this->belongsTo(Zonal::class, 'zone_id');
    }

    /**
     * Get the region associated with the branch.
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * Get the employees working in this branch.
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
