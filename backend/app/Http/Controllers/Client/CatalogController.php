<?php

namespace App\Http\Controllers\Client;

use App\Enums\Unit;
use App\Exceptions\WorkflowException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Client\CategoryResource;
use App\Http\Resources\Client\PickupPointResource;
use App\Http\Resources\Client\ProductResource;
use App\Http\Resources\Client\RegionResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\Region;
use App\Services\UnitConverter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Каталог без входа: регионы, категории, поиск и карточки позиций. */
class CatalogController extends Controller
{
    public function regions(): AnonymousResourceCollection
    {
        return RegionResource::collection(Region::query()->where('is_active', true)->orderBy('sort')->get());
    }

    public function categories(): AnonymousResourceCollection
    {
        $all = Category::query()->where('is_published', true)->orderBy('sort')->orderBy('name')->get();
        $byParent = $all->groupBy('parent_id');
        $attach = function ($items) use (&$attach, $byParent) {
            return $items->each(fn (Category $c) => $c->setRelation('children', $attach($byParent->get($c->id, collect()))));
        };

        return CategoryResource::collection($attach($byParent->get(null, collect()))->values());
    }

    /** Фильтры категории со значениями, которые реально встречаются в позициях региона. */
    public function filters(Request $request, Category $category): JsonResponse
    {
        $region = $request->attributes->get('region');
        $products = $this->visibleProducts($region)->where('category_id', $category->id)->pluck('attrs');

        $filters = $category->characteristics()->wherePivot('is_filterable', true)->get()->map(function ($attribute) use ($products) {
            $values = $products->pluck($attribute->code)->filter(fn ($v) => $v !== null && $v !== '')->unique()->values();
            $base = ['code' => $attribute->code, 'name' => $attribute->name, 'unit' => $attribute->unit, 'type' => $attribute->type];

            return $attribute->type === 'number'
                ? $base + ['min' => $values->min(), 'max' => $values->max()]
                : $base + ['values' => $values->sort()->values()];
        });

        return response()->json(['data' => $filters]);
    }

    public function products(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'category' => 'nullable|integer',
            'q' => 'nullable|string|max:100',
            'f' => 'nullable|array',
            'sort' => ['nullable', Rule::in(['name', 'price_asc', 'price_desc'])],
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        $region = $request->attributes->get('region');
        $query = $this->visibleProducts($region);

        if (! empty($data['category'])) {
            $ids = $this->categoryWithDescendants((int) $data['category']);
            $query->whereIn('category_id', $ids);
        }
        if (! empty($data['q'])) {
            $q = trim($data['q']);
            $query->where(function (Builder $w) use ($q) {
                $w->whereRaw("search_text @@ websearch_to_tsquery('russian', ?)", [$q])
                    ->orWhere('name', 'ilike', '%'.addcslashes($q, '%_\\').'%')
                    ->orWhere('grade', 'ilike', addcslashes($q, '%_\\').'%')
                    ->orWhereRaw('similarity(name, ?) > 0.3', [$q]);
            });
        }
        foreach ($data['f'] ?? [] as $code => $value) {
            if (! preg_match('/^[a-z0-9_]+$/', (string) $code)) {
                continue;
            }
            if (is_array($value)) {
                // Нечисловые значения не ломают запрос: к числу приводится только похожее на число.
                if (isset($value['min']) && is_numeric($value['min'])) {
                    $query->whereRaw("(CASE WHEN attrs->>'{$code}' ~ '^-?[0-9]+(\\.[0-9]+)?$' THEN (attrs->>'{$code}')::numeric END) >= ?", [$value['min']]);
                }
                if (isset($value['max']) && is_numeric($value['max'])) {
                    $query->whereRaw("(CASE WHEN attrs->>'{$code}' ~ '^-?[0-9]+(\\.[0-9]+)?$' THEN (attrs->>'{$code}')::numeric END) <= ?", [$value['max']]);
                }
                $list = array_values(array_filter($value, fn ($k) => is_int($k), ARRAY_FILTER_USE_KEY));
                if ($list !== []) {
                    $query->whereIn(DB::raw("attrs->>'{$code}'"), array_map('strval', $list));
                }
            } else {
                $query->whereRaw("attrs->>'{$code}' = ?", [(string) $value]);
            }
        }

        match ($data['sort'] ?? 'name') {
            'price_asc', 'price_desc' => $query->leftJoin('region_products as rp_sort', fn ($j) => $j->on('rp_sort.product_id', '=', 'products.id')->where('rp_sort.region_id', $region->id))
                ->orderByRaw('rp_sort.public_price '.(($data['sort'] ?? '') === 'price_desc' ? 'desc' : 'asc').' nulls last')
                ->select('products.*'),
            default => $query->orderBy('name'),
        };

        return ProductResource::collection($query->paginate($data['per_page'] ?? 30));
    }

    public function product(Request $request, Product $product): ProductResource
    {
        $region = $request->attributes->get('region');
        $found = $this->visibleProducts($region)->with('images')->whereKey($product->id)->firstOrFail();

        return new ProductResource($found);
    }

    public function convert(Request $request, Product $product, UnitConverter $converter): JsonResponse
    {
        $data = $request->validate([
            'qty' => 'required|numeric|min:0',
            'from' => ['required', Rule::enum(Unit::class)],
            'to' => ['required', Rule::enum(Unit::class)],
        ]);
        $result = $converter->convert($product, (string) $data['qty'], Unit::from($data['from']), Unit::from($data['to']));
        if ($result === null) {
            throw new WorkflowException('conversion_unavailable', 'Для этой позиции пересчёт единиц недоступен.');
        }

        return response()->json(['data' => ['qty' => $result, 'unit' => $data['to'], 'is_theoretical' => true]]);
    }

    public function pickupPoints(Request $request): AnonymousResourceCollection
    {
        $region = $request->attributes->get('region');

        return PickupPointResource::collection($region->pickupPoints()->where('is_active', true)->where('is_public', true)->get());
    }

    /** Позиции, опубликованные и видимые в регионе; условия региона подгружены. */
    private function visibleProducts(Region $region): Builder
    {
        return Product::query()
            ->where('products.is_published', true)
            ->whereHas('regionProducts', fn ($q) => $q->where('region_id', $region->id)->where('is_visible', true))
            ->with(['regionProducts' => fn ($q) => $q->where('region_id', $region->id)]);
    }

    /** @return list<int> */
    private function categoryWithDescendants(int $id): array
    {
        $ids = [$id];
        $frontier = [$id];
        while ($frontier !== []) {
            $frontier = Category::query()->whereIn('parent_id', $frontier)->pluck('id')->all();
            $ids = array_merge($ids, $frontier);
        }

        return $ids;
    }
}
