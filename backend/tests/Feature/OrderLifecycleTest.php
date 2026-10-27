<?php

namespace Tests\Feature;

use App\Enums\DocumentsSummary;
use App\Enums\InvoiceStatus;
use App\Enums\MatchStatus;
use App\Enums\OfferStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProcurementStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\BankTransaction;
use App\Models\StatusHistory;
use App\Services\Bank\BankStatementImporter;
use App\Services\ProcurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsWorld;
use Tests\TestCase;

/** Сценарий 1 из docs/02-arhitektura/02-statusy.md: обычный заказ с предоплатой и самовывозом. */
class OrderLifecycleTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
    }

    public function test_full_prepaid_order_goes_from_cart_to_completed(): void
    {
        $order = $this->placeOrder('2');
        $this->assertSame(OrderStatus::Created, $order->status);
        $this->assertSame(PaymentStatus::NotInvoiced, $order->payment_status);
        $this->assertSame('ЗАК-TST-'.now()->year.'-000001', $order->number);
        $this->assertNotNull($order->currentDate(\App\Enums\DateKind::Preliminary));

        // Менеджер: предложение по цене каталога 95 000 ₽/т × 2 т
        $this->workflow()->takeForReview($order, $this->actor('manager'));
        $offer = $this->workflow()->createOfferDraft($order->refresh(), $this->actor('manager'));
        $this->assertSame('190000.00', (string) $offer->grand_total);
        $this->assertSame('31666.67', (string) $offer->goods_vat);
        $this->workflow()->sendOffer($offer, $this->actor('manager'));
        $this->assertSame(OrderStatus::AwaitingTerms, $order->refresh()->status);

        // Клиент принимает → счёт
        $this->workflow()->acceptOffer($offer->refresh(), $this->client, '127.0.0.1', 'test');
        $order->refresh();
        $this->assertSame(OrderStatus::AwaitingPayment, $order->status);
        $this->assertSame(PaymentStatus::AwaitingPayment, $order->payment_status);
        $this->assertSame(OfferStatus::Accepted, $offer->refresh()->status);
        $invoice = $order->invoices()->sole();
        $this->assertSame('190000.00', (string) $invoice->amount);

        // Деньги пришли в выписке → автопривязка → оплата подтверждена
        $stats = app(BankStatementImporter::class)->importFile($this->seller, $this->statement('190000.00', "Оплата по счёту {$invoice->number} в т.ч. НДС"));
        $this->assertSame(['created' => 1, 'duplicates' => 0, 'matched' => 1, 'skipped' => 0], $stats);
        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(OrderStatus::PaymentConfirmed, $order->status);

        // Закупка
        $procurement = app(ProcurementService::class);
        $po = $procurement->create($this->supplier, $this->region, $this->actor('procurement'));
        $line = $offer->lines()->first();
        $procurement->addLine($po, ['description' => 'Круг 20', 'qty' => '2', 'unit' => 't', 'purchase_price' => '80000'], [$line->id => '2'], $this->actor('procurement'));
        $this->assertSame('160000.00', (string) $po->refresh()->total);
        $procurement->send($po, $this->actor('procurement'));
        $this->assertSame(OrderStatus::Procurement, $order->refresh()->status);
        $this->assertSame(ProcurementStatus::InProgress, $order->procurement_status);

        $procurement->confirm($po->refresh(), $this->actor('procurement'), 'Счёт поставщика 77', now()->addDays(5)->toDateString());
        $this->assertSame(OrderStatus::AwaitingReadiness, $order->refresh()->status);
        $this->assertSame(ProcurementStatus::Confirmed, $order->procurement_status);

        $procurement->advance($po->refresh(), PurchaseOrderStatus::Ready, $this->actor('procurement'));
        $this->assertSame(OrderStatus::ReadyToShip, $order->refresh()->status);

        // Самовывоз → доставлен → завершён (документов в заказе пока нет)
        $this->assertSame(DocumentsSummary::None, $order->documents_status);
        $this->workflow()->markPickedUp($order, $this->actor('logistics'), 'Иванов И. И., доверенность № 5');
        $this->assertSame(OrderStatus::Completed, $order->refresh()->status);

        // Вся история статусов записана
        $this->assertGreaterThanOrEqual(10, StatusHistory::where('entity_type', 'order')->where('entity_id', $order->id)->count());
    }

    public function test_duplicate_statement_is_ignored_and_foreign_inn_is_not_auto_matched(): void
    {
        [$order] = $this->acceptedOrder();
        $invoice = $order->invoices()->sole();
        $importer = app(BankStatementImporter::class);

        $file = $this->statement('190000.00', "Оплата {$invoice->number}", '9999999999');
        $this->assertSame(0, $importer->importFile($this->seller, $file)['matched']);
        $this->assertSame(1, $importer->importFile($this->seller, $file)['duplicates']);
        $this->assertSame(MatchStatus::Unmatched, BankTransaction::sole()->match_status);
        $this->assertSame(OrderStatus::AwaitingPayment, $order->refresh()->status);
    }

    public function test_partial_payment_does_not_open_procurement(): void
    {
        [$order] = $this->acceptedOrder();
        $invoice = $order->invoices()->sole();
        app(BankStatementImporter::class)->importFile($this->seller, $this->statement('100000.00', "Оплата {$invoice->number}"));

        $order->refresh();
        $this->assertSame(PaymentStatus::PartiallyPaid, $order->payment_status);
        $this->assertSame(OrderStatus::AwaitingPayment, $order->status);
    }

    public function test_cancel_before_payment_annuls_invoice(): void
    {
        [$order] = $this->acceptedOrder();
        $this->workflow()->cancel($order, \App\Support\Actor::user($this->client), 'Передумали');

        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
        $this->assertSame(InvoiceStatus::Cancelled, $order->invoices()->sole()->status);
    }
}
