<?php

namespace App\Http\Controllers\Client;

use App\Enums\MemberRole;
use App\Enums\VerificationStatus;
use App\Exceptions\WorkflowException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Client\AddressResource;
use App\Http\Resources\Client\OrganizationResource;
use App\Models\DeliveryAddress;
use App\Models\Organization;
use App\Models\User;
use App\Services\Auth\OtpService;
use App\Services\CompanyLookup\CompanyLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Организации клиента, сотрудники организации и адреса доставки. */
class OrganizationController extends Controller
{
    private const REQUISITE_RULES = [
        'type' => 'required|in:legal,ip',
        'name_full' => 'required|string|max:500',
        'name_short' => 'required|string|max:255',
        'inn' => ['required', 'regex:/^\d{10}(\d{2})?$/'],
        'kpp' => ['nullable', 'regex:/^\d{9}$/'],
        'ogrn' => ['nullable', 'regex:/^\d{13}(\d{2})?$/'],
        'legal_address' => 'nullable|string|max:1000',
        'bank_name' => 'nullable|string|max:255',
        'bik' => ['nullable', 'regex:/^\d{9}$/'],
        'account' => ['nullable', 'regex:/^\d{20}$/'],
        'corr_account' => ['nullable', 'regex:/^\d{20}$/'],
        'signer_name' => 'nullable|string|max:255',
        'signer_position' => 'nullable|string|max:255',
        'signer_basis' => 'nullable|string|max:255',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        return OrganizationResource::collection($request->user()->organizations()->get());
    }

    public function lookup(Request $request, CompanyLookup $lookup): JsonResponse
    {
        $data = $request->validate(['inn' => ['required', 'regex:/^\d{10}(\d{2})?$/']]);
        $found = $lookup->findByInn($data['inn']);
        unset($found['raw']);

        return response()->json(['data' => $found]);
    }

    public function store(Request $request, CompanyLookup $lookup): JsonResponse
    {
        $data = $request->validate(self::REQUISITE_RULES);
        $user = $request->user();

        $existing = Organization::query()->where('inn', $data['inn'])->where('kpp', $data['kpp'] ?? null)->first();
        if ($existing) {
            // Организация уже есть: присоединиться можно только по приглашению владельца.
            throw new WorkflowException('organization_exists', 'Эта организация уже зарегистрирована. Попросите её владельца пригласить вас в приложении.');
        }

        $organization = DB::transaction(function () use ($data, $user, $lookup, $request) {
            $snapshot = $lookup->findByInn($data['inn']);
            $organization = Organization::create($data + [
                'source' => $snapshot ? 'dadata' : 'manual',
                'external_snapshot' => $snapshot['raw'] ?? null,
                'verification_status' => VerificationStatus::Unverified,
                'region_id' => $user->default_region_id ?? $request->attributes->get('region')?->id,
            ]);
            $organization->members()->create(['user_id' => $user->id, 'role' => MemberRole::Owner]);

            return $organization;
        });

        return (new OrganizationResource($organization))->response()->setStatusCode(201);
    }

    public function show(Request $request): OrganizationResource
    {
        return new OrganizationResource($request->attributes->get('organization'));
    }

    /** Изменение реквизитов сбрасывает проверку: менеджер проверит заново. */
    public function update(Request $request): OrganizationResource
    {
        $this->requireRole($request, [MemberRole::Owner, MemberRole::Accountant]);
        $rules = self::REQUISITE_RULES;
        unset($rules['inn'], $rules['type']);
        $data = $request->validate(array_map(fn ($r) => is_array($r) ? array_merge(['sometimes'], $r) : 'sometimes|'.$r, $rules));

        /** @var Organization $organization */
        $organization = $request->attributes->get('organization');
        $organization->fill($data);
        if ($organization->isDirty()) {
            $organization->verification_status = VerificationStatus::Unverified;
            $organization->save();
        }

        return new OrganizationResource($organization);
    }

    public function members(Request $request): JsonResponse
    {
        /** @var Organization $organization */
        $organization = $request->attributes->get('organization');

        return response()->json(['data' => $organization->members()->with('user')->where('status', 'active')->get()->map(fn ($m) => [
            'id' => $m->id, 'name' => $m->user->name, 'phone' => $m->user->phone, 'role' => $m->role->value, 'role_label' => $m->role->label(),
        ])]);
    }

    public function invite(Request $request): JsonResponse
    {
        $this->requireRole($request, [MemberRole::Owner]);
        $data = $request->validate(['phone' => 'required|string|max:20', 'role' => ['required', Rule::enum(MemberRole::class)]]);
        $phone = OtpService::normalizePhone($data['phone']);
        /** @var Organization $organization */
        $organization = $request->attributes->get('organization');

        $user = User::firstOrCreate(['phone' => $phone]);
        $organization->members()->updateOrCreate(['user_id' => $user->id], [
            'role' => $data['role'], 'status' => 'active', 'invited_by' => $request->user()->id,
        ]);

        return response()->json(['data' => ['ok' => true]], 201);
    }

    public function removeMember(Request $request, int $memberId): JsonResponse
    {
        $this->requireRole($request, [MemberRole::Owner]);
        /** @var Organization $organization */
        $organization = $request->attributes->get('organization');
        $member = $organization->members()->findOrFail($memberId);
        if ($member->user_id === $request->user()->id) {
            throw new WorkflowException('cannot_remove_self', 'Нельзя удалить себя.');
        }
        $member->update(['status' => 'removed']);

        return response()->json(['data' => ['ok' => true]]);
    }

    public function addresses(Request $request): AnonymousResourceCollection
    {
        return AddressResource::collection($request->attributes->get('organization')->addresses()->where('is_archived', false)->get());
    }

    public function storeAddress(Request $request): JsonResponse
    {
        $this->requireRole($request, [MemberRole::Owner, MemberRole::Buyer]);
        $address = $request->attributes->get('organization')->addresses()->create($this->validateAddress($request));

        return (new AddressResource($address))->response()->setStatusCode(201);
    }

    public function updateAddress(Request $request, int $addressId): AddressResource
    {
        $this->requireRole($request, [MemberRole::Owner, MemberRole::Buyer]);
        $address = $this->findAddress($request, $addressId);
        $address->update($this->validateAddress($request));

        return new AddressResource($address);
    }

    public function deleteAddress(Request $request, int $addressId): JsonResponse
    {
        $this->requireRole($request, [MemberRole::Owner, MemberRole::Buyer]);
        // Не удаляем: адрес мог использоваться в заказах.
        $this->findAddress($request, $addressId)->update(['is_archived' => true]);

        return response()->json(['data' => ['ok' => true]]);
    }

    private function findAddress(Request $request, int $id): DeliveryAddress
    {
        return $request->attributes->get('organization')->addresses()->where('is_archived', false)->findOrFail($id);
    }

    private function validateAddress(Request $request): array
    {
        return $request->validate([
            'label' => 'nullable|string|max:255',
            'address' => 'required|string|max:1000',
            'city' => 'nullable|string|max:255',
            'fias_id' => 'nullable|string|max:64',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'contact_name' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'access_notes' => 'nullable|string|max:1000',
        ]);
    }

    /** @param list<MemberRole> $roles */
    private function requireRole(Request $request, array $roles): void
    {
        if (! in_array($request->attributes->get('membership')->role, $roles, true)) {
            throw new WorkflowException('forbidden', 'У вас нет прав на это действие в этой организации.');
        }
    }
}
