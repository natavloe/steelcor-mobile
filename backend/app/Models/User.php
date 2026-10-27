<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/** Клиент — пользователь мобильного приложения. Сотрудники компании — в StaffUser. */
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'pd_consent_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_members')
            ->withPivot(['role', 'status'])
            ->wherePivot('status', 'active');
    }

    public function defaultRegion(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'default_region_id');
    }

    public function pushTokens(): HasMany
    {
        return $this->hasMany(PushToken::class);
    }

    /** Активное членство в организации или null. */
    public function membershipIn(int $organizationId): ?OrganizationMember
    {
        return $this->memberships()
            ->where('organization_id', $organizationId)
            ->where('status', 'active')
            ->first();
    }
}
