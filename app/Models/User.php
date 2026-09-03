<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements JWTSubject, MustVerifyEmail
{
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'user_type',
        'employee_id',
        'is_active',
        'can_login',
        'last_login_at',
        'last_login_ip',
        'email_verified_at',
        'email_verification_token',
        'email_verification_token_expires_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_verification_token_expires_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'can_login' => 'boolean',
        ];
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
        ];
    }

    /* Relationships */

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /* Accessors */

    public function getBranchAttribute()
    {
        return $this->employee?->branch;
    }

    public function getZoneAttribute()
    {
        return $this->employee?->zonal;
    }

    public function getRegionAttribute()
    {
        return $this->employee?->region;
    }

    public function getProvinceAttribute()
    {
        return $this->employee?->province;
    }

    public function getParentAttribute()
    {
        return $this->employee?->reportingManager?->user;
    }

    public function getChildrenAttribute()
    {
        if (!$this->employee_id) {
            return collect();
        }
        $subordinateIds = Employee::where('reporting_manager_id', $this->employee_id)->pluck('id');
        return User::whereIn('employee_id', $subordinateIds)->get();
    }

    public function toArray()
    {
        $array = parent::toArray();

        // If employee relationship is loaded, we can populate branch, zone, region, province
        if ($this->relationLoaded('employee') && $this->employee) {
            $array['branch'] = $this->employee->relationLoaded('branch') ? $this->employee->branch : null;
            $array['zone'] = $this->employee->relationLoaded('zonal') ? $this->employee->zonal : null;
            $array['region'] = $this->employee->relationLoaded('region') ? $this->employee->region : null;
            $array['province'] = $this->employee->relationLoaded('province') ? $this->employee->province : null;

            if ($this->employee->relationLoaded('reportingManager') && $this->employee->reportingManager) {
                if ($this->employee->reportingManager->relationLoaded('user') && $this->employee->reportingManager->user) {
                    $parentUser = $this->employee->reportingManager->user;
                    $array['parent'] = [
                        'id' => $parentUser->id,
                        'name' => $parentUser->name,
                        'username' => $parentUser->username,
                        'email' => $parentUser->email,
                        'user_type' => $parentUser->user_type,
                        'is_active' => $parentUser->is_active,
                        'can_login' => $parentUser->can_login,
                    ];
                } else {
                    $array['parent'] = null;
                }
            } else {
                $array['parent'] = null;
            }

            if ($this->employee->relationLoaded('subordinates')) {
                $array['children'] = $this->employee->subordinates
                    ->map(function ($sub) {
                        return $sub->relationLoaded('user') && $sub->user ? [
                            'id' => $sub->user->id,
                            'name' => $sub->user->name,
                            'username' => $sub->user->username,
                            'email' => $sub->user->email,
                            'user_type' => $sub->user->user_type,
                            'is_active' => $sub->user->is_active,
                            'can_login' => $sub->user->can_login,
                        ] : null;
                    })
                    ->filter()
                    ->values()
                    ->toArray();
            } else {
                $array['children'] = [];
            }
        } else {
            $array['branch'] = null;
            $array['zone'] = null;
            $array['region'] = null;
            $array['province'] = null;
            $array['parent'] = null;
            $array['children'] = [];
        }

        return $array;
    }

    /* Helper Methods */

    /**
     * Helper to clear Spatie's permission cache
     */
    public function forgetCachedPermissions(): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function canLogin(): bool
    {
        $canLogin = $this->is_active && $this->can_login;

        if ($this->employee_id && $this->relationLoaded('employee')) {
            return $canLogin && $this->employee && $this->employee->is_active;
        }

        // If not loaded, check existence
        if ($this->employee_id) {
            return $canLogin && $this->load('employee')->employee->is_active;
        }

        return $canLogin;
    }

    public function updateLastLogin($ipAddress = null)
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ipAddress
        ]);
    }

    /**
     * Generate a unique email verification token
     */
    public function generateEmailVerificationToken(): string
    {
        $token = bin2hex(random_bytes(32));

        $this->update([
            'email_verification_token' => $token,
            'email_verification_token_expires_at' => now()->addHours(24)
        ]);

        return $token;
    }

    /**
     * Mark the user's email as verified
     */
    public function markEmailAsVerifiedcheck(string $token)
    {
        $this->update([
            'email_verified_at' => now(),
            'email_verification_token' => $token,
            'email_verification_token_expires_at' => null
        ]);
    }

    /**
     * Mark the user's email as verified without a token
     */
    public function markEmailAsVerified()
    {
        $this->update([
            'email_verified_at' => now(),
            'email_verification_token' => null,
            'email_verification_token_expires_at' => null
        ]);
    }

    /**
     * Check if the user's email verification token is valid
     */
    public function isEmailVerificationTokenValid(string $token): bool
    {
        if ($this->email_verification_token !== $token) {
            return false;
        }

        if (!$this->email_verification_token_expires_at) {
            return false;
        }

        return now()->lessThan($this->email_verification_token_expires_at);
    }

    /**
     * Get all direct and indirect subordinate IDs (descendants).
     */
    public function getAllDescendantIds(): array
    {
        if (!$this->employee_id) {
            return [];
        }

        if ($this->relationLoaded('employee') && $this->employee) {
            return $this->employee->getAllDescendantUserIds();
        }

        return $this->load('employee')->employee->getAllDescendantUserIds();
    }

    /**
     * Check if the user has verified their email
     */
    public function hasVerifiedEmail(): bool
    {
        return !is_null($this->email_verified_at);
    }

}
