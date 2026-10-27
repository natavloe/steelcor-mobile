<?php

namespace Tests\Feature;

use App\Enums\IssueType;
use App\Enums\OfferStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProcurementStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\RefundStatus;
use App\Enums\VerificationStatus;
use App\Exceptions\WorkflowException;
use App\Models\BankTransaction;
use App\Models\PricingRule;
use App\Services\Bank\BankStatementImporter;
use App\Services\Bank\PaymentMatcher;
use App\Services\ProcurementService;
use App\Services\RefundService;
use App\Support\Actor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsWorld;
use Tests\TestCase;

/** «Замки» между статусами и права. */
class WorkflowGuardsTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
    }

    private function assertBlocked(string $code, callable $action): void
    {
        try {
            $action();
            $this->fail("Ожидалась блокировка «{$code}»");
        } catch (WorkflowException $e) {
            $this->assertSame($code, $e->errorCode, $e->getMessage());
        }
    }

    private function pay(string $amount, string $invoiceNumber): void
    {
        app(BankStatementImporter::class)->importFile($this->seller, $this->statement($amount, "Оплата {$invoiceNumber}", number: (string) random_int(1, 99999)));
    }

    public function test_purchase_cannot_be_sent_before_client_payment(): void
    {
        [, $offer] = $this->acceptedOrder();
        $procurement = app(ProcurementService::class);
        $po = $procurement->create($this->supplier, $this->region, $this->actor('procurement'));
        $procurement->addLine($po, ['description' => 'Круг', 'qty' => '2', 'unit' => 't', 'purchase_price' => '80000'], [$offer->lines()->first()->id => '2'], $this->actor('procurement'));

        $this->assertBlocked('order_not_paid', fn () => $procurement->send($po, $this->actor('procurement')));
    }

    public function test_client_payment_notice_does_not_mark_order_paid(): void
    {
        [$order] = $this->acceptedOrder();
        $this->workflow()->notifyPaid($order, $this->client, null, 'Оплатили сегодня');

        $order->refresh();
        $this->assertSame(PaymentStatus::AwaitingConfirmation, $order->payment_status);
        $this->assertSame(OrderStatus::AwaitingPayment, $order->status);
    }

    public function test_expired_offer_cannot_be_accepted(): void
    {
        [, $offer] = $this->orderWithSentOffer();
        $offer->update(['valid_until' => now()->subMinute()]);

        $this->assertBlocked('offer_expired', fn () => $this->workflow()->acceptOffer($offer->refresh(), $this->client));
        $this->assertSame(1, $this->workflow()->expireOffers());
        $this->assertSame(OfferStatus::Expired, $offer->refresh()->status);
        $this->assertSame(OrderStatus::UnderReview, $offer->order->status);
    }

    public function test_offer_needs_verified_client_and_approved_seller_requisites(): void
    {
        $order = $this->placeOrder();
        $this->workflow()->takeForReview($order, $this->actor('manager'));
        $offer = $this->workflow()->createOfferDraft($order->refresh(), $this->actor('manager'));

        $this->org->update(['verification_status' => VerificationStatus::Unverified]);
        $this->assertBlocked('organization_not_verified', fn () => $this->workflow()->sendOffer($offer, $this->actor('manager')));

        $this->org->update(['verification_status' => VerificationStatus::Verified]);
        $this->seller->requisites()->update(['status' => 'draft']);
        $this->assertBlocked('no_approved_requisites', fn () => $this->workflow()->sendOffer($offer->refresh(), $this->actor('manager')));
    }

    public function test_low_margin_offer_needs_head_approval(): void
    {
        PricingRule::create(['scope' => 'global', 'min_margin_percent' => '10']);
        $order = $this->placeOrder();
        $this->workflow()->takeForReview($order, $this->actor('manager'));
        $offer = $this->workflow()->createOfferDraft($order->refresh(), $this->actor('manager'));
        $offer->lines()->first()->cost()->create(['purchase_price' => '90000']); // маржа ≈ 5%

        $this->assertBlocked('low_margin', fn () => $this->workflow()->sendOffer($offer, $this->actor('manager')));
        $this->assertSame(OfferStatus::PendingApproval, $offer->refresh()->status);
        $this->assertBlocked('forbidden', fn () => $this->workflow()->approveLowMargin($offer, $this->actor('manager')));

        $this->workflow()->approveLowMargin($offer, $this->actor('head'));
        $this->workflow()->sendOffer($offer->refresh(), $this->actor('manager'));
        $this->assertSame(OfferStatus::Sent, $offer->refresh()->status);
    }

    public function test_viewer_role_cannot_accept_offer_and_client_cannot_cancel_paid_order(): void
    {
        [$order, $offer] = $this->orderWithSentOffer();
        $viewer = \App\Models\User::create(['phone' => '+70000000009']);
        $this->org->members()->create(['user_id' => $viewer->id, 'role' => 'viewer']);
        $this->assertBlocked('forbidden', fn () => $this->workflow()->acceptOffer($offer, $viewer));

        $this->workflow()->acceptOffer($offer, $this->client);
        $this->pay('190000.00', $order->invoices()->sole()->number);
        $this->assertSame(OrderStatus::PaymentConfirmed, $order->refresh()->status);
        $this->assertBlocked('cancel_needs_manager', fn () => $this->workflow()->cancel($order, Actor::user($this->client), 'Не нужно'));
        $this->assertBlocked('issue_required', fn () => $this->workflow()->cancel($order, $this->actor('manager'), 'Не нужно'));
    }

    public function test_deferral_opens_procurement_only_within_credit_limit(): void
    {
        $this->org->update(['payment_terms' => 'deferral', 'deferral_days' => 30, 'credit_limit' => '100000']);
        [$order] = $this->acceptedOrder(); // 190 000 > лимита

        $this->assertBlocked('forbidden', fn () => $this->workflow()->approveDeferral($order, $this->actor('manager'), 'Постоянный клиент'));
        $this->assertBlocked('credit_limit_exceeded', fn () => $this->workflow()->approveDeferral($order, $this->actor('head'), 'Постоянный клиент'));

        $this->org->update(['credit_limit' => '500000']);
        $this->workflow()->approveDeferral($order->refresh(), $this->actor('head'), 'Постоянный клиент');
        $order->refresh();
        $this->assertSame(OrderStatus::PaymentConfirmed, $order->status);
        $this->assertSame(PaymentStatus::AwaitingPayment, $order->payment_status);
    }

    /** Сценарий 2: поставщик поднял цену после оплаты. */
    public function test_supplier_price_change_goes_through_reagreement_with_additional_invoice(): void
    {
        [$order, $offer] = $this->acceptedOrder();
        $this->pay('190000.00', $order->invoices()->sole()->number);
        $procurement = app(ProcurementService::class);
        $po = $procurement->create($this->supplier, $this->region, $this->actor('procurement'));
        $poLine = $procurement->addLine($po, ['description' => 'Круг', 'qty' => '2', 'unit' => 't', 'purchase_price' => '80000'], [$offer->lines()->first()->id => '2'], $this->actor('procurement'));
        $procurement->send($po, $this->actor('procurement'));
        $procurement->confirm($po->refresh(), $this->actor('procurement'), null, null);

        $procurement->changePrice($poLine, '99000', 'Рост цен на заводе', $this->actor('procurement'));
        $order->refresh();
        $this->assertSame(ProcurementStatus::Problem, $order->procurement_status);
        $this->assertSame(IssueType::PriceChanged, $order->issues()->sole()->type);
        $this->assertSame('190000.00', (string) $order->agreedOffer->grand_total, 'Цена клиента не меняется сама');

        // Повторное согласование: v2 дороже → счёт на доплату
        $this->workflow()->startReagreement($order, $this->actor('manager'), 'Поставщик поднял цену');
        $v2 = $this->workflow()->createOfferDraft($order->refresh(), $this->actor('manager'), 'Рост закупочной цены');
        $v2->lines()->first()->update(['unit_price' => '105000']);
        $this->workflow()->sendOffer($v2, $this->actor('manager'));
        $this->workflow()->acceptOffer($v2->refresh(), $this->client);

        $order->refresh();
        $this->assertSame(OfferStatus::Superseded, $offer->refresh()->status);
        $this->assertSame(2, $order->invoices()->count());
        $extra = $order->invoices()->where('kind', 'additional')->sole();
        $this->assertSame('20000.00', (string) $extra->amount);
        $this->assertSame(PaymentStatus::PartiallyPaid, $order->payment_status);
        $this->assertSame(OrderStatus::AwaitingPayment, $order->status);

        $this->pay('20000.00', $extra->number);
        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertFalse($order->is_reagreement);
        $this->assertNotSame(OrderStatus::AwaitingPayment, $order->status);

        $procurement->acceptPriceChange($po->refresh(), $this->actor('head'), 'Клиент доплатил');
        $this->assertSame(PurchaseOrderStatus::Confirmed, $po->refresh()->status);
        $this->assertSame(OrderStatus::AwaitingReadiness, $order->refresh()->status);
    }

    public function test_refund_needs_two_people_and_outgoing_bank_payment(): void
    {
        [$order] = $this->acceptedOrder();
        $this->pay('190000.00', $order->invoices()->sole()->number);
        $refunds = app(RefundService::class);

        $refund = $refunds->request($order, null, '190000.00', 'п. 7.3 договора, соглашение от 27.10.2026', $this->actor('accountant'));
        $this->assertBlocked('same_person', fn () => $refunds->approve($refund, $this->actor('accountant')));
        $refunds->approve($refund, $this->actor('accountant2'));
        $this->assertSame(PaymentStatus::Refund, $order->refresh()->payment_status);

        $incoming = BankTransaction::sole();
        $this->assertBlocked('tx_mismatch', fn () => $refunds->complete($refund->refresh(), $incoming, $this->actor('accountant2')));

        $out = BankTransaction::create([
            'seller_entity_id' => $this->seller->id, 'source' => 'statement_file', 'external_id' => 'out-1', 'direction' => 'out',
            'op_date' => now()->toDateString(), 'amount' => '190000.00', 'payer_inn' => '0000000002',
        ]);
        $refunds->complete($refund->refresh(), $out, $this->actor('accountant2'));
        $this->assertSame(RefundStatus::Completed, $refund->refresh()->status);
        $this->assertSame(PaymentStatus::Refunded, $order->refresh()->payment_status);
    }

    public function test_manual_match_respects_amounts_and_rights(): void
    {
        [$order] = $this->acceptedOrder();
        $invoice = $order->invoices()->sole();
        app(BankStatementImporter::class)->importFile($this->seller, $this->statement('190000.00', 'Оплата по договору'));
        $tx = BankTransaction::sole();
        $matcher = app(PaymentMatcher::class);

        $this->assertBlocked('forbidden', fn () => $matcher->manualMatch($tx, $invoice, '190000.00', $this->actor('manager')));
        $this->assertBlocked('amount_invalid', fn () => $matcher->manualMatch($tx, $invoice, '200000.00', $this->actor('accountant')));
        $allocation = $matcher->manualMatch($tx, $invoice, '190000.00', $this->actor('accountant'));
        $this->assertSame(OrderStatus::PaymentConfirmed, $order->refresh()->status);

        $matcher->cancelAllocation($allocation, $this->actor('accountant'), 'Ошибочная привязка');
        $order->refresh();
        $this->assertSame(PaymentStatus::AwaitingPayment, $order->payment_status);
        $this->assertSame(OrderStatus::AwaitingPayment, $order->status, 'До закупки заказ возвращается в ожидание оплаты');
    }
}
