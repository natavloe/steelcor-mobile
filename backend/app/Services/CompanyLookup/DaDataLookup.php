<?php

namespace App\Services\CompanyLookup;

use App\Models\IntegrationLog;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * DaData: метод findById/party. Без ключа (DADATA_API_KEY) не вызывается — клиент вводит реквизиты вручную.
 * Ответ — только подсказка: реквизиты всё равно проверяет менеджер.
 */
class DaDataLookup implements CompanyLookup
{
    private const URL = 'https://suggestions.dadata.ru/suggestions/api/4_1/rs/findById/party';

    public function findByInn(string $inn): ?array
    {
        $key = config('services.dadata.key');
        if (! $key) {
            return null;
        }

        $started = microtime(true);
        try {
            $response = Http::withHeaders(['Authorization' => 'Token '.$key])->acceptJson()->timeout(5)
                ->post(self::URL, ['query' => $inn, 'count' => 1]);
        } catch (Throwable $e) {
            $this->log($inn, null, null, $started);

            return null;
        }
        $this->log($inn, $response->status(), $response->successful() ? ['found' => count($response->json('suggestions', []))] : $response->json(), $started);

        $item = $response->successful() ? $response->json('suggestions.0') : null;
        if ($item === null) {
            return null;
        }
        $data = $item['data'] ?? [];

        return [
            'type' => ($data['type'] ?? null) === 'INDIVIDUAL' ? 'ip' : 'legal',
            'name_full' => $data['name']['full_with_opf'] ?? $item['value'],
            'name_short' => $data['name']['short_with_opf'] ?? $item['value'],
            'inn' => $data['inn'] ?? $inn,
            'kpp' => $data['kpp'] ?? null,
            'ogrn' => $data['ogrn'] ?? null,
            'legal_address' => $data['address']['unrestricted_value'] ?? $data['address']['value'] ?? null,
            'signer_name' => $data['management']['name'] ?? null,
            'signer_position' => $data['management']['post'] ?? null,
            'status' => $data['state']['status'] ?? null,
            'raw' => $item,
        ];
    }

    private function log(string $inn, ?int $status, ?array $response, float $started): void
    {
        IntegrationLog::create([
            'provider' => 'dadata', 'operation' => 'findById/party', 'request' => ['inn' => $inn],
            'response' => $response, 'http_status' => $status, 'duration_ms' => (int) ((microtime(true) - $started) * 1000),
        ]);
    }
}
