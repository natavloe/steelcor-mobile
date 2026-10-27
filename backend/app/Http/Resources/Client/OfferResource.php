<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Предложение (условия) для клиента. Себестоимости и маржи нет.
 *
 * @mixin \App\Models\Offer
 */
class OfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'version' => $this->version,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'reason' => $this->reason,
            'valid_until' => $this->valid_until?->toIso8601String(),
            'payment_terms' => $this->payment_terms->value,
            'payment_terms_label' => $this->payment_terms->label(),
            'prepay_percent' => $this->prepay_percent,
            'deferral_days' => $this->deferral_days,
            'lead_time_text' => $this->lead_time_text,
            'totals' => [
                'goods' => (string) $this->goods_total,
                'goods_vat' => (string) $this->goods_vat,
                'delivery' => (string) $this->delivery_total,
                'services' => (string) $this->services_total,
                'total' => (string) $this->grand_total,
            ],
            'lines' => $this->lines->map(fn ($line) => [
                'type' => $line->line_type,
                'name' => $line->name,
                'grade' => $line->grade,
                'gost' => $line->gost,
                'size' => $line->size_text,
                'qty' => (string) $line->qty,
                'unit' => $line->unit->value,
                'unit_price' => (string) $line->unit_price,
                'vat_rate' => (string) $line->vat_rate,
                'amount' => (string) $line->amount_total,
                'lead_time_text' => $line->lead_time_text,
            ])->values(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
        ];
    }
}
