<?php

namespace App\Services\CompanyLookup;

/** Подсказка реквизитов организации по ИНН. */
interface CompanyLookup
{
    /** @return array<string, mixed>|null реквизиты или null, если не найдено / сервис не настроен */
    public function findByInn(string $inn): ?array;
}
