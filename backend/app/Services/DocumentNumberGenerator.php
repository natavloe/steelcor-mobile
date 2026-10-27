<?php

namespace App\Services;

use App\Models\DocumentCounter;
use App\Models\Region;
use Illuminate\Support\Facades\DB;

/**
 * Номера документов: {префикс}-{РЕГИОН}-{год}-{000123}, например ЗАК-SMR-2026-000123.
 * Счётчик свой у юрлица, вида документа и года (О-4 — формат может измениться).
 */
class DocumentNumberGenerator
{
    public const PREFIXES = [
        'order' => 'ЗАК',
        'invoice' => 'СЧ',
        'offer' => 'КП',
        'contract' => 'Д',
        'specification' => 'СП',
        'purchase_order' => 'П',
    ];

    public function next(string $kind, int $sellerEntityId, Region $region, ?int $year = null): string
    {
        $year ??= (int) now()->format('Y');

        $number = DB::transaction(function () use ($kind, $sellerEntityId, $year) {
            $counter = DocumentCounter::query()
                ->where(['seller_entity_id' => $sellerEntityId, 'kind' => $kind, 'year' => $year])
                ->lockForUpdate()
                ->first();

            if ($counter === null) {
                DocumentCounter::query()->insertOrIgnore([
                    'seller_entity_id' => $sellerEntityId, 'kind' => $kind, 'year' => $year, 'last_number' => 0,
                ]);
                $counter = DocumentCounter::query()
                    ->where(['seller_entity_id' => $sellerEntityId, 'kind' => $kind, 'year' => $year])
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $counter->increment('last_number');

            return $counter->last_number;
        });

        $prefix = self::PREFIXES[$kind] ?? mb_strtoupper($kind);

        return sprintf('%s-%s-%d-%06d', $prefix, mb_strtoupper($region->code), $year, $number);
    }
}
