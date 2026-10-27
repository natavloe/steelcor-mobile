<?php

namespace App\Services\Bank;

use App\Exceptions\WorkflowException;

/**
 * Разбор выписки в формате 1CClientBankExchange (выгрузка из интернет-банка для 1С).
 * Возвращает документы как массивы «ключ => значение».
 */
class ClientBankExchangeParser
{
    /** @return array{account: ?string, documents: list<array<string,string>>} */
    public function parse(string $content): array
    {
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1251');
        }
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = preg_split('/\r\n|\r|\n/', $content);

        if (trim($lines[0] ?? '') !== '1CClientBankExchange') {
            throw new WorkflowException('bad_statement', 'Файл не похож на выписку 1CClientBankExchange.');
        }

        $account = null;
        $documents = [];
        $current = null;
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || ! str_contains($line, '=')) {
                if ($line === 'КонецДокумента' && $current !== null) {
                    $documents[] = $current;
                    $current = null;
                }

                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            if ($key === 'СекцияДокумент') {
                $current = ['СекцияДокумент' => $value];

                continue;
            }
            if ($current !== null) {
                $current[$key] = $value;
            } elseif ($key === 'РасчСчет') {
                $account = $value;
            }
        }

        return ['account' => $account, 'documents' => $documents];
    }
}
