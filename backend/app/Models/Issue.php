<?php

namespace App\Models;

use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Models\Concerns\HasStatusHistory;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Инцидент по заказу. */
class Issue extends Model
{
    use HasStatusHistory, RecordsActivity;

    protected $guarded = ['id'];

    /** Значения по умолчанию (как в базе), чтобы новые записи сразу их видели. */
    protected $attributes = ['status' => 'open'];

    public function historyEntityType(): string
    {
        return 'issue';
    }

    protected function casts(): array
    {
        return [
            'type' => IssueType::class,
            'status' => IssueStatus::class,
            'client_agreed_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(IssueAction::class);
    }

    public function newOffer(): BelongsTo
    {
        return $this->belongsTo(Offer::class, 'new_offer_id');
    }
}
