<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Характеристика товара (марка, ГОСТ, диаметр…). */
class Attribute extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'options' => 'array',
        ];
    }
}
