<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Models\Concerns\HasStatusHistory;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Договор с покупателем. */
class Contract extends Model
{
    use HasStatusHistory, RecordsActivity;

    protected $guarded = ['id'];

    /** Значения по умолчанию (как в базе), чтобы новые записи сразу их видели. */
    protected $attributes = ['status' => 'generating'];

    public function historyEntityType(): string
    {
        return 'contract';
    }

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'signed_date' => 'date',
            'valid_until' => 'date',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function sellerEntity(): BelongsTo
    {
        return $this->belongsTo(SellerEntity::class);
    }

    public function currentDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'current_document_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
