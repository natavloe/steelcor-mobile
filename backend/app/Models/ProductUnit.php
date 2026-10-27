<?php

namespace App\Models;

use App\Enums\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Единица, в которой можно заказать позицию. */
class ProductUnit extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'unit' => Unit::class,
            'to_base_ratio' => 'decimal:8',
            'step' => 'decimal:4',
            'min_qty' => 'decimal:4',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
