<?php

namespace Database\Seeders;

use App\Enums\ApprovalStatus;
use App\Enums\Availability;
use App\Enums\MemberRole;
use App\Enums\OrganizationType;
use App\Enums\VerificationStatus;
use App\Models\Attribute;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Organization;
use App\Models\PickupPoint;
use App\Models\PricingRule;
use App\Models\Product;
use App\Models\Region;
use App\Models\SellerEntity;
use App\Models\SellerRequisites;
use App\Models\StaffUser;
use App\Models\Supplier;
use App\Models\User;
use App\Services\OrderWorkflow;
use App\Support\Actor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * ДЕМО-данные для локального просмотра. ВСЁ ВЫМЫШЛЕНО и помечено «ДЕМО».
 * Реквизиты — заведомо недействительные (нули). Запуск в боевой среде запрещён.
 *
 *   php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'demo-password-123';

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Демо-данные нельзя загружать в боевую базу.');
        }

        $this->call(RolesSeeder::class);

        $seller = SellerEntity::firstOrCreate(['inn' => '0000000000'], ['name_short' => 'ДЕМО-продавец (не использовать)']);
        $headStaff = $this->staff('Руководитель (демо)', 'head@demo.local', ['head'], true);
        $lawyer = $this->staff('Юрист (демо)', 'lawyer@demo.local', ['lawyer'], true);
        SellerRequisites::firstOrCreate(['seller_entity_id' => $seller->id, 'version' => 1], [
            'name_full' => 'ДЕМО Общество с ограниченной ответственностью «Пример»', 'name_short' => 'ДЕМО ООО «Пример»',
            'inn' => '0000000000', 'kpp' => '000000000', 'ogrn' => '0000000000000', 'legal_address' => 'ДЕМО-адрес',
            'bank_name' => 'ДЕМО-банк', 'bik' => '000000000', 'account' => '40702810000000000000', 'corr_account' => '30101810000000000000',
            'signer_name' => 'Демо Д. Д.', 'signer_position' => 'Директор', 'signer_basis' => 'Устава', 'vat_rate_default' => '20',
            'valid_from' => now()->subMonth()->toDateString(), 'status' => ApprovalStatus::Approved,
            'created_by' => $lawyer->id, 'approved_by' => $headStaff->id, 'approved_at' => now(),
        ]);

        $regions = [];
        foreach ([['smr', 'Самара', 'Europe/Samara', true], ['ekb', 'Екатеринбург', 'Asia/Yekaterinburg', true], ['msk', 'Москва', 'Europe/Moscow', false]] as $i => [$code, $name, $tz, $active]) {
            $regions[$code] = Region::firstOrCreate(['code' => $code], [
                'name' => $name, 'timezone' => $tz, 'seller_entity_id' => $seller->id, 'is_active' => $active, 'sort' => $i,
                'phone' => 'ДЕМО', 'ship_from_address' => "ДЕМО-склад, {$name}",
            ]);
            PickupPoint::firstOrCreate(['region_id' => $regions[$code]->id, 'name' => "ДЕМО-площадка {$name}"], [
                'address' => "{$name}, ДЕМО-адрес площадки", 'work_hours' => 'Пн–Пт 8:00–17:00', 'instructions' => 'При себе доверенность на получение',
            ]);
        }

        $this->staff('Администратор (демо)', 'admin@demo.local', ['admin'], true);
        $manager = $this->staff('Менеджер Самара (демо)', 'manager@demo.local', ['manager'], false, [$regions['smr']->id]);
        $this->staff('Закупщик (демо)', 'procurement@demo.local', ['procurement'], true);
        $this->staff('Бухгалтер (демо)', 'accountant@demo.local', ['accountant'], true);
        $this->staff('Логист (демо)', 'logistics@demo.local', ['logistics'], true);

        $this->catalog($regions);

        PricingRule::firstOrCreate(['scope' => 'global'], ['markup_percent' => '12', 'min_margin_percent' => '5']);
        $supplier = Supplier::firstOrCreate(['name' => 'ДЕМО-поставщик «Металлобаза»'], ['ship_from_address' => 'Самара, ДЕМО-база', 'rating' => 4]);
        $supplier->regions()->syncWithoutDetaching([$regions['smr']->id, $regions['ekb']->id]);
        foreach (Product::query()->limit(6)->get() as $product) {
            $supplier->offers()->firstOrCreate(['product_id' => $product->id, 'region_id' => $regions['smr']->id], [
                'purchase_price' => $product->base_unit->value === 't' ? '78000' : '900', 'unit' => $product->base_unit,
                'lead_time_days' => 5, 'valid_until' => now()->addMonth()->toDateString(), 'source' => 'price_list',
            ]);
        }

        // Клиент приложения и демо-заказ, ожидающий менеджера
        $client = User::firstOrCreate(['phone' => '+79990000001'], ['name' => 'Клиент (демо)', 'pd_consent_at' => now(), 'pd_consent_version' => 'demo']);
        $org = Organization::firstOrCreate(['inn' => '0000000001', 'kpp' => '000000001'], [
            'type' => OrganizationType::Legal, 'name_full' => 'ДЕМО ООО «Стройка»', 'name_short' => 'ДЕМО «Стройка»',
            'verification_status' => VerificationStatus::Verified, 'region_id' => $regions['smr']->id, 'manager_id' => $manager->id,
        ]);
        $org->members()->firstOrCreate(['user_id' => $client->id], ['role' => MemberRole::Owner]);
        $address = $org->addresses()->firstOrCreate(['label' => 'Стройплощадка'], [
            'address' => 'Самара, ДЕМО-улица, 1', 'contact_name' => 'Прораб', 'contact_phone' => '+79990000002', 'access_notes' => 'Въезд для длинномера с торца',
        ]);

        if ($org->orders()->doesntExist()) {
            $cart = Cart::firstOrCreate(['user_id' => $client->id, 'organization_id' => $org->id, 'region_id' => $regions['smr']->id]);
            foreach (Product::query()->whereHas('category', fn ($q) => $q->where('slug', 'krug'))->limit(2)->get() as $p) {
                $cart->items()->create(['product_id' => $p->id, 'qty' => '1.5', 'unit' => 't', 'comment' => 'Порезать по 3 м']);
            }
            app(OrderWorkflow::class)->createFromCart($client, $org, $regions['smr'], $cart, [
                'receive_method' => 'delivery', 'delivery_address_id' => $address->id,
                'desired_date_from' => now()->addWeek()->toDateString(), 'client_comment' => 'ДЕМО-заказ',
            ]);

            // Второй заказ — уже с отправленным предложением
            $cart->items()->create(['product_id' => Product::query()->where('slug', 'like', 'list%')->value('id'), 'qty' => '3', 'unit' => 't']);
            $order = app(OrderWorkflow::class)->createFromCart($client, $org, $regions['smr'], $cart, ['receive_method' => 'pickup', 'pickup_point_id' => $regions['smr']->pickupPoints()->value('id')]);
            $workflow = app(OrderWorkflow::class);
            $workflow->takeForReview($order, Actor::staff($manager));
            $offer = $workflow->createOfferDraft($order->refresh(), Actor::staff($manager));
            $workflow->sendOffer($offer, Actor::staff($manager));
        }

        $this->command?->info('Демо-данные загружены. Вход в админку: head@demo.local / '.self::PASSWORD.' (и admin@, manager@, procurement@, accountant@, logistics@, lawyer@demo.local)');
    }

    /** @param list<string> $roles */
    private function staff(string $name, string $email, array $roles, bool $allRegions, array $regionIds = []): StaffUser
    {
        $staff = StaffUser::firstOrCreate(['email' => $email], ['name' => $name, 'password' => self::PASSWORD, 'all_regions' => $allRegions]);
        $staff->syncRoles($roles);
        $staff->regions()->sync($regionIds);

        return $staff;
    }

    /** Небольшой каталог с теоретической массой по сортаменту (для проверки пересчёта единиц). */
    private function catalog(array $regions): void
    {
        $attrs = [];
        foreach ([
            ['grade', 'Марка стали', 'text', null], ['gost', 'ГОСТ', 'text', null], ['diameter', 'Диаметр', 'number', 'мм'],
            ['thickness', 'Толщина', 'number', 'мм'], ['technology', 'Технология', 'enum', null],
        ] as [$code, $name, $type, $unit]) {
            $attrs[$code] = Attribute::firstOrCreate(['code' => $code], ['name' => $name, 'type' => $type, 'unit' => $unit,
                'options' => $code === 'technology' ? ['Горячекатаный', 'Холоднокатаный'] : null]);
        }

        $sort = Category::firstOrCreate(['slug' => 'sortovoj-prokat'], ['name' => 'Сортовой прокат', 'sort' => 1]);
        $krug = Category::firstOrCreate(['slug' => 'krug'], ['name' => 'Круг стальной', 'parent_id' => $sort->id]);
        $list = Category::firstOrCreate(['slug' => 'list-stalnoj'], ['name' => 'Лист стальной', 'sort' => 2]);
        foreach ([[$krug, ['grade', 'gost', 'diameter']], [$list, ['grade', 'gost', 'thickness', 'technology']]] as [$category, $codes]) {
            foreach ($codes as $i => $code) {
                $category->categoryAttributes()->firstOrCreate(['attribute_id' => $attrs[$code]->id], ['sort' => $i]);
            }
        }

        // Круг: масса 1 м = 0,00617 × d² кг (сортамент по ГОСТ 2590)
        foreach (['Ст3' => 72000, '40Х' => 95000, '12Х18Н10Т' => 420000] as $grade => $price) {
            foreach ([12, 16, 20, 25, 32] as $d) {
                $this->product($krug, "Круг {$d} мм {$grade}", ['grade' => $grade, 'gost' => 'ГОСТ 2590-2006', 'diameter' => $d],
                    't', (string) round(0.00617 * $d * $d, 3), 6000, $regions, $price + $d * 100);
            }
        }
        // Лист г/к: масса 1 м² = 7,85 × t кг
        foreach ([3, 4, 6, 10] as $t) {
            $this->product($list, "Лист г/к {$t} мм Ст3пс 1500×6000", ['grade' => 'Ст3пс', 'gost' => 'ГОСТ 19903-2015', 'thickness' => $t, 'technology' => 'Горячекатаный'],
                't', null, 6000, $regions, 78000, $t >= 10 ? Availability::PriceOnRequest : Availability::MadeToOrder);
        }
    }

    private function product(Category $category, string $name, array $attrs, string $unit, ?string $weightPerM, int $length, array $regions, int $price, Availability $availability = Availability::MadeToOrder): void
    {
        $product = Product::firstOrCreate(['slug' => Str::slug($name)], [
            'category_id' => $category->id, 'name' => $name, 'attrs' => $attrs, 'grade' => $attrs['grade'] ?? null, 'gost' => $attrs['gost'] ?? null,
            'base_unit' => $unit, 'weight_per_m' => $weightPerM, 'std_length_mm' => $length, 'max_length_mm' => $length, 'is_oversize' => $length > 3000,
        ]);
        foreach ($regions as $region) {
            $product->regionProducts()->firstOrCreate(['region_id' => $region->id], [
                'availability' => $availability, 'public_price' => $availability === Availability::PriceOnRequest ? null : $price,
                'price_unit' => $unit, 'price_valid_until' => now()->addWeeks(2)->toDateString(), 'lead_time_min_days' => 3, 'lead_time_max_days' => 10,
            ]);
        }
    }
}
