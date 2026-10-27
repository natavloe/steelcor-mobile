<?php

namespace Tests\Support;

use App\Enums\ApprovalStatus;
use App\Enums\Availability;
use App\Enums\MemberRole;
use App\Enums\OrganizationType;
use App\Enums\VerificationStatus;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Offer;
use App\Models\Order;
use App\Models\Organization;
use App\Models\PickupPoint;
use App\Models\Product;
use App\Models\Region;
use App\Models\SellerEntity;
use App\Models\SellerRequisites;
use App\Models\StaffUser;
use App\Models\Supplier;
use App\Models\User;
use App\Services\OrderWorkflow;
use App\Support\Actor;
use Database\Seeders\RolesSeeder;

/**
 * Тестовый «мир»: продавец с утверждёнными реквизитами, регион, сотрудники, клиент, товар.
 * Все реквизиты заведомо вымышленные и помечены «ТЕСТ» — только для автотестов.
 */
trait BuildsWorld
{
    protected SellerEntity $seller;

    protected Region $region;

    protected PickupPoint $pickup;

    protected User $client;

    protected Organization $org;

    protected Product $product;

    protected Supplier $supplier;

    /** @var array<string, StaffUser> */
    protected array $staff = [];

    protected function buildWorld(): void
    {
        $this->seed(RolesSeeder::class);

        $this->seller = SellerEntity::create(['name_short' => 'ТЕСТ Продавец', 'inn' => '0000000001']);
        SellerRequisites::create([
            'seller_entity_id' => $this->seller->id, 'version' => 1,
            'name_full' => 'ТЕСТ Продавец', 'name_short' => 'ТЕСТ Продавец', 'inn' => '0000000001', 'kpp' => '000000001',
            'legal_address' => 'ТЕСТ', 'bank_name' => 'ТЕСТ БАНК', 'bik' => '000000000', 'account' => '40702810000000000001',
            'signer_name' => 'ТЕСТ', 'signer_position' => 'Директор', 'signer_basis' => 'Устава',
            'vat_rate_default' => '20', 'valid_from' => now()->subYear()->toDateString(), 'status' => ApprovalStatus::Approved,
        ]);
        $this->region = Region::create(['code' => 'tst', 'name' => 'Тестоград', 'seller_entity_id' => $this->seller->id, 'is_active' => true]);
        $this->pickup = PickupPoint::create(['region_id' => $this->region->id, 'name' => 'Склад', 'address' => 'ТЕСТ']);

        foreach (['head', 'manager', 'procurement', 'accountant', 'logistics', 'lawyer', 'admin'] as $role) {
            $this->staff[$role] = StaffUser::create(['name' => $role, 'email' => "$role@test.invalid", 'password' => 'secret-password', 'all_regions' => true]);
            $this->staff[$role]->assignRole($role);
        }
        $this->staff['accountant2'] = StaffUser::create(['name' => 'acc2', 'email' => 'acc2@test.invalid', 'password' => 'secret-password', 'all_regions' => true]);
        $this->staff['accountant2']->assignRole('accountant');

        $this->client = User::create(['name' => 'Клиент', 'phone' => '+70000000001']);
        $this->org = Organization::create([
            'type' => OrganizationType::Legal, 'name_full' => 'ТЕСТ Покупатель', 'name_short' => 'ТЕСТ Покупатель',
            'inn' => '0000000002', 'kpp' => '000000002', 'verification_status' => VerificationStatus::Verified,
            'region_id' => $this->region->id,
        ]);
        $this->org->members()->create(['user_id' => $this->client->id, 'role' => MemberRole::Owner]);

        $category = Category::create(['name' => 'Круг', 'slug' => 'krug']);
        $this->product = Product::create([
            'category_id' => $category->id, 'name' => 'Круг 20 мм 40Х', 'slug' => 'krug-20-40h',
            'attrs' => ['diameter' => 20, 'grade' => '40Х'], 'grade' => '40Х', 'gost' => 'ГОСТ 2590-2006',
            'base_unit' => 't', 'weight_per_m' => '2.47', 'std_length_mm' => 6000,
        ]);
        $this->product->regionProducts()->create([
            'region_id' => $this->region->id, 'availability' => Availability::MadeToOrder,
            'public_price' => '95000', 'price_unit' => 't', 'lead_time_min_days' => 3, 'lead_time_max_days' => 7,
        ]);
        $this->supplier = Supplier::create(['name' => 'ТЕСТ Поставщик']);
    }

    protected function actor(string $role): Actor
    {
        return Actor::staff($this->staff[$role]);
    }

    protected function workflow(): OrderWorkflow
    {
        return app(OrderWorkflow::class);
    }

    protected function placeOrder(string $qty = '2'): Order
    {
        $cart = Cart::create(['user_id' => $this->client->id, 'organization_id' => $this->org->id, 'region_id' => $this->region->id]);
        $cart->items()->create(['product_id' => $this->product->id, 'qty' => $qty, 'unit' => 't']);

        return $this->workflow()->createFromCart($this->client, $this->org, $this->region, $cart, [
            'receive_method' => 'pickup', 'pickup_point_id' => $this->pickup->id,
        ]);
    }

    /** Заказ с отправленным предложением (цена из каталога: 95 000 за т). */
    protected function orderWithSentOffer(string $qty = '2'): array
    {
        $order = $this->placeOrder($qty);
        $this->workflow()->takeForReview($order, $this->actor('manager'));
        $offer = $this->workflow()->createOfferDraft($order->refresh(), $this->actor('manager'));
        $this->workflow()->sendOffer($offer, $this->actor('manager'));

        return [$order->refresh(), $offer->refresh()];
    }

    /** @return array{0: Order, 1: Offer} */
    protected function acceptedOrder(string $qty = '2'): array
    {
        [$order, $offer] = $this->orderWithSentOffer($qty);
        $this->workflow()->acceptOffer($offer, $this->client);

        return [$order->refresh(), $offer->refresh()];
    }

    /** Выписка 1CClientBankExchange с одним поступлением. */
    protected function statement(string $amount, string $purpose, string $payerInn = '0000000002', string $number = '15'): string
    {
        return implode("\r\n", [
            '1CClientBankExchange', 'ВерсияФормата=1.03', 'Кодировка=Windows', 'РасчСчет=40702810000000000001',
            'СекцияДокумент=Платежное поручение', "Номер={$number}", 'Дата=27.10.2026', "Сумма={$amount}",
            'ПлательщикСчет=40702810999999999999', "ПлательщикИНН={$payerInn}", 'Плательщик1=ТЕСТ Покупатель',
            'ПолучательСчет=40702810000000000001', 'ПолучательИНН=0000000001', 'ДатаПоступило=27.10.2026',
            "НазначениеПлатежа={$purpose}", 'КонецДокумента', 'КонецФайла',
        ]);
    }
}
