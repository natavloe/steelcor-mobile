<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PurchaseOrderStatus;
use App\Filament\Resources\BankTransactions\Pages\ListBankTransactions;
use App\Filament\Resources\PurchaseOrders\Pages\ViewPurchaseOrder;
use App\Services\ProcurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\BuildsWorld;
use Tests\TestCase;

/** Бухгалтер загружает выписку, закупщик распределяет закупку — через интерфейс админки. */
class AdminFinanceAndProcurementTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        Storage::fake('local');
    }

    public function test_accountant_imports_statement_and_order_becomes_paid(): void
    {
        [$order] = $this->acceptedOrder();
        $invoice = $order->invoices()->sole();
        $file = UploadedFile::fake()->createWithContent('kl_to_1c.txt', mb_convert_encoding($this->statement('190000.00', "Оплата по счёту {$invoice->number}"), 'Windows-1251', 'UTF-8'));

        $this->actingAs($this->staff['accountant'], 'staff');
        Livewire::test(ListBankTransactions::class)
            ->callAction('import', ['seller_entity_id' => $this->seller->id, 'file' => $file])
            ->assertHasNoActionErrors();

        $this->assertSame(OrderStatus::PaymentConfirmed, $order->refresh()->status);
    }

    public function test_procurement_adds_allocated_line_and_cannot_send_unpaid(): void
    {
        [$order, $offer] = $this->acceptedOrder();
        $po = app(ProcurementService::class)->create($this->supplier, $this->region, $this->actor('procurement'));
        $lineId = $offer->lines()->first()->id;

        $this->actingAs($this->staff['procurement'], 'staff');
        Livewire::test(ViewPurchaseOrder::class, ['record' => $po->getRouteKey()])
            ->callAction('addLine', [
                'description' => 'Круг 20 мм 40Х', 'qty' => '2', 'unit' => 't', 'purchase_price' => '81000',
                'allocations' => [['offer_line_id' => $lineId, 'qty' => '2']],
            ])
            ->assertHasNoActionErrors()
            ->callAction('send');

        $po->refresh();
        $this->assertSame('162000.00', (string) $po->total);
        $this->assertSame(PurchaseOrderStatus::Draft, $po->status, 'Неоплаченный заказ не даёт отправить закупку');
        $this->assertSame(1, $po->lines()->sole()->allocations()->count());
    }
}
