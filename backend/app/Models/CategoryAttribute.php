<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Связь категории и характеристики (для фильтров). */
class CategoryAttribute extends Model
{
    protected $table = 'category_attributes';

    public $timestamps = false;

    protected $guarded = ['id'];

    /** Значения по умолчанию (как в базе), чтобы новые записи сразу их видели. */
    protected $attributes = ['is_filterable' => true, 'is_required' => false, 'sort' => 0];

    protected function casts(): array
    {
        return [
            'is_filterable' => 'boolean',
            'is_required' => 'boolean',
        ];
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
