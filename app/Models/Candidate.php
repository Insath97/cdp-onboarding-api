<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Candidate extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'nic_number',
        'phone_number',
        'secondary_number',
        'designation_id',
        'expected_salary',
        'cv_path',
        'status',
        'uploaded_by',
        'uploader_comments',
        'submitted_at',
        'channel_head_employee_id',
        'stage_updated_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expected_salary' => 'decimal:2',
            'submitted_at' => 'datetime',
            'stage_updated_at' => 'datetime',
        ];
    }

    /**
     * Appends custom accessors to array/JSON representation.
     */
    protected $appends = ['full_name', 'cv_url'];

    /**
     * Get full name accessor.
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Get accessible public URL for the uploaded CV.
     */
    public function getCvUrlAttribute(): ?string
    {
        if (! $this->cv_path) {
            return null;
        }

        return asset($this->cv_path);
    }

    /**
     * Get the designation the candidate applied for.
     */
    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    /**
     * Get the staff user who uploaded/submitted the CV.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the snapshotted Channel Head employee at submission time.
     */
    public function channelHead(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'channel_head_employee_id');
    }

    /**
     * Scope a query to search across first_name, last_name, nic_number, and phone_number.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('first_name', 'like', "%{$search}%")
              ->orWhere('last_name', 'like', "%{$search}%")
              ->orWhere('nic_number', 'like', "%{$search}%")
              ->orWhere('phone_number', 'like', "%{$search}%");
        });
    }

    /**
     * Scope a query to filter by candidate status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
