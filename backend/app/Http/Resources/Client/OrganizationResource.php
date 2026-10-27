<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Организация клиента. Внутренняя заметка, кредитный лимит и менеджерские поля не отдаются.
 *
 * @mixin \App\Models\Organization
 */
class OrganizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'name_full' => $this->name_full,
            'name_short' => $this->name_short,
            'inn' => $this->inn,
            'kpp' => $this->kpp,
            'ogrn' => $this->ogrn,
            'legal_address' => $this->legal_address,
            'bank_name' => $this->bank_name,
            'bik' => $this->bik,
            'account' => $this->account,
            'corr_account' => $this->corr_account,
            'signer_name' => $this->signer_name,
            'signer_position' => $this->signer_position,
            'signer_basis' => $this->signer_basis,
            'verification_status' => $this->verification_status->value,
            'verification_label' => $this->verification_status->label(),
            'my_role' => $this->whenPivotLoaded('organization_members', fn () => $this->pivot->role),
        ];
    }
}
