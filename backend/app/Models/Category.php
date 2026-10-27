<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Категория каталога. */
class Category extends Model
{
    protected $guarded = ['id'];

    /** Значения по умолчанию (как в базе), чтобы новые записи сразу их видели. */
    protected $attributes = ['sort' => 0, 'is_published' => true];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function categoryAttributes(): HasMany
    {
        return $this->hasMany(CategoryAttribute::class)->orderBy('sort');
    }

    public function characteristics(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'category_attributes')->withPivot(['is_filterable', 'is_required', 'sort'])->orderByPivot('sort');
    }
}
