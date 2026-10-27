<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** ВНУТРЕННЕЕ: правило наценки. */
class PricingRule extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'markup_percent' => 'decimal:2',
            'markup_fixed' => 'decimal:2',
            'min_margin_percent' => 'decimal:2',
            'valid_from' => 'date',
            'valid_to' => 'date',
        ];
    }
}
