<?php

namespace App\Http\Controllers\Client;

use App\Exceptions\WorkflowException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Вход по SMS-коду. Новый пользователь обязан дать согласие на обработку персональных данных. */
class AuthController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    public function requestCode(Request $request): JsonResponse
    {
        $data = $request->validate(['phone' => 'required|string|max:20']);
        $phone = OtpService::normalizePhone($data['phone']);
        $this->otp->request($phone);

        return response()->json(['data' => [
            'phone' => $phone,
            'is_new_user' => ! User::where('phone', $phone)->exists(),
            'ttl_seconds' => (int) config('steelcor.otp.ttl_minutes', 5) * 60,
        ]]);
    }

    public function verifyCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => 'required|string|max:20',
            'code' => 'required|string|max:8',
            'name' => 'nullable|string|max:255',
            'pd_consent' => 'nullable|boolean',
            'device_name' => 'nullable|string|max:100',
        ]);
        $phone = OtpService::normalizePhone($data['phone']);
        $this->otp->verify($phone, $data['code']);

        $user = User::where('phone', $phone)->first();
        if ($user === null) {
            if (! ($data['pd_consent'] ?? false)) {
                throw new WorkflowException('pd_consent_required', 'Нужно согласие на обработку персональных данных.');
            }
            $user = User::create([
                'phone' => $phone,
                'name' => $data['name'] ?? null,
                'phone_verified_at' => now(),
                'pd_consent_at' => now(),
                'pd_consent_version' => config('steelcor.pd_consent_version'),
            ]);
        } elseif ($user->status !== 'active') {
            throw new WorkflowException('user_blocked', 'Доступ заблокирован. Свяжитесь с менеджером.');
        }

        $token = $user->createToken($data['device_name'] ?? 'mobile')->plainTextToken;

        return response()->json(['data' => ['token' => $token, 'user' => $this->userPayload($user)]]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->userPayload($request->user())]);
    }

    public function updateMe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|nullable|string|max:255',
            'email' => 'sometimes|nullable|email|max:255|unique:users,email,'.$request->user()->id,
            'default_region' => 'sometimes|nullable|exists:regions,code',
        ]);
        $user = $request->user();
        if (array_key_exists('default_region', $data)) {
            $data['default_region_id'] = $data['default_region'] ? \App\Models\Region::where('code', $data['default_region'])->value('id') : null;
            unset($data['default_region']);
        }
        if (array_key_exists('email', $data) && $data['email'] !== $user->email) {
            $data['email_verified_at'] = null;
        }
        $user->update($data);

        return response()->json(['data' => $this->userPayload($user->refresh())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['data' => ['ok' => true]]);
    }

    public function registerPushToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => 'required|string|max:255',
            'platform' => 'required|in:ios,android',
            'provider' => 'required|in:expo,fcm,apns,rustore',
        ]);
        $request->user()->pushTokens()->updateOrCreate(['token' => $data['token']], $data + ['last_seen_at' => now()]);

        return response()->json(['data' => ['ok' => true]]);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'email' => $user->email,
            'default_region' => $user->defaultRegion?->code,
            'organizations' => $user->organizations()->get()->map(fn ($o) => [
                'id' => $o->id, 'name' => $o->name_short, 'inn' => $o->inn, 'role' => $o->pivot->role,
                'verification_status' => $o->verification_status->value,
            ])->values(),
        ];
    }
}
