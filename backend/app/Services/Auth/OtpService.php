<?php

namespace App\Services\Auth;

use App\Exceptions\WorkflowException;
use App\Services\Sms\SmsSender;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/** Одноразовые коды входа по SMS: хранится только хеш, ограничены срок и число попыток. */
class OtpService
{
    public function __construct(private readonly SmsSender $sms) {}

    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (strlen($digits) === 11 && in_array($digits[0], ['7', '8'], true)) {
            $digits = '7'.substr($digits, 1);
        } elseif (strlen($digits) === 10) {
            $digits = '7'.$digits;
        }
        if (! preg_match('/^7\d{10}$/', $digits)) {
            throw new WorkflowException('phone_invalid', 'Введите российский номер телефона.');
        }

        return '+'.$digits;
    }

    public function request(string $phone): void
    {
        $length = (int) config('steelcor.otp.length', 4);
        $code = str_pad((string) random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);
        Cache::put($this->key($phone), ['hash' => Hash::make($code), 'attempts' => 0], now()->addMinutes((int) config('steelcor.otp.ttl_minutes', 5)));
        $this->sms->send($phone, "Код входа в приложение Стилкор: {$code}. Никому его не сообщайте.");
    }

    public function verify(string $phone, string $code): void
    {
        $key = $this->key($phone);
        $data = Cache::get($key);
        if ($data === null) {
            throw new WorkflowException('otp_expired', 'Код устарел. Запросите новый.');
        }
        if ($data['attempts'] >= (int) config('steelcor.otp.max_attempts', 5)) {
            Cache::forget($key);
            throw new WorkflowException('otp_attempts', 'Слишком много попыток. Запросите новый код.');
        }
        if (! Hash::check($code, $data['hash'])) {
            $data['attempts']++;
            Cache::put($key, $data, now()->addMinutes((int) config('steelcor.otp.ttl_minutes', 5)));
            throw new WorkflowException('otp_invalid', 'Неверный код.');
        }
        Cache::forget($key);
    }

    private function key(string $phone): string
    {
        return 'otp:'.$phone;
    }
}
