<?php

namespace App\Models;

use App\Enums\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Позиция корзины. */
class CartItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'unit' => Unit::class,
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
