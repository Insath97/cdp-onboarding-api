<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'f_name',
        'l_name',
        'full_name',
        'name_with_initials',
        'employee_code',
        'reporting_manager_id',
        'province_id',
        'region_id',
        'zonal_id',
        'branch_id',
        'department_id',
        'designation_id',
        'employee_type',
        'id_type',
        'id_number',
        'date_of_birth',
        'email',
        'phone',
        'address_line_1',
        'city',
        'state',
        'country',
        'postal_code',
        'phone_primary',
        'phone_secondary',
        'have_whatsapp',
        'whatsapp_number',
        'start_date',
        'end_date',
        'joined_at',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'have_whatsapp' => 'boolean',
        'date_of_birth' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
        'joined_at' => 'datetime',
    ];

    /**
     * Scope a query to only include active employees.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to search employees by name, email, or employee code.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search) {
            $q->where('f_name', 'like', "%{$search}%")
              ->orWhere('l_name', 'like', "%{$search}%")
              ->orWhere('full_name', 'like', "%{$search}%")
              ->orWhere('employee_code', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%");
        });
    }

    /**
     * Get the user account associated with the employee.
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    /**
     * Get the reporting manager of the employee.
     */
    public function reportingManager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reporting_manager_id');
    }

    /**
     * Get the subordinates reporting to this employee.
     */
    public function subordinates(): HasMany
    {
        return $this->hasMany(Employee::class, 'reporting_manager_id');
    }

    /**
     * Get the province associated with the employee.
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /**
     * Get the region associated with the employee.
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * Get the zonal associated with the employee.
     */
    public function zonal(): BelongsTo
    {
        return $this->belongsTo(Zonal::class, 'zonal_id');
    }

    /**
     * Get the branch where the employee works.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the department where the employee works.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the designation of the employee.
     */
    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    /**
     * Get the channel wise employment associated with the employee.
     * REMOVED: channel_wise_employment_id no longer exists on employees table.
     * Channel membership is now determined by walking up the reporting_manager chain
     * to find a Channel Head in channel_head_histories.
     * Use resolveChannel() or resolveChannelAsOf($date) instead.
     */

    /**
     * Walk up the reporting manager chain to find this employee's current channel.
     * Returns the active ChannelHeadHistory record, or null if no channel is found.
     */
    public function resolveChannel(): ?\App\Models\ChannelHeadHistory
    {
        $current = $this;
        $visited = [];

        while ($current) {
            if (in_array($current->id, $visited)) break;
            $visited[] = $current->id;

            $headRecord = \App\Models\ChannelHeadHistory::where('employee_id', $current->id)
                ->whereNull('effective_to')
                ->with('channel')
                ->first();

            if ($headRecord) return $headRecord;

            $current = $current->reportingManager;
        }

        return null;
    }

    /**
     * Walk up the reporting manager chain to find this employee's channel AS OF a specific date.
     * Used when resolving the Channel Head for a candidate's submission date.
     */
    public function resolveChannelAsOf(string $date): ?\App\Models\ChannelHeadHistory
    {
        $current = $this;
        $visited = [];

        while ($current) {
            if (in_array($current->id, $visited)) break;
            $visited[] = $current->id;

            $headRecord = \App\Models\ChannelHeadHistory::where('employee_id', $current->id)
                ->where('effective_from', '<=', $date)
                ->where(function ($q) use ($date) {
                    $q->whereNull('effective_to')
                      ->orWhere('effective_to', '>=', $date);
                })
                ->with('channel')
                ->first();

            if ($headRecord) return $headRecord;

            $current = $current->reportingManager;
        }

        return null;
    }

    /**
     * Get the historical reporting manager changes for this employee.
     */
    public function hierarchyHistories(): HasMany
    {
        return $this->hasMany(EmployeeHierarchyHistory::class, 'employee_id');
    }

    /**
     * Resolve reporting manager as of a specific date.
     */
    public function resolveReportingManagerAsOf(string $date): ?Employee
    {
        $history = EmployeeHierarchyHistory::where('employee_id', $this->id)
            ->asOf($date)
            ->with('reportingManager')
            ->first();

        return $history ? $history->reportingManager : null;
    }

    /**
     * Resolve direct subordinates as of a specific date.
     */
    public function resolveSubordinatesAsOf(string $date)
    {
        $histories = EmployeeHierarchyHistory::where('reporting_manager_id', $this->id)
            ->asOf($date)
            ->with('employee')
            ->get();

        return $histories->map(fn ($h) => $h->employee)->filter();
    }

    /**
     * Get all user IDs associated with subordinate employees (recursive).
     */
    public function getAllDescendantUserIds(): array
    {
        $userIds = [];
        $subordinates = Employee::where('reporting_manager_id', $this->id)->get();

        foreach ($subordinates as $subordinate) {
            $subUser = User::where('employee_id', $subordinate->id)->first();
            if ($subUser) {
                $userIds[] = $subUser->id;
                $userIds = array_merge($userIds, $subUser->getAllDescendantIds());
            } else {
                $userIds = array_merge($userIds, $subordinate->getAllDescendantUserIds());
            }
        }

        return $userIds;
    }
}
