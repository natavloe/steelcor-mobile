<?php

namespace App\Services;

use App\Enums\DateKind;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Enums\PurchaseOrderStatus;
use App\Exceptions\WorkflowException;
use App\Models\Allocation;
use App\Models\Issue;
use App\Models\OfferLine;
use App\Models\Order;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Region;
use App\Models\Supplier;
use App\Support\Actor;
use App\Support\Money;
use App\Support\Permissions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ВНУТРЕННЕЕ: закупки у поставщиков. Один заказ клиента может закупаться у нескольких поставщиков,
 * одна закупка может обеспечивать несколько заказов (таблица allocations).
 */
class ProcurementService
{
    /** Закупки в этих статусах больше не обеспечивают заказы. */
    private const DEAD = [PurchaseOrderStatus::Cancelled, PurchaseOrderStatus::Refused];

    /** Допустимые переходы статуса закупки. */
    private const TRANSITIONS = [
        'draft' => ['sent', 'cancelled'],
        'sent' => ['confirmed', 'refused', 'cancelled'],
        'confirmed' => ['price_changed', 'paid_to_supplier', 'awaiting_ready', 'ready', 'cancelled'],
        'price_changed' => ['confirmed', 'cancelled'],
        'paid_to_supplier' => ['awaiting_ready', 'ready'],
        'awaiting_ready' => ['ready'],
        'ready' => ['shipped'],
    ];

    public function __construct(
        private readonly OrderWorkflow $workflow,
        private readonly DocumentNumberGenerator $numbers,
    ) {}

    public function create(Supplier $supplier, Region $region, Actor $actor, ?string $note = null): PurchaseOrder
    {
        $this->assertStaff($actor);
        if ($region->seller_entity_id === null) {
            throw new WorkflowException('region_not_configured', 'У региона не указано юрлицо-продавец.');
        }

        return PurchaseOrder::create([
            'number' => $this->numbers->next('purchase_order', $region->seller_entity_id, $region),
            'supplier_id' => $supplier->id,
            'region_id' => $region->id,
            'status' => PurchaseOrderStatus::Draft,
            'ship_from_address' => $supplier->ship_from_address,
            'created_by' => $actor->id,
            'note' => $note,
        ]);
    }

    /**
     * Добавить строку закупки и распределить её на строки согласованных предложений клиентов.
     *
     * @param  array{product_id?:int|null, description:string, qty:string, unit:string, purchase_price:string}  $data
     * @param  array<int,string>  $allocations  offer_line_id => количество
     */
    public function addLine(PurchaseOrder $po, array $data, array $allocations, Actor $actor): PurchaseOrderLine
    {
        $this->assertStaff($actor);
        if ($po->status !== PurchaseOrderStatus::Draft) {
            throw new WorkflowException('po_not_draft', 'Строки можно добавлять только в черновик закупки.');
        }

        return DB::transaction(function () use ($po, $data, $allocations) {
            $line = $po->lines()->create([
                'product_id' => $data['product_id'] ?? null,
                'description' => $data['description'],
                'qty' => $data['qty'],
                'unit' => $data['unit'],
                'purchase_price' => $data['purchase_price'],
            ]);

            $allocated = '0';
            foreach ($allocations as $offerLineId => $qty) {
                $this->allocate($line, OfferLine::findOrFail($offerLineId), (string) $qty);
                $allocated = Money::round(Money::of($allocated)->plus(Money::of($qty)), 4);
            }
            if (Money::compare($allocated, $line->qty) > 0) {
                throw new WorkflowException('over_allocated', 'Распределено больше, чем закупается.');
            }
            $this->recalcTotal($po);

            return $line;
        });
    }

    public function allocate(PurchaseOrderLine $line, OfferLine $offerLine, string $qty): Allocation
    {
        $offer = $offerLine->offer;
        $order = $offer->order;
        if ($order->agreed_offer_id !== $offer->id) {
            throw new WorkflowException('offer_not_agreed', "Строка не из согласованного предложения заказа {$order->number}.");
        }
        if ($order->region_id !== $line->purchaseOrder->region_id) {
            throw new WorkflowException('region_mismatch', 'Заказ и закупка из разных регионов.');
        }
        if ($offerLine->unit !== $line->unit) {
            throw new WorkflowException('unit_mismatch', 'Единицы измерения строки заказа и закупки различаются.');
        }

        $alreadyCovered = Money::add(...Allocation::query()
            ->where('offer_line_id', $offerLine->id)
            ->whereHas('purchaseOrderLine.purchaseOrder', fn ($q) => $q->whereNotIn('status', array_map(fn ($s) => $s->value, self::DEAD)))
            ->pluck('qty')->all());
        if (Money::compare(Money::of($alreadyCovered)->plus(Money::of($qty)), Money::of($offerLine->qty)) > 0) {
            throw new WorkflowException('over_allocated', "По строке «{$offerLine->name}» заказа {$order->number} распределено больше, чем заказал клиент.");
        }

        return Allocation::create(['offer_line_id' => $offerLine->id, 'purchase_order_line_id' => $line->id, 'qty' => $qty]);
    }

    /** Замок: отправить поставщику можно, только если все связанные заказы оплачены (или одобрена отсрочка). */
    public function send(PurchaseOrder $po, Actor $actor): void
    {
        $this->assertStaff($actor);
        $orders = $this->orders($po);
        if ($orders->isEmpty()) {
            throw new WorkflowException('po_empty', 'Закупка не связана ни с одним заказом клиента.');
        }
        foreach ($orders as $order) {
            if (! $this->workflow->isClearedForProcurement($order)) {
                throw new WorkflowException('order_not_paid', "Заказ {$order->number} не оплачен (и отсрочка не одобрена) — закупку отправлять нельзя.");
            }
        }
        $this->transition($po, PurchaseOrderStatus::Sent, $actor);
    }

    public function confirm(PurchaseOrder $po, Actor $actor, ?string $supplierRef, ?string $expectedReadyDate): void
    {
        $this->assertStaff($actor);
        $po->forceFill(['supplier_ref' => $supplierRef, 'expected_ready_date' => $expectedReadyDate, 'confirmed_at' => now()]);
        $this->transition($po, PurchaseOrderStatus::Confirmed, $actor);

        if ($expectedReadyDate) {
            foreach ($this->orders($po) as $order) {
                $this->workflow->setDate($order, DateKind::SupplierConfirmed, $expectedReadyDate, null, $actor, "Закупка {$po->number}");
            }
        }
    }

    /** Поставщик изменил цену: заказ клиента не меняется, по каждому создаётся инцидент. */
    public function changePrice(PurchaseOrderLine $line, string $newPrice, string $reason, Actor $actor): void
    {
        $this->assertStaff($actor);
        $po = $line->purchaseOrder()->firstOrFail();
        DB::transaction(function () use ($line, $po, $newPrice, $reason, $actor) {
            $old = (string) $line->purchase_price;
            $line->update(['purchase_price' => $newPrice, 'price_changed' => true]);
            $this->recalcTotal($po);
            $this->transition($po, PurchaseOrderStatus::PriceChanged, $actor, $reason);
            $this->openIssues($po, IssueType::PriceChanged, "Поставщик изменил цену «{$line->description}»: {$old} → {$newPrice}. {$reason}", $actor);
        });
    }

    /** Цена согласована внутри компании (например, компания берёт разницу на себя) — цена клиента не меняется. */
    public function acceptPriceChange(PurchaseOrder $po, Actor $actor, string $note): void
    {
        $this->assertStaff($actor);
        $this->transition($po, PurchaseOrderStatus::Confirmed, $actor, $note);
    }

    public function refuse(PurchaseOrder $po, Actor $actor, string $reason): void
    {
        $this->assertStaff($actor);
        DB::transaction(function () use ($po, $actor, $reason) {
            $this->transition($po, PurchaseOrderStatus::Refused, $actor, $reason);
            $this->openIssues($po, IssueType::SupplierRefused, "Поставщик отказал по закупке {$po->number}: {$reason}", $actor);
        });
    }

    public function advance(PurchaseOrder $po, PurchaseOrderStatus $to, Actor $actor, ?string $note = null): void
    {
        $this->assertStaff($actor);
        DB::transaction(function () use ($po, $to, $actor, $note) {
            if ($to === PurchaseOrderStatus::PaidToSupplier) {
                $po->supplier_payment_status = 'paid';
            }
            $this->transition($po, $to, $actor, $note);
            if ($to === PurchaseOrderStatus::Shipped) {
                foreach ($this->orders($po) as $order) {
                    $this->workflow->setDate($order, DateKind::ActualShipment, now()->toDateString(), null, $actor, "Закупка {$po->number}");
                }
            }
        });
    }

    /** @return Collection<int, Order> */
    public function orders(PurchaseOrder $po): Collection
    {
        return Order::query()
            ->whereHas('agreedOffer.lines.allocations.purchaseOrderLine', fn ($q) => $q->where('purchase_order_id', $po->id))
            ->get();
    }

    private function transition(PurchaseOrder $po, PurchaseOrderStatus $to, Actor $actor, ?string $reason = null): void
    {
        $po->status = PurchaseOrder::query()->whereKey($po->id)->lockForUpdate()->value('status');
        $allowed = self::TRANSITIONS[$po->status->value] ?? [];
        if (! in_array($to->value, $allowed, true)) {
            throw new WorkflowException('wrong_status', "Закупку в статусе «{$po->status->label()}» нельзя перевести в «{$to->label()}».");
        }
        $po->setStatus('status', $to, $actor, $reason);
        foreach ($this->orders($po) as $order) {
            $this->workflow->recomputeProcurement($order, $actor);
        }
    }

    private function openIssues(PurchaseOrder $po, IssueType $type, string $description, Actor $actor): void
    {
        foreach ($this->orders($po) as $order) {
            Issue::create([
                'order_id' => $order->id,
                'purchase_order_id' => $po->id,
                'type' => $type,
                'status' => IssueStatus::Open,
                'description' => $description,
                'created_by' => $actor->id,
            ]);
        }
    }

    private function recalcTotal(PurchaseOrder $po): void
    {
        $total = '0';
        foreach ($po->lines()->get() as $line) {
            $total = Money::add($total, Money::mul($line->qty, $line->purchase_price));
        }
        $po->update(['total' => $total]);
    }

    private function assertStaff(Actor $actor): void
    {
        if ($actor->staff === null || ! $actor->staff->can(Permissions::PROCUREMENT_MANAGE)) {
            throw new WorkflowException('forbidden', 'Недостаточно прав для работы с закупками.');
        }
    }
}
