<?php

namespace App\Services\Bank;

use App\Enums\InvoiceStatus;
use App\Enums\MatchStatus;
use App\Exceptions\WorkflowException;
use App\Models\BankTransaction;
use App\Models\Invoice;
use App\Models\PaymentAllocation;
use App\Services\OrderWorkflow;
use App\Support\Actor;
use App\Support\Money;
use App\Support\Permissions;
use Illuminate\Support\Facades\DB;

/**
 * Привязка поступлений из выписки к счетам.
 * Автоматически — только если совпали номер счёта в назначении, ИНН плательщика и сумма не больше остатка.
 * Всё остальное — в очередь «Неразобранные» для бухгалтера.
 */
class PaymentMatcher
{
    public function __construct(private readonly OrderWorkflow $workflow) {}

    public function autoMatch(BankTransaction $tx): bool
    {
        if ($tx->direction !== 'in' || $tx->match_status !== MatchStatus::Unmatched || ! $tx->payer_inn) {
            return false;
        }

        $purpose = $this->normalize((string) $tx->purpose);
        $candidates = Invoice::query()
            ->where('seller_entity_id', $tx->seller_entity_id)
            ->where('status', InvoiceStatus::Issued->value)
            ->whereHas('organization', fn ($q) => $q->where('inn', $tx->payer_inn))
            ->get()
            ->filter(fn (Invoice $invoice) => str_contains($purpose, $this->normalize($invoice->number)));

        if ($candidates->count() !== 1) {
            return false;
        }
        $invoice = $candidates->first();
        if (Money::compare($tx->amount, $this->remaining($invoice)) > 0) {
            return false;
        }

        $this->allocate($tx, $invoice, (string) $tx->amount, null);

        return true;
    }

    /** Ручная привязка бухгалтером. */
    public function manualMatch(BankTransaction $tx, Invoice $invoice, string $amount, Actor $actor): PaymentAllocation
    {
        if ($actor->staff === null || ! $actor->staff->can(Permissions::PAYMENTS_MATCH)) {
            throw new WorkflowException('forbidden', 'Недостаточно прав для привязки платежей.');
        }
        if ($tx->direction !== 'in') {
            throw new WorkflowException('not_incoming', 'Это исходящий платёж.');
        }
        if ($invoice->status !== InvoiceStatus::Issued || $invoice->seller_entity_id !== $tx->seller_entity_id) {
            throw new WorkflowException('invoice_not_payable', 'Счёт аннулирован или выставлен другим юрлицом.');
        }
        if (Money::compare($amount, '0') <= 0
            || Money::compare($amount, $this->unallocated($tx)) > 0
            || Money::compare($amount, $this->remaining($invoice)) > 0) {
            throw new WorkflowException('amount_invalid', 'Сумма больше нераспределённого остатка платежа или остатка по счёту.');
        }

        return $this->allocate($tx, $invoice, $amount, $actor);
    }

    public function cancelAllocation(PaymentAllocation $allocation, Actor $actor, string $reason): void
    {
        if ($actor->staff === null || ! $actor->staff->can(Permissions::PAYMENTS_MATCH)) {
            throw new WorkflowException('forbidden', 'Недостаточно прав.');
        }
        DB::transaction(function () use ($allocation, $actor, $reason) {
            $allocation->forceFill(['cancelled_at' => now(), 'cancelled_by' => $actor->id, 'cancel_reason' => $reason])->save();
            $this->refreshTransaction($allocation->bankTransaction);
            $this->workflow->recomputePayment($allocation->invoice->order, $actor);
        });
    }

    public function ignore(BankTransaction $tx, Actor $actor): void
    {
        if ($actor->staff === null || ! $actor->staff->can(Permissions::PAYMENTS_MATCH)) {
            throw new WorkflowException('forbidden', 'Недостаточно прав.');
        }
        if ($tx->allocations()->whereNull('cancelled_at')->exists()) {
            throw new WorkflowException('has_allocations', 'Сначала отмените привязки платежа.');
        }
        $tx->update(['match_status' => MatchStatus::Ignored]);
    }

    public function remaining(Invoice $invoice): string
    {
        $paid = Money::add(...$invoice->paymentAllocations()->whereNull('cancelled_at')->pluck('amount')->all());

        return Money::sub($invoice->amount, $paid);
    }

    public function unallocated(BankTransaction $tx): string
    {
        return Money::sub($tx->amount, ...$tx->allocations()->whereNull('cancelled_at')->pluck('amount')->all());
    }

    private function allocate(BankTransaction $tx, Invoice $invoice, string $amount, ?Actor $actor): PaymentAllocation
    {
        return DB::transaction(function () use ($tx, $invoice, $amount, $actor) {
            BankTransaction::query()->whereKey($tx->id)->lockForUpdate()->first();
            $allocation = PaymentAllocation::create([
                'bank_transaction_id' => $tx->id,
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'method' => $actor ? 'manual' : 'auto',
                'matched_by' => $actor?->id,
                'matched_at' => now(),
            ]);
            $this->refreshTransaction($tx);
            $this->workflow->recomputePayment($invoice->order, $actor ?? Actor::integration());

            return $allocation;
        });
    }

    private function refreshTransaction(BankTransaction $tx): void
    {
        $left = $this->unallocated($tx);
        $status = match (true) {
            Money::compare($left, '0') <= 0 => MatchStatus::Matched,
            Money::compare($left, $tx->amount) < 0 => MatchStatus::PartiallyMatched,
            default => MatchStatus::Unmatched,
        };
        $tx->update(['match_status' => $status]);
    }

    private function normalize(string $text): string
    {
        return mb_strtoupper(preg_replace('/\s+/u', '', $text));
    }
}
