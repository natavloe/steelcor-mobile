<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\VerificationStatus;
use App\Models\Organization;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ProcurementService;
use App\Services\Sms\SmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsWorld;
use Tests\TestCase;

/** Клиентский API: путь клиента от входа до принятия условий, доступы и отсутствие утечек закупки. */
class ClientApiTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    private array $sms = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->app->instance(SmsSender::class, new class($this->sms) implements SmsSender
        {
            public function __construct(private array &$box) {}

            public function send(string $phone, string $text): void
            {
                $this->box[] = [$phone, $text];
            }
        });
    }

    private function api(string $method, string $uri, array $data = [], array $headers = [], ?string $token = null)
    {
        $headers += ['Accept' => 'application/json'];
        if ($token) {
            $headers['Authorization'] = 'Bearer '.$token;
        }

        return $this->json($method, '/api/client/v1/'.ltrim($uri, '/'), $data, $headers);
    }

    private function login(string $phone = '+7 (900) 111-22-33', bool $consent = true): string
    {
        $this->api('POST', 'auth/otp/request', ['phone' => $phone])->assertOk();
        preg_match('/(\d{4})/', end($this->sms)[1], $m);
        $response = $this->api('POST', 'auth/otp/verify', ['phone' => $phone, 'code' => $m[1], 'name' => 'Пётр', 'pd_consent' => $consent]);
        $response->assertOk();

        return $response->json('data.token');
    }

    public function test_client_journey_from_login_to_accepting_terms(): void
    {
        $token = $this->login();
        $this->assertSame('+79001112233', User::where('name', 'Пётр')->value('phone'));

        // Организация (DaData не настроена — ввод вручную, статус «не проверена»)
        $org = $this->api('POST', 'organizations', [
            'type' => 'legal', 'name_full' => 'ООО ТЕСТ-СТРОЙ', 'name_short' => 'ТЕСТ-СТРОЙ', 'inn' => '1234567890', 'kpp' => '123456789',
        ], token: $token)->assertCreated()->json('data');
        $this->assertSame('unverified', $org['verification_status']);
        $ctx = ['X-Organization' => (string) $org['id'], 'X-Region' => 'tst'];

        // Каталог и корзина
        $this->api('GET', 'products?q=40Х', headers: ['X-Region' => 'tst'])->assertOk()->assertJsonPath('data.0.name', 'Круг 20 мм 40Х');
        $this->api('GET', 'products?f[diameter][min]=10&f[diameter][max]=25', headers: ['X-Region' => 'tst'])->assertJsonCount(1, 'data');
        $this->api('GET', 'products?f[diameter][min]=30', headers: ['X-Region' => 'tst'])->assertJsonCount(0, 'data');

        $cart = $this->api('POST', 'cart/items', ['product_id' => $this->product->id, 'qty' => 120, 'unit' => 'm'], $ctx, $token)
            ->assertCreated()->json('data');
        // 120 м × 2,47 кг/м = 296,4 кг = 0,2964 т × 95 000 ₽ = 28 158 ₽
        $this->assertSame('28158.00', $cart['preliminary_total']);
        $this->assertSame('296.400', $cart['theoretical_weight_kg']);

        // Оформление: повтор с тем же ключом не создаёт второй заказ
        $address = $this->api('POST', 'addresses', ['address' => 'г. Тестоград, ул. Литейная, 1', 'access_notes' => 'Въезд для длинномера со двора'], $ctx, $token)
            ->assertCreated()->json('data');
        $payload = ['receive_method' => 'delivery', 'delivery_address_id' => $address['id']];
        $first = $this->api('POST', 'orders', $payload, $ctx + ['Idempotency-Key' => 'order-key-001'], $token)->assertCreated()->json('data');
        $second = $this->api('POST', 'orders', $payload, $ctx + ['Idempotency-Key' => 'order-key-001'], $token)->json('data');
        $this->assertSame($first['id'], $second['id']);
        $this->assertSame('Заказ получен', $first['status_label']);

        // Менеджер проверяет реквизиты и отправляет предложение
        $organization = Organization::find($org['id']);
        $organization->update(['verification_status' => VerificationStatus::Verified]);
        $order = $organization->orders()->sole();
        $this->workflow()->takeForReview($order, $this->actor('manager'));
        $offer = $this->workflow()->createOfferDraft($order->refresh(), $this->actor('manager'));
        $offer->lines()->first()->update(['unit_price' => '96000', 'unit' => 't', 'qty' => '0.2964']);
        $this->workflow()->sendOffer($offer->refresh(), $this->actor('manager'));

        $shown = $this->api('GET', "orders/{$order->id}", token: $token)->assertOk()->json('data');
        $this->assertSame('accept_terms', $shown['action_required']['code']);
        $this->assertSame('28454.40', $shown['current_offer']['totals']['total']);

        // Клиент принимает условия → счёт
        $accepted = $this->api('POST', "orders/{$order->id}/offers/{$offer->id}/accept", token: $token)->assertOk()->json('data');
        $this->assertSame('awaiting_payment', $accepted['status']);
        $this->assertSame('pay', $accepted['action_required']['code']);
        $this->assertCount(1, $accepted['invoices']);
        $this->assertSame('28454.40', $accepted['invoices'][0]['remaining']);

        // «Я оплатил» — только сигнал, не оплата
        $this->api('POST', "orders/{$order->id}/payment-notice", ['comment' => 'п/п 15'], token: $token)
            ->assertOk()->assertJsonPath('data.payment_status', 'awaiting_confirmation')->assertJsonPath('data.status', 'awaiting_payment');
    }

    public function test_new_user_must_consent_and_wrong_code_is_rejected(): void
    {
        $this->api('POST', 'auth/otp/request', ['phone' => '89005556677'])->assertOk()->assertJsonPath('data.is_new_user', true);
        $this->api('POST', 'auth/otp/verify', ['phone' => '89005556677', 'code' => '0000x'])->assertStatus(422)->assertJsonPath('error.code', 'otp_invalid');

        preg_match('/(\d{4})/', end($this->sms)[1], $m);
        $this->api('POST', 'auth/otp/verify', ['phone' => '89005556677', 'code' => $m[1]])
            ->assertStatus(422)->assertJsonPath('error.code', 'pd_consent_required');
        $this->assertSame(0, User::where('phone', '+79005556677')->count());
    }

    public function test_stranger_cannot_see_order_and_viewer_cannot_accept(): void
    {
        [$order, $offer] = $this->orderWithSentOffer();
        $strangerToken = $this->login('+79000000077');
        $this->api('GET', "orders/{$order->id}", token: $strangerToken)->assertNotFound();

        $viewer = User::where('phone', '+79000000077')->first();
        $this->org->members()->create(['user_id' => $viewer->id, 'role' => 'viewer']);
        $this->api('GET', "orders/{$order->id}", token: $strangerToken)->assertOk();
        $this->api('POST', "orders/{$order->id}/offers/{$offer->id}/accept", token: $strangerToken)
            ->assertForbidden()->assertJsonPath('error.code', 'forbidden');
        $this->assertSame(OrderStatus::AwaitingTerms, $order->refresh()->status);

        // Чужая организация в заголовке не пускает в корзину
        $this->api('GET', 'cart', headers: ['X-Organization' => '999999', 'X-Region' => 'tst'], token: $strangerToken)
            ->assertStatus(422)->assertJsonPath('error.code', 'organization_required');
    }

    /** Ни один GET-метод клиентского API не отдаёт поставщиков, закупочные цены и маржу. */
    public function test_no_procurement_data_leaks_through_client_api(): void
    {
        Supplier::query()->update(['name' => 'СЕКРЕТНЫЙПОСТАВЩИК']);
        [$order, $offer] = $this->orderWithSentOffer();
        $offer->lines()->first()->cost()->create(['supplier_id' => $this->supplier->id, 'purchase_price' => '77777.77', 'note' => 'ВНУТРЕННЯЯЗАМЕТКА']);
        $this->org->update(['internal_note' => 'ЗАМЕТКАМЕНЕДЖЕРА', 'credit_limit' => '9999999']);
        $this->workflow()->acceptOffer($offer, $this->client);
        app(\App\Services\Bank\BankStatementImporter::class)->importFile($this->seller, $this->statement('190000.00', 'Оплата '.$order->invoices()->sole()->number));
        $procurement = app(ProcurementService::class);
        $po = $procurement->create($this->supplier, $this->region, $this->actor('procurement'));
        $procurement->addLine($po, ['description' => 'Круг', 'qty' => '2', 'unit' => 't', 'purchase_price' => '66666.66'], [$offer->lines()->first()->id => '2'], $this->actor('procurement'));

        $token = $this->client->createToken('t')->plainTextToken;
        $ctx = ['X-Organization' => (string) $this->org->id, 'X-Region' => 'tst'];
        $bodies = [];
        foreach ([
            'regions', 'categories', 'products', "products/{$this->product->id}", 'pickup-points', 'me', 'organizations',
            'organization', 'organization/members', 'addresses', 'cart', 'orders', "orders/{$order->id}", "orders/{$order->id}/offers",
        ] as $uri) {
            $response = $this->api('GET', $uri, headers: $ctx, token: $token);
            $this->assertSame(200, $response->status(), $uri);
            $bodies[$uri] = $response->getContent();
        }

        foreach ($bodies as $uri => $body) {
            foreach (['СЕКРЕТНЫЙПОСТАВЩИК', '77777', '66666', 'ВНУТРЕННЯЯЗАМЕТКА', 'ЗАМЕТКАМЕНЕДЖЕРА', '9999999',
                'purchase_price', 'margin', 'supplier', 'internal_note', 'credit_limit', 'Поставщик'] as $secret) {
                $this->assertStringNotContainsString($secret, $body, "Утечка «{$secret}» в {$uri}");
            }
        }
    }

    public function test_region_header_is_required_for_catalog(): void
    {
        $this->api('GET', 'products')->assertStatus(422)->assertJsonPath('error.code', 'region_required');
        $this->api('GET', 'regions')->assertOk()->assertJsonPath('data.0.code', 'tst');
    }
}
