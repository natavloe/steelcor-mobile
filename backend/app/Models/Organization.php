<?php

namespace App\Models;

use App\Enums\OrganizationType;
use App\Enums\VerificationStatus;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Организация-покупатель. */
class Organization extends Model
{
    use RecordsActivity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => OrganizationType::class,
            'verification_status' => VerificationStatus::class,
            'external_snapshot' => 'array',
            'verified_at' => 'datetime',
            'credit_limit' => 'decimal:2',
        ];
    }

    public function members(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_members');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(DeliveryAddress::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'manager_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function frameworkContract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'framework_contract_id');
    }
}
