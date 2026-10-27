<?php

namespace App\Services\Bank;

use App\Enums\ApprovalStatus;
use App\Enums\MatchStatus;
use App\Exceptions\WorkflowException;
use App\Models\BankTransaction;
use App\Models\SellerEntity;
use App\Models\StaffUser;
use Carbon\Carbon;

/**
 * Загрузка выписки банка. Источник истины об оплате — только выписка или API банка;
 * ручного ввода сумм нет. Повторная загрузка одного платежа отбрасывается.
 */
class BankStatementImporter
{
    public function __construct(
        private readonly ClientBankExchangeParser $parser,
        private readonly PaymentMatcher $matcher,
    ) {}

    /** @return array{created:int, duplicates:int, matched:int, skipped:int} */
    public function importFile(SellerEntity $seller, string $content, ?StaffUser $by = null): array
    {
        $accounts = $seller->requisites()
            ->whereIn('status', [ApprovalStatus::Approved->value, ApprovalStatus::Archived->value])
            ->pluck('account')->unique()->values()->all();
        if ($accounts === []) {
            throw new WorkflowException('no_account', 'У продавца нет утверждённых реквизитов с расчётным счётом.');
        }

        $parsed = $this->parser->parse($content);
        $stats = ['created' => 0, 'duplicates' => 0, 'matched' => 0, 'skipped' => 0];

        foreach ($parsed['documents'] as $doc) {
            $incoming = in_array($doc['ПолучательСчет'] ?? null, $accounts, true);
            $outgoing = in_array($doc['ПлательщикСчет'] ?? null, $accounts, true);
            if (! $incoming && ! $outgoing) {
                $stats['skipped']++;

                continue;
            }

            $date = $doc[$incoming ? 'ДатаПоступило' : 'ДатаСписано'] ?? $doc['Дата'] ?? null;
            $amount = str_replace([' ', ','], ['', '.'], $doc['Сумма'] ?? '0');
            $externalId = sha1(implode('|', [
                $doc['Номер'] ?? '', $doc['Дата'] ?? '', $amount,
                $doc['ПлательщикСчет'] ?? '', $doc['ПолучательСчет'] ?? '', $incoming ? 'in' : 'out',
            ]));

            $counterparty = $incoming ? 'Плательщик' : 'Получатель';
            $tx = BankTransaction::query()->firstOrCreate(
                ['source' => 'statement_file', 'external_id' => $externalId],
                [
                    'seller_entity_id' => $seller->id,
                    'direction' => $incoming ? 'in' : 'out',
                    'op_date' => $date ? Carbon::createFromFormat('d.m.Y', $date)->toDateString() : now()->toDateString(),
                    'amount' => $amount,
                    'payer_inn' => $doc[$counterparty.'ИНН'] ?? null,
                    'payer_kpp' => $doc[$counterparty.'КПП'] ?? null,
                    'payer_name' => $doc[$counterparty.'1'] ?? $doc[$counterparty] ?? null,
                    'payer_account' => $doc[$counterparty.'Счет'] ?? null,
                    'purpose' => $doc['НазначениеПлатежа'] ?? null,
                    'match_status' => MatchStatus::Unmatched,
                    'raw' => $doc,
                    'imported_by' => $by?->id,
                ],
            );

            if (! $tx->wasRecentlyCreated) {
                $stats['duplicates']++;

                continue;
            }
            $stats['created']++;
            if ($incoming && $this->matcher->autoMatch($tx)) {
                $stats['matched']++;
            }
        }

        return $stats;
    }
}
