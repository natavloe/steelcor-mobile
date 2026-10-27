<?php

namespace App\Services;

use App\Enums\RefundStatus;
use App\Exceptions\WorkflowException;
use App\Models\BankTransaction;
use App\Models\Issue;
use App\Models\Order;
use App\Models\Refund;
use App\Support\Actor;
use App\Support\Money;
use App\Support\Permissions;
use Illuminate\Support\Facades\DB;

/**
 * Возвраты денег. Сумму и основание определяет сотрудник по договору (О-13) — система сама ничего не удерживает.
 * Возврат считается выполненным только по исходящему платежу в выписке банка.
 */
class RefundService
{
    public function __construct(private readonly OrderWorkflow $workflow) {}

    public function request(Order $order, ?Issue $issue, string $amount, string $basis, Actor $actor, ?array $recipientAccount = null): Refund
    {
        $this->assertCan($actor, Permissions::REFUNDS_REQUEST);
        if (Money::compare($amount, '0') <= 0) {
            throw new WorkflowException('amount_invalid', 'Сумма возврата должна быть больше нуля.');
        }

        return Refund::create([
            'order_id' => $order->id,
            'issue_id' => $issue?->id,
            'amount' => $amount,
            'basis' => $basis,
            'status' => RefundStatus::Requested,
            'requested_by' => $actor->id,
            'recipient_account' => $recipientAccount,
        ]);
    }

    /** Утверждает другой сотрудник (принцип двух ключей). */
    public function approve(Refund $refund, Actor $actor): void
    {
        $this->assertCan($actor, Permissions::REFUNDS_APPROVE);
        if ($refund->requested_by === $actor->id) {
            throw new WorkflowException('same_person', 'Возврат утверждает не тот сотрудник, который его запросил.');
        }
        $this->transition($refund, RefundStatus::Requested, RefundStatus::Approved, $actor);
    }

    public function reject(Refund $refund, Actor $actor, string $reason): void
    {
        $this->assertCan($actor, Permissions::REFUNDS_APPROVE);
        $this->transition($refund, RefundStatus::Requested, RefundStatus::Rejected, $actor, $reason);
    }

    /** Привязать исходящий платёж из выписки: возврат выполнен. */
    public function complete(Refund $refund, BankTransaction $tx, Actor $actor): void
    {
        $this->assertCan($actor, Permissions::REFUNDS_APPROVE);
        if ($tx->direction !== 'out' || Money::compare($tx->amount, $refund->amount) !== 0) {
            throw new WorkflowException('tx_mismatch', 'Нужен исходящий платёж из выписки на сумму возврата.');
        }
        if (Refund::where('bank_transaction_id', $tx->id)->exists()) {
            throw new WorkflowException('tx_used', 'Этот платёж уже привязан к другому возврату.');
        }
        DB::transaction(function () use ($refund, $tx, $actor) {
            $refund->bank_transaction_id = $tx->id;
            $this->transition($refund, [RefundStatus::Approved, RefundStatus::Sent], RefundStatus::Completed, $actor);
            $tx->update(['match_status' => 'matched']);
        });
    }

    private function transition(Refund $refund, RefundStatus|array $from, RefundStatus $to, Actor $actor, ?string $reason = null): void
    {
        $from = is_array($from) ? $from : [$from];
        if (! in_array($refund->status, $from, true)) {
            throw new WorkflowException('wrong_status', "Возврат в статусе «{$refund->status->label()}».");
        }
        if ($to === RefundStatus::Approved) {
            $refund->approved_by = $actor->id;
        }
        $refund->setStatus('status', $to, $actor, $reason);
        $this->workflow->recomputePayment($refund->order, $actor);
    }

    private function assertCan(Actor $actor, string $permission): void
    {
        if ($actor->staff === null || ! $actor->staff->can($permission)) {
            throw new WorkflowException('forbidden', 'Недостаточно прав.');
        }
    }
}
