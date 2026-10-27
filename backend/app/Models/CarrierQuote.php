<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Журнал расчётов стоимости у ТК. */
class CarrierQuote extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'request' => 'array',
            'cost' => 'decimal:2',
            'is_error' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
