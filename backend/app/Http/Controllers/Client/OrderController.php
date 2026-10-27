<?php

namespace App\Http\Controllers\Client;

use App\Enums\OrderStatus;
use App\Exceptions\WorkflowException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Client\OfferResource;
use App\Http\Resources\Client\OrderResource;
use App\Models\Cart;
use App\Models\Offer;
use App\Models\Order;
use App\Services\OrderWorkflow;
use App\Support\Actor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Заказы клиента. Вся бизнес-логика — в OrderWorkflow, здесь только проверка входа и доступа. */
class OrderController extends Controller
{
    public function __construct(private readonly OrderWorkflow $workflow) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate(['status' => 'nullable|in:active,done']);
        $query = Order::query()
            ->whereIn('organization_id', $request->user()->organizations()->pluck('organizations.id'))
            ->with(['organization', 'region', 'agreedOffer'])
            ->orderByDesc('id');
        $done = [OrderStatus::Completed->value, OrderStatus::Cancelled->value];
        match ($data['status'] ?? null) {
            'active' => $query->whereNotIn('status', $done),
            'done' => $query->whereIn('status', $done),
            default => null,
        };

        return OrderResource::collection($query->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'receive_method' => 'required|in:delivery,pickup',
            'delivery_address_id' => 'required_if:receive_method,delivery|nullable|integer',
            'pickup_point_id' => 'required_if:receive_method,pickup|nullable|integer',
            'desired_date_from' => 'nullable|date|after_or_equal:today',
            'desired_date_to' => 'nullable|date|after_or_equal:desired_date_from',
            'desired_time_slot' => 'nullable|string|max:50',
            'contact_name' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'client_comment' => 'nullable|string|max:2000',
        ]);
        $key = $request->header('Idempotency-Key');
        if ($key !== null && ! preg_match('/^[A-Za-z0-9_-]{8,64}$/', $key)) {
            throw new WorkflowException('idempotency_key_invalid', 'Неверный ключ повтора.');
        }

        $organization = $request->attributes->get('organization');
        $region = $request->attributes->get('region');
        $cart = Cart::firstOrCreate(['user_id' => $request->user()->id, 'organization_id' => $organization->id, 'region_id' => $region->id]);

        $order = $this->workflow->createFromCart($request->user(), $organization, $region, $cart, $data, $key);

        return (new OrderResource($order->load(['organization', 'region'])))->response()->setStatusCode(201);
    }

    public function show(Request $request, Order $order): OrderResource
    {
        $this->authorizeOrder($request, $order);

        return new OrderResource($order->load(['organization', 'region', 'agreedOffer', 'items', 'deliveryAddress', 'pickupPoint', 'shipments']));
    }

    public function offers(Request $request, Order $order): AnonymousResourceCollection
    {
        $this->authorizeOrder($request, $order);

        return OfferResource::collection($order->offers()->whereNot('status', 'draft')->whereNot('status', 'pending_approval')->with('lines')->get());
    }

    public function accept(Request $request, Order $order, Offer $offer): OrderResource
    {
        $this->authorizeOrder($request, $order, $offer);
        $this->workflow->acceptOffer($offer, $request->user(), $request->ip(), $request->userAgent());

        return $this->show($request, $order->refresh());
    }

    public function requestChanges(Request $request, Order $order, Offer $offer): OrderResource
    {
        $this->authorizeOrder($request, $order, $offer);
        $data = $request->validate(['comment' => 'required|string|max:2000']);
        $this->workflow->requestChanges($offer, $request->user(), $data['comment']);

        return $this->show($request, $order->refresh());
    }

    public function cancel(Request $request, Order $order): OrderResource
    {
        $this->authorizeOrder($request, $order);
        $data = $request->validate(['reason' => 'required|string|max:1000']);
        $this->workflow->cancel($order, Actor::user($request->user()), $data['reason']);

        return $this->show($request, $order->refresh());
    }

    public function paymentNotice(Request $request, Order $order): OrderResource
    {
        $this->authorizeOrder($request, $order);
        $data = $request->validate([
            'comment' => 'nullable|string|max:1000',
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);
        $path = $request->file('file')?->store("payment-notices/{$order->id}", 'local');
        $this->workflow->notifyPaid($order, $request->user(), $path, $data['comment'] ?? null);

        return $this->show($request, $order->refresh());
    }

    public function repeat(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOrder($request, $order);
        $cart = Cart::firstOrCreate(['user_id' => $request->user()->id, 'organization_id' => $order->organization_id, 'region_id' => $order->region_id]);
        $added = 0;
        foreach ($order->items()->whereNotNull('product_id')->get() as $item) {
            $cart->items()->create(['product_id' => $item->product_id, 'qty' => $item->qty, 'unit' => $item->unit, 'comment' => $item->comment]);
            $added++;
        }

        return response()->json(['data' => ['added' => $added]]);
    }

    /** Доступ только участникам организации заказа; предложение должно принадлежать заказу. */
    private function authorizeOrder(Request $request, Order $order, ?Offer $offer = null): void
    {
        if ($request->user()->membershipIn($order->organization_id) === null) {
            throw new WorkflowException('not_found', 'Заказ не найден.');
        }
        if ($offer !== null && $offer->order_id !== $order->id) {
            throw new WorkflowException('not_found', 'Предложение не найдено.');
        }
    }
}
