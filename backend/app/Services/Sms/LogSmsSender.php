<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/** Для разработки: SMS пишется в лог. В боевой среде запрещён. */
class LogSmsSender implements SmsSender
{
    public function send(string $phone, string $text): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('SMS-шлюз не настроен: в боевой среде LogSmsSender запрещён.');
        }
        Log::info("SMS для {$phone}: {$text}");
    }
}
