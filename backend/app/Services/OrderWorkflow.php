<?php

namespace App\Services;

use App\Enums\ActorType;
use App\Enums\ApprovalStatus;
use App\Enums\Availability;
use App\Enums\DateKind;
use App\Enums\DocumentsSummary;
use App\Enums\InvoiceStatus;
use App\Enums\IssueStatus;
use App\Enums\MemberRole;
use App\Enums\OfferStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTerms;
use App\Enums\ProcurementStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\ReceiveMethod;
use App\Enums\RefundStatus;
use App\Enums\ShipmentStatus;
use App\Enums\VerificationStatus;
use App\Exceptions\WorkflowException;
use App\Models\Cart;
use App\Models\Invoice;
use App\Models\Offer;
use App\Models\OfferLine;
use App\Models\Order;
use App\Models\OrderDate;
use App\Models\Organization;
use App\Models\PaymentAllocation;
use App\Models\Region;
use App\Models\SellerRequisites;
use App\Models\StatusHistory;
use App\Models\User;
use App\Support\Actor;
use App\Support\Money;
use App\Support\Permissions;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

/**
 * Все переходы статусов заказа и их «замки».
 * Документ: docs/02-arhitektura/02-statusy.md.
 */
class OrderWorkflow
{
    /** Статусы, из которых возможно повторное согласование (после оплаты, до готовности). */
    private const REAGREEMENT_FROM = [
        OrderStatus::PaymentConfirmed, OrderStatus::Procurement, OrderStatus::SupplierConfirmed, OrderStatus::AwaitingReadiness,
    ];

    /** Клиент может сам отменить заказ только на этих этапах (до оплаты). */
    private const CLIENT_CANCELLABLE = [
        OrderStatus::Created, OrderStatus::UnderReview, OrderStatus::AwaitingTerms, OrderStatus::AwaitingPayment,
    ];

    public function __construct(
        private readonly DocumentNumberGenerator $numbers,
        private readonly OfferCalculator $calculator,
        private readonly UnitConverter $units,
    ) {}

    // ---------------------------------------------------------------- создание

    /**
     * Клиент оформляет заказ из корзины.
     *
     * @param  array{receive_method:string, delivery_address_id?:int|null, pickup_point_id?:int|null, desired_date_from?:string|null,
     *               desired_date_to?:string|null, desired_time_slot?:string|null, contact_name?:string|null, contact_phone?:string|null,
     *               client_comment?:string|null}  $data
     */
    public function createFromCart(User $user, Organization $organization, Region $region, Cart $cart, array $data, ?string $idempotencyKey = null): Order
    {
        if ($idempotencyKey !== null && ($existing = Order::where('idempotency_key', $idempotencyKey)->first())) {
            if ($existing->created_by_user_id !== $user->id) {
                throw new WorkflowException('idempotency_conflict', 'Ключ повтора уже использован.');
            }

            return $existing;
        }

        $this->assertMemberRole($user, $organization, [MemberRole::Owner, MemberRole::Buyer]);

        if (! $region->is_active || $region->seller_entity_id === null) {
            throw new WorkflowException('region_inactive', 'Приём заказов в этом регионе пока не открыт.');
        }

        $items = $cart->items()->with('product.regionProducts')->get();
        if ($items->isEmpty()) {
            throw new WorkflowException('cart_empty', 'Корзина пуста.');
        }

        $method = ReceiveMethod::from($data['receive_method']);
        if ($method === ReceiveMethod::Delivery) {
            $addressOk = ! empty($data['delivery_address_id'])
                && $organization->addresses()->whereKey($data['delivery_address_id'])->where('is_archived', false)->exists();
            if (! $addressOk) {
                throw new WorkflowException('address_required', 'Укажите адрес доставки.');
            }
        } else {
            $pointOk = ! empty($data['pickup_point_id'])
                && $region->pickupPoints()->whereKey($data['pickup_point_id'])->where('is_active', true)->where('is_public', true)->exists();
            if (! $pointOk) {
                throw new WorkflowException('pickup_point_required', 'Выберите пункт самовывоза.');
            }
        }

        return DB::transaction(function () use ($user, $organization, $region, $cart, $items, $data, $method, $idempotencyKey) {
            $order = Order::create([
                'number' => $this->numbers->next('order', $region->seller_entity_id, $region),
                'region_id' => $region->id,
                'seller_entity_id' => $region->seller_entity_id,
                'organization_id' => $organization->id,
                'created_by_user_id' => $user->id,
                'manager_id' => $organization->manager_id,
                'status' => OrderStatus::Created,
                'payment_status' => PaymentStatus::NotInvoiced,
                'procurement_status' => ProcurementStatus::NotStarted,
                'delivery_status' => ShipmentStatus::NotArranged,
                'documents_status' => DocumentsSummary::None,
                'receive_method' => $method,
                'delivery_address_id' => $method === ReceiveMethod::Delivery ? $data['delivery_address_id'] : null,
                'pickup_point_id' => $method === ReceiveMethod::Pickup ? $data['pickup_point_id'] : null,
                'desired_date_from' => $data['desired_date_from'] ?? null,
                'desired_date_to' => $data['desired_date_to'] ?? null,
                'desired_time_slot' => $data['desired_time_slot'] ?? null,
                'contact_name' => $data['contact_name'] ?? $user->name,
                'contact_phone' => $data['contact_phone'] ?? $user->phone,
                'client_comment' => $data['client_comment'] ?? null,
                'contract_mode' => $organization->framework_contract_id ? 'framework' : null,
                'contract_id' => $organization->framework_contract_id,
                'idempotency_key' => $idempotencyKey,
            ]);

            $maxLeadDays = null;
            foreach ($items as $item) {
                $order->items()->create([
                    'product_id' => $item->product_id,
                    'description' => $item->product->name,
                    'qty' => $item->qty,
                    'unit' => $item->unit,
                    'comment' => $item->comment,
                ]);
                $rp = $item->product->regionProducts->firstWhere('region_id', $region->id);
                if ($rp?->lead_time_max_days !== null) {
                    $maxLeadDays = max($maxLeadDays ?? 0, $rp->lead_time_max_days);
                }
            }

            if ($maxLeadDays !== null) {
                $this->setDate($order, DateKind::Preliminary, now()->addDays($maxLeadDays)->toDateString(), null, Actor::system(), 'Ориентир по каталогу');
            }

            $this->recordCreation($order, Actor::user($user));
            $cart->items()->delete();

            return $order;
        });
    }

    // ------------------------------------------------------- работа менеджера

    public function takeForReview(Order $order, Actor $actor): void
    {
        $this->assertStaff($actor, Permissions::OFFERS_MANAGE);
        $this->assertStatus($order, [OrderStatus::Created]);

        $order->manager_id ??= $actor->id;
        $order->setStatus('status', OrderStatus::UnderReview, $actor);
    }

    /** Новая версия предложения: строки копируются из запроса клиента или из прошлой версии. */
    public function createOfferDraft(Order $order, Actor $actor, ?string $reason = null): Offer
    {
        $this->assertStaff($actor, Permissions::OFFERS_MANAGE);
        $this->assertStatus($order, [OrderStatus::UnderReview, OrderStatus::AwaitingTerms]);

        return DB::transaction(function () use ($order, $actor, $reason) {
            $previous = $order->offers()->reorder('version', 'desc')->first();
            $offer = $order->offers()->create([
                'version' => ($previous->version ?? 0) + 1,
                'status' => OfferStatus::Draft,
                'reason' => $reason,
                'valid_until' => now()->addWeekdays((int) config('steelcor.offer_valid_weekdays', 3))->endOfDay(),
                'payment_terms' => $previous?->payment_terms ?? ($order->organization->payment_terms === 'deferral' ? PaymentTerms::Deferral : PaymentTerms::Prepay100),
                'deferral_days' => $previous?->deferral_days ?? $order->organization->deferral_days,
                'receive_method' => $order->receive_method,
                'lead_time_text' => $previous?->lead_time_text,
                'created_by' => $actor->id,
            ]);

            if ($previous !== null) {
                foreach ($previous->lines()->with('cost')->get() as $line) {
                    $copy = $offer->lines()->create($line->only([
                        'line_type', 'order_item_id', 'product_id', 'name', 'grade', 'gost', 'size_text', 'qty', 'unit',
                        'qty_base_t', 'unit_price', 'vat_rate', 'amount_net', 'amount_vat', 'amount_total', 'lead_time_text', 'sort',
                    ]));
                    if ($line->cost) {
                        $copy->cost()->create($line->cost->only([
                            'supplier_id', 'supplier_offer_id', 'purchase_price', 'logistics_cost', 'handling_cost', 'other_costs', 'note',
                        ]));
                    }
                }
            } else {
                $vat = $this->approvedRequisites($order)?->vat_rate_default ?? 0;
                foreach ($order->items()->with('product.regionProducts')->get() as $i => $item) {
                    $rp = $item->product?->regionProducts->firstWhere('region_id', $order->region_id);
                    $suggested = ($rp && $rp->public_price !== null && $rp->availability !== Availability::PriceOnRequest
                        && $rp->price_unit === $item->unit) ? $rp->public_price : 0;
                    $offer->lines()->create([
                        'line_type' => 'goods',
                        'order_item_id' => $item->id,
                        'product_id' => $item->product_id,
                        'name' => $item->description,
                        'grade' => $item->product?->grade,
                        'gost' => $item->product?->gost,
                        'qty' => $item->qty,
                        'unit' => $item->unit,
                        'qty_base_t' => $item->product ? $this->units->toTonnes($item->product, (string) $item->qty, $item->unit) : null,
                        'unit_price' => $suggested,
                        'vat_rate' => $vat,
                        'amount_net' => 0, 'amount_vat' => 0, 'amount_total' => 0,
                        'sort' => $i,
                    ]);
                }
            }

            $this->calculator->recalculate($offer);
            $offer->setStatus('status', OfferStatus::Draft, $actor);

            return $offer;
        });
    }

    public function approveLowMargin(Offer $offer, Actor $actor): void
    {
        $this->assertStaff($actor, Permissions::OFFERS_APPROVE_LOW_MARGIN);
        if ($offer->status !== OfferStatus::PendingApproval) {
            throw new WorkflowException('offer_not_pending', 'Предложение не ждёт одобрения.');
        }
        $offer->approved_low_margin_by = $actor->id;
        $offer->setStatus('status', OfferStatus::Draft, $actor, 'Низкая маржа одобрена');
    }

    public function sendOffer(Offer $offer, Actor $actor): void
    {
        $this->assertStaff($actor, Permissions::OFFERS_MANAGE);
        $order = $offer->order;
        $this->assertStatus($order, [OrderStatus::UnderReview, OrderStatus::AwaitingTerms]);

        if ($offer->status !== OfferStatus::Draft) {
            throw new WorkflowException('offer_not_draft', 'Отправить можно только черновик предложения.');
        }
        if ($offer->isExpired()) {
            throw new WorkflowException('offer_expired', 'Срок действия предложения уже прошёл — измените его.');
        }

        $this->calculator->recalculate($offer);
        $lines = $offer->lines()->with('cost')->get();
        $goods = $lines->where('line_type', 'goods');
        if ($goods->isEmpty()) {
            throw new WorkflowException('offer_empty', 'В предложении нет товаров.');
        }
        foreach ($goods as $line) {
            if (Money::compare($line->unit_price, '0') <= 0) {
                throw new WorkflowException('price_missing', "Не указана цена: {$line->name}.");
            }
        }
        if ($this->approvedRequisites($order) === null) {
            throw new WorkflowException('no_approved_requisites', 'У продавца нет утверждённых реквизитов — счёт сформировать нельзя.');
        }
        if ($order->organization->verification_status !== VerificationStatus::Verified) {
            throw new WorkflowException('organization_not_verified', 'Реквизиты клиента не проверены.');
        }

        if ($offer->approved_low_margin_by === null && $this->hasLowMargin($order, $lines)) {
            $offer->setStatus('status', OfferStatus::PendingApproval, $actor, 'Маржа ниже минимальной');
            throw new WorkflowException('low_margin', 'Маржа ниже минимальной: предложение отправлено на одобрение руководителю.');
        }

        DB::transaction(function () use ($offer, $order, $actor) {
            foreach ($order->offers()->where('status', OfferStatus::Sent->value)->whereKeyNot($offer->id)->get() as $old) {
                $old->setStatus('status', OfferStatus::Superseded, $actor, 'Отправлена новая версия');
            }
            $offer->sent_at = now();
            $offer->setStatus('status', OfferStatus::Sent, $actor);
            $order->setStatus('status', OrderStatus::AwaitingTerms, $actor, $order->is_reagreement ? 'Повторное согласование' : null);
        });
    }

    // ----------------------------------------------------------- действия клиента

    public function acceptOffer(Offer $offer, User $user, ?string $ip = null, ?string $device = null): void
    {
        $order = $offer->order;
        $this->assertMemberRole($user, $order->organization, [MemberRole::Owner, MemberRole::Buyer]);
        $this->assertStatus($order, [OrderStatus::AwaitingTerms]);

        if ($offer->status !== OfferStatus::Sent) {
            throw new WorkflowException('offer_not_active', 'Это предложение уже неактуально.');
        }
        if ($offer->isExpired()) {
            throw new WorkflowException('offer_expired', 'Срок действия предложения истёк. Менеджер подготовит новое.');
        }
        if ($order->organization->verification_status !== VerificationStatus::Verified) {
            throw new WorkflowException('organization_not_verified', 'Реквизиты организации ещё не проверены менеджером.');
        }
        $requisites = $this->approvedRequisites($order)
            ?? throw new WorkflowException('no_approved_requisites', 'Не удалось сформировать счёт. Менеджер свяжется с вами.');

        $actor = Actor::user($user);

        DB::transaction(function () use ($offer, $order, $user, $ip, $device, $actor, $requisites) {
            $previousAgreed = $order->agreedOffer;
            if ($previousAgreed && $previousAgreed->id !== $offer->id) {
                $previousAgreed->setStatus('status', OfferStatus::Superseded, $actor, 'Принята новая версия');
            }

            $offer->forceFill([
                'accepted_at' => now(),
                'accepted_by_user_id' => $user->id,
                'accepted_ip' => $ip,
                'accepted_device' => $device ? mb_substr($device, 0, 255) : null,
            ]);
            $offer->setStatus('status', OfferStatus::Accepted, $actor);

            $order->agreed_offer_id = $offer->id;
            $order->save();

            $this->issueInvoiceForAgreedOffer($order->refresh(), $offer, $requisites);

            if ($order->desired_date_from) {
                $this->setDate($order, DateKind::Agreed, $order->desired_date_from->toDateString(), $order->desired_date_to?->toDateString(), $actor, 'Условия приняты клиентом', true);
            }

            if ($order->is_reagreement) {
                foreach ($order->issues()->whereIn('status', [IssueStatus::Open->value, IssueStatus::WaitingClient->value])->get() as $issue) {
                    $issue->forceFill(['client_agreed_at' => now(), 'client_agreed_via' => 'app', 'new_offer_id' => $offer->id])->save();
                }
            }

            $order->setStatus('status', OrderStatus::AwaitingPayment, $actor);
            $this->recomputePayment($order->refresh(), $actor);
        });
    }

    public function requestChanges(Offer $offer, User $user, string $comment): void
    {
        $order = $offer->order;
        $this->assertMemberRole($user, $order->organization, [MemberRole::Owner, MemberRole::Buyer]);
        $this->assertStatus($order, [OrderStatus::AwaitingTerms]);
        if ($offer->status !== OfferStatus::Sent) {
            throw new WorkflowException('offer_not_active', 'Это предложение уже неактуально.');
        }

        $actor = Actor::user($user);
        DB::transaction(function () use ($offer, $order, $comment, $actor) {
            $offer->client_comment = $comment;
            $offer->setStatus('status', OfferStatus::Rejected, $actor, $comment);
            $order->setStatus('status', OrderStatus::UnderReview, $actor, 'Клиент просит изменить условия');
        });
    }

    /** Плановая задача: истёкшие предложения → «истёк», заказ возвращается менеджеру. */
    public function expireOffers(): int
    {
        $count = 0;
        $offers = Offer::query()->where('status', OfferStatus::Sent->value)->where('valid_until', '<', now())->get();
        foreach ($offers as $offer) {
            DB::transaction(function () use ($offer) {
                $offer->setStatus('status', OfferStatus::Expired, Actor::system());
                $order = $offer->order;
                if ($order->status === OrderStatus::AwaitingTerms) {
                    $order->setStatus('status', OrderStatus::UnderReview, Actor::system(), 'Истёк срок предложения');
                }
            });
            $count++;
        }

        return $count;
    }

    // ------------------------------------------------------------------- оплата

    /**
     * Пересчитать статус оплаты по привязанным платежам банка и, если хватает, открыть замок закупки.
     * Ручной установки «Оплачен» нет.
     */
    public function recomputePayment(Order $order, ?Actor $actor = null): void
    {
        $actor ??= Actor::system();
        $invoiceIds = $order->invoices()->where('status', InvoiceStatus::Issued->value)->pluck('id');
        $invoiced = Money::add(...$order->invoices()->where('status', InvoiceStatus::Issued->value)->pluck('amount')->all());
        $paid = Money::add(...PaymentAllocation::query()->whereIn('invoice_id', $invoiceIds)->whereNull('cancelled_at')->pluck('amount')->all());

        // Выполненные возвраты уменьшают полученную сумму.
        $refunded = Money::add(...$order->refunds()->where('status', RefundStatus::Completed->value)->pluck('amount')->all());
        $paid = Money::sub($paid, $refunded);

        $status = match (true) {
            $order->refunds()->whereIn('status', [RefundStatus::Approved->value, RefundStatus::Sent->value])->exists() => PaymentStatus::Refund,
            Money::compare($refunded, '0') > 0 && Money::compare($paid, '0') <= 0 => PaymentStatus::Refunded,
            $invoiceIds->isEmpty() => PaymentStatus::NotInvoiced,
            Money::compare($paid, $invoiced) >= 0 => PaymentStatus::Paid,
            Money::compare($paid, '0') > 0 => PaymentStatus::PartiallyPaid,
            $order->paymentNotices()->exists() => PaymentStatus::AwaitingConfirmation,
            default => PaymentStatus::AwaitingPayment,
        };
        $order->setStatus('payment_status', $status, $actor);

        $this->tryConfirmPayment($order, $paid, $actor);
        $this->handlePaymentReversal($order, $paid, $actor);
    }

    /**
     * Привязку платежа отменили (ошибка бухгалтера) после подтверждения оплаты:
     * до начала закупки заказ возвращается в «Ожидает оплаты», позже — открывается инцидент.
     */
    private function handlePaymentReversal(Order $order, string $paid, Actor $actor): void
    {
        $offer = $order->agreedOffer;
        if ($offer === null || $order->deferral_approved_by !== null || $order->is_reagreement
            || ! in_array($order->status, self::REAGREEMENT_FROM, true)
            || Money::compare($paid, $offer->requiredPrepayment()) >= 0) {
            return;
        }

        if ($order->status === OrderStatus::PaymentConfirmed && ! $order->purchaseOrdersActive()) {
            $order->setStatus('status', OrderStatus::AwaitingPayment, $actor, 'Подтверждение оплаты отменено');

            return;
        }
        if (! $order->issues()->where('type', 'other')->where('description', 'like', 'Оплата больше не подтверждена%')->whereIn('status', ['open'])->exists()) {
            $order->issues()->create([
                'type' => 'other', 'status' => IssueStatus::Open,
                'description' => 'Оплата больше не подтверждена выпиской, а закупка уже начата. Проверьте привязку платежей.',
            ]);
        }
    }

    /** Клиент сообщил об оплате: это сигнал бухгалтерии, статус «Оплачен» не ставится. */
    public function notifyPaid(Order $order, User $user, ?string $filePath, ?string $comment): void
    {
        $this->assertMemberRole($user, $order->organization, [MemberRole::Owner, MemberRole::Accountant]);
        $this->assertStatus($order, [OrderStatus::AwaitingPayment]);
        $order->paymentNotices()->create(['user_id' => $user->id, 'file_path' => $filePath, 'comment' => $comment]);
        $this->recomputePayment($order->refresh(), Actor::user($user));
    }

    /** Разрешить закупку до оплаты (отсрочка) — только уполномоченный сотрудник, в пределах лимита. */
    public function approveDeferral(Order $order, Actor $actor, string $reason): void
    {
        $this->assertStaff($actor, Permissions::PROCUREMENT_BEFORE_PAYMENT);
        $this->assertStatus($order, [OrderStatus::AwaitingPayment]);

        $organization = $order->organization;
        if ($organization->payment_terms !== 'deferral' || $organization->credit_limit === null) {
            throw new WorkflowException('no_deferral', 'У клиента нет одобренной отсрочки и кредитного лимита.');
        }
        $debt = $this->outstandingDebt($organization);
        if (Money::compare($debt, $organization->credit_limit) > 0) {
            throw new WorkflowException('credit_limit_exceeded', "Превышен кредитный лимит клиента: долг {$debt} ₽ при лимите {$organization->credit_limit} ₽.");
        }

        $order->forceFill(['deferral_approved_by' => $actor->id, 'deferral_reason' => $reason])->save();
        $this->recomputePayment($order->refresh(), $actor);
    }

    // ------------------------------------------------------------- закупка

    /** Замок: можно ли отправлять закупку поставщику по этому заказу. */
    public function isClearedForProcurement(Order $order): bool
    {
        return in_array($order->status, [
            OrderStatus::PaymentConfirmed, OrderStatus::Procurement, OrderStatus::SupplierConfirmed,
            OrderStatus::AwaitingReadiness, OrderStatus::ReadyToShip,
        ], true) && ! $order->is_reagreement;
    }

    /** Пересчитать сводный статус закупки и продвинуть заказ, если все строки обеспечены. */
    public function recomputeProcurement(Order $order, ?Actor $actor = null): void
    {
        $actor ??= Actor::system();
        $offer = $order->agreedOffer;
        if ($offer === null) {
            return;
        }

        $lines = $offer->lines()->where('line_type', 'goods')->with('allocations.purchaseOrderLine.purchaseOrder')->get();
        $poStatuses = collect();
        $allCovered = $lines->isNotEmpty();
        $anyAllocated = false;
        foreach ($lines as $line) {
            $covered = '0';
            foreach ($line->allocations as $allocation) {
                $po = $allocation->purchaseOrderLine->purchaseOrder;
                if ($po->status === PurchaseOrderStatus::Cancelled || $po->status === PurchaseOrderStatus::Refused) {
                    $poStatuses->push($po->status);

                    continue;
                }
                $anyAllocated = true;
                $poStatuses->push($po->status);
                if (! in_array($po->status, [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Sent, PurchaseOrderStatus::PriceChanged], true)) {
                    $covered = Money::round(Money::of($covered)->plus(Money::of($allocation->qty)), 4);
                }
            }
            if (Money::compare($covered, $line->qty) < 0) {
                $allCovered = false;
            }
        }

        $active = $poStatuses->reject(fn ($s) => in_array($s, [PurchaseOrderStatus::Cancelled, PurchaseOrderStatus::Refused], true));
        $status = match (true) {
            $poStatuses->contains(PurchaseOrderStatus::Refused) || $poStatuses->contains(PurchaseOrderStatus::PriceChanged) => ProcurementStatus::Problem,
            ! $anyAllocated => ProcurementStatus::NotStarted,
            $allCovered && $active->every(fn ($s) => $s === PurchaseOrderStatus::Shipped) => ProcurementStatus::Done,
            $allCovered && $active->every(fn ($s) => in_array($s, [PurchaseOrderStatus::Ready, PurchaseOrderStatus::Shipped], true)) => ProcurementStatus::Ready,
            $allCovered => ProcurementStatus::Confirmed,
            default => ProcurementStatus::InProgress,
        };
        $order->setStatus('procurement_status', $status, $actor);

        if ($order->is_reagreement) {
            return;
        }
        if ($order->status === OrderStatus::PaymentConfirmed && $anyAllocated) {
            $order->setStatus('status', OrderStatus::Procurement, $actor);
        }
        if ($order->status === OrderStatus::Procurement && in_array($status, [ProcurementStatus::Confirmed, ProcurementStatus::Ready, ProcurementStatus::Done], true)) {
            $order->setStatus('status', OrderStatus::SupplierConfirmed, $actor);
            $order->setStatus('status', OrderStatus::AwaitingReadiness, $actor);
        }
        if ($order->status === OrderStatus::AwaitingReadiness && in_array($status, [ProcurementStatus::Ready, ProcurementStatus::Done], true)) {
            $order->setStatus('status', OrderStatus::ReadyToShip, $actor);
        }
    }

    // ------------------------------------------------------ повторное согласование

    /** Условия меняются после оплаты: заказ уходит на согласование, закупка по нему замораживается. */
    public function startReagreement(Order $order, Actor $actor, string $reason): void
    {
        if ($actor->type !== ActorType::System) {
            $this->assertStaff($actor, Permissions::ISSUES_MANAGE);
        }
        if (! in_array($order->status, self::REAGREEMENT_FROM, true)) {
            throw new WorkflowException('reagreement_not_allowed', 'На этом этапе повторное согласование невозможно.');
        }

        $order->forceFill(['is_reagreement' => true, 'resume_status' => $order->status])->save();
        $order->setStatus('status', OrderStatus::UnderReview, $actor, 'Повторное согласование: '.$reason);
        // Дальше менеджер готовит новую версию предложения: sendOffer() переведёт заказ в «Ожидает подтверждения условий».
    }

    // ----------------------------------------------------------------- доставка

    public function markPickedUp(Order $order, Actor $actor, string $receivedBy): void
    {
        $this->assertStaff($actor, Permissions::SHIPMENTS_MANAGE);
        $this->assertStatus($order, [OrderStatus::ReadyToShip]);
        if ($order->receive_method !== ReceiveMethod::Pickup) {
            throw new WorkflowException('not_pickup', 'Заказ оформлен с доставкой.');
        }
        $order->setStatus('delivery_status', ShipmentStatus::Delivered, $actor);
        $order->setStatus('status', OrderStatus::Delivered, $actor, 'Самовывоз: получил '.$receivedBy);
        $this->tryComplete($order, $actor);
    }

    /** Пересчитать сводную доставку по отправлениям и продвинуть заказ. */
    public function recomputeDelivery(Order $order, ?Actor $actor = null): void
    {
        $actor ??= Actor::system();
        $statuses = $order->shipments()->where('status', '!=', ShipmentStatus::Cancelled->value)->pluck('status');
        if ($statuses->isEmpty()) {
            return;
        }
        $rank = [
            ShipmentStatus::Problem, ShipmentStatus::NotArranged, ShipmentStatus::Calculating, ShipmentStatus::AwaitingBooking,
            ShipmentStatus::Booked, ShipmentStatus::AcceptedByCarrier, ShipmentStatus::InTransit, ShipmentStatus::Arrived,
            ShipmentStatus::ReadyForPickup, ShipmentStatus::Delivered,
        ];
        $worst = $statuses->sortBy(fn ($s) => array_search($s, $rank, true))->first();
        $order->setStatus('delivery_status', $worst, $actor);

        if ($order->status === OrderStatus::ReadyToShip) {
            $order->setStatus('status', OrderStatus::ArrangingDelivery, $actor);
        }
        $reached = fn (ShipmentStatus $s) => array_search($worst, $rank, true) >= array_search($s, $rank, true);
        if ($order->status === OrderStatus::ArrangingDelivery && $worst !== ShipmentStatus::Problem && $reached(ShipmentStatus::AcceptedByCarrier)) {
            $order->setStatus('status', OrderStatus::InDelivery, $actor);
        }
        if ($order->status === OrderStatus::InDelivery && $worst === ShipmentStatus::Delivered) {
            $order->setStatus('status', OrderStatus::Delivered, $actor);
            $this->tryComplete($order, $actor);
        }
    }

    // -------------------------------------------------------------- завершение

    /** Закрыть заказ, если выполнены все замки закрытия. */
    public function tryComplete(Order $order, Actor $actor): bool
    {
        if ($order->status !== OrderStatus::Delivered) {
            return false;
        }
        $paid = $order->payment_status === PaymentStatus::Paid;
        $openIssues = $order->issues()->whereNotIn('status', [IssueStatus::Resolved->value, IssueStatus::Closed->value])->exists();
        $docsOk = in_array($order->documents_status, [DocumentsSummary::None, DocumentsSummary::AllSigned], true);
        if (! $paid || $openIssues || ! $docsOk) {
            return false;
        }
        $order->completed_at = now();
        $order->setStatus('status', OrderStatus::Completed, $actor);

        return true;
    }

    // ------------------------------------------------------------------ отмена

    public function cancel(Order $order, Actor $actor, string $reason): void
    {
        $paidStage = ! in_array($order->status, self::CLIENT_CANCELLABLE, true);

        if ($actor->type === ActorType::User) {
            $this->assertMemberRole($actor->user, $order->organization, [MemberRole::Owner, MemberRole::Buyer]);
            if ($paidStage) {
                throw new WorkflowException('cancel_needs_manager', 'Заказ уже в работе. Отправьте запрос на отмену менеджеру.');
            }
        } else {
            $this->assertStaff($actor, Permissions::ISSUES_MANAGE);
        }

        if (in_array($order->status, [OrderStatus::ArrangingDelivery, OrderStatus::InDelivery, OrderStatus::Delivered, OrderStatus::Completed, OrderStatus::Cancelled], true)) {
            throw new WorkflowException('cancel_not_allowed', 'На этом этапе отменить заказ нельзя.');
        }
        if ($paidStage && ! $order->issues()->whereIn('type', ['client_cancel', 'cancel_after_purchase', 'supplier_refused', 'out_of_stock', 'price_changed', 'supplier_delay'])->exists()) {
            throw new WorkflowException('issue_required', 'Отмена оплаченного заказа — только через инцидент с причиной и решением.');
        }
        if ($order->purchaseOrdersActive()) {
            throw new WorkflowException('active_purchases', 'Сначала отмените закупки у поставщиков по этому заказу.');
        }

        DB::transaction(function () use ($order, $actor, $reason) {
            foreach ($order->invoices()->where('status', InvoiceStatus::Issued->value)->get() as $invoice) {
                if (! $invoice->paymentAllocations()->whereNull('cancelled_at')->exists()) {
                    $invoice->update(['status' => InvoiceStatus::Cancelled]);
                }
            }
            foreach ($order->offers()->whereIn('status', [OfferStatus::Draft->value, OfferStatus::Sent->value, OfferStatus::PendingApproval->value])->get() as $offer) {
                $offer->setStatus('status', OfferStatus::Superseded, $actor, 'Заказ отменён');
            }
            $order->forceFill(['cancel_reason' => $reason, 'cancelled_at' => now()]);
            $order->setStatus('status', OrderStatus::Cancelled, $actor, $reason);
        });
    }

    // ------------------------------------------------------------ вспомогательное

    public function setDate(Order $order, DateKind $kind, string $from, ?string $to, Actor $actor, ?string $reason = null, bool $notify = false): OrderDate
    {
        return $order->dates()->create([
            'kind' => $kind,
            'date_from' => $from,
            'date_to' => $to,
            'reason' => $reason,
            'set_by_type' => $actor->type->value,
            'set_by_id' => $actor->id,
            'notify_client' => $notify,
        ]);
    }

    public function approvedRequisites(Order $order): ?SellerRequisites
    {
        return SellerRequisites::query()
            ->where('seller_entity_id', $order->seller_entity_id)
            ->where('status', ApprovalStatus::Approved->value)
            ->whereDate('valid_from', '<=', now())
            ->orderByDesc('valid_from')->orderByDesc('version')
            ->first();
    }

    private function issueInvoiceForAgreedOffer(Order $order, Offer $offer, SellerRequisites $requisites): void
    {
        // Сколько уже выставлено по действующим счетам (при повторном согласовании — доплата на разницу).
        $issued = Money::add(...$order->invoices()->where('status', InvoiceStatus::Issued->value)->pluck('amount')->all());
        $amount = Money::sub($offer->grand_total, $issued);
        if (Money::compare($amount, '0') <= 0) {
            return; // сумма не выросла; уменьшение решается возвратом или зачётом по договору (О-13)
        }

        $vat = Money::compare($offer->grand_total, '0') === 0 ? '0.00'
            : Money::round(Money::of($offer->goods_vat)->multipliedBy(Money::of($amount))->dividedBy(Money::of($offer->grand_total), 6, RoundingMode::HalfUp));

        Invoice::create([
            'number' => $this->numbers->next('invoice', $order->seller_entity_id, $order->region),
            'order_id' => $order->id,
            'offer_id' => $offer->id,
            'seller_entity_id' => $order->seller_entity_id,
            'seller_requisites_id' => $requisites->id,
            'organization_id' => $order->organization_id,
            'kind' => Money::compare($issued, '0') > 0 ? 'additional' : ($offer->payment_terms === PaymentTerms::PrepayPartial ? 'prepayment' : 'final'),
            'amount' => $amount,
            'vat' => $vat,
            'due_date' => now()->addWeekdays((int) config('steelcor.invoice_due_weekdays', 3))->toDateString(),
            'status' => InvoiceStatus::Issued,
        ]);
    }

    private function tryConfirmPayment(Order $order, string $paid, Actor $actor): void
    {
        if ($order->status !== OrderStatus::AwaitingPayment || $order->agreedOffer === null) {
            return;
        }
        $required = $order->agreedOffer->requiredPrepayment();
        $prepaid = Money::compare($paid, $required) >= 0 && ($order->agreedOffer->payment_terms !== PaymentTerms::Deferral);
        $invoicedEnough = $order->is_reagreement
            ? $order->payment_status === PaymentStatus::Paid
            : $prepaid;

        if (! $invoicedEnough && $order->deferral_approved_by === null) {
            return;
        }

        if ($order->is_reagreement && $order->resume_status !== null) {
            $resume = $order->resume_status;
            $order->forceFill(['is_reagreement' => false, 'resume_status' => null])->save();
            $order->setStatus('status', $resume, $actor, 'Условия согласованы повторно');
            $this->recomputeProcurement($order, $actor);

            return;
        }

        $order->setStatus('status', OrderStatus::PaymentConfirmed, $actor, $order->deferral_approved_by && ! $invoicedEnough ? 'Отсрочка: '.$order->deferral_reason : null);
    }

    private function outstandingDebt(Organization $organization): string
    {
        $invoiceIds = Invoice::query()->where('organization_id', $organization->id)->where('status', InvoiceStatus::Issued->value)->pluck('id');
        $invoiced = Money::add(...Invoice::query()->whereIn('id', $invoiceIds)->pluck('amount')->all());
        $paid = Money::add(...PaymentAllocation::query()->whereIn('invoice_id', $invoiceIds)->whereNull('cancelled_at')->pluck('amount')->all());

        return Money::sub($invoiced, $paid);
    }

    private function hasLowMargin(Order $order, $lines): bool
    {
        $min = app(PricingService::class)->minMarginPercent($order);
        if ($min === null) {
            return false;
        }
        foreach ($lines as $line) {
            if ($line->cost?->margin_percent !== null && Money::compare($line->cost->margin_percent, $min) < 0) {
                return true;
            }
        }

        return false;
    }

    private function recordCreation(Order $order, Actor $actor): void
    {
        StatusHistory::create([
            'entity_type' => 'order', 'entity_id' => $order->id, 'field' => 'status',
            'from_value' => null, 'to_value' => OrderStatus::Created->value,
            'actor_type' => $actor->type->value, 'actor_id' => $actor->id,
        ]);
    }

    /** @param list<OrderStatus> $allowed */
    private function assertStatus(Order $order, array $allowed): void
    {
        if (! in_array($order->status, $allowed, true)) {
            throw new WorkflowException('wrong_status', "Действие недоступно в статусе «{$order->status->label()}».");
        }
    }

    private function assertStaff(Actor $actor, string $permission): void
    {
        if ($actor->type !== ActorType::Staff || $actor->staff === null || ! $actor->staff->can($permission)) {
            throw new WorkflowException('forbidden', 'Недостаточно прав для этого действия.');
        }
    }

    /** @param list<MemberRole> $roles */
    private function assertMemberRole(?User $user, Organization $organization, array $roles): void
    {
        $membership = $user?->membershipIn($organization->id);
        if ($membership === null || ! in_array($membership->role, $roles, true)) {
            throw new WorkflowException('forbidden', 'У вас нет прав на это действие в этой организации.');
        }
    }
}
