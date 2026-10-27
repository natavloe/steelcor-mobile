<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Шаблон документа (.docx). Документы формируются только по утверждённым. */
class DocumentTemplate extends Model
{
    use RecordsActivity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => ApprovalStatus::class,
            'placeholders' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function sellerEntity(): BelongsTo
    {
        return $this->belongsTo(SellerEntity::class);
    }
}
