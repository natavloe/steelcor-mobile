<?php

namespace App\Services\Sms;

/** Отправка SMS. Реализация шлюза выбирается перед запуском (SMS-шлюз в РФ). */
interface SmsSender
{
    public function send(string $phone, string $text): void;
}
