<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Models\Concerns\HasStatusHistory;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Файл документа с отпечатком и снимком данных. */
class Document extends Model
{
    use HasStatusHistory, RecordsActivity;

    protected $guarded = ['id'];

    public function historyEntityType(): string
    {
        return 'document';
    }

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'data_snapshot' => 'array',
            'doc_date' => 'date',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'template_id');
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(Signature::class);
    }

    public function edoSubmissions(): HasMany
    {
        return $this->hasMany(EdoSubmission::class);
    }
}
