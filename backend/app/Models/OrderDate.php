<?php

namespace App\Models;

use App\Enums\DateKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Срок по заказу (каждое изменение — новая строка). */
class OrderDate extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'kind' => DateKind::class,
            'date_from' => 'date',
            'date_to' => 'date',
            'notify_client' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
