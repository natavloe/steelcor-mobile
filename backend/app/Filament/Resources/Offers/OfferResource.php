<?php

namespace App\Filament\Resources\Offers;

use App\Enums\OfferStatus;
use App\Enums\PaymentTerms;
use App\Enums\ReceiveMethod;
use App\Enums\Unit;
use App\Filament\Concerns\GuardsByPermission;
use App\Filament\Resources\Offers\Pages\EditOffer;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Offer;
use App\Models\Product;
use App\Services\PricingService;
use App\Support\Permissions;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Предложение (версия условий) по заказу. Открывается из карточки заказа.
 * Черновик редактируется; отправленное и принятое — только просмотр.
 * Себестоимость видят только роли с правом costs.view.
 */
class OfferResource extends Resource
{
    use GuardsByPermission;

    protected static ?string $model = Offer::class;

    protected static ?string $modelLabel = 'предложение';

    protected static ?string $pluralModelLabel = 'Предложения';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $viewPermission = Permissions::ORDERS_VIEW;

    protected static ?string $editPermission = Permissions::ORDERS_VIEW;

    protected static ?string $regionColumn = null;

    public static function canCreate(): bool
    {
        return false;
    }

    private static function canEditDraft(?Offer $offer): bool
    {
        return $offer?->status === OfferStatus::Draft && (bool) auth('staff')->user()?->can(Permissions::OFFERS_MANAGE);
    }

    private static function canSeeCosts(): bool
    {
        return (bool) auth('staff')->user()?->can(Permissions::COSTS_VIEW);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->disabled(fn (?Offer $record) => ! static::canEditDraft($record))
            ->components([
                Section::make('Условия')->columns(3)->schema([
                    DateTimePicker::make('valid_until')->label('Предложение действует до')->required()->seconds(false),
                    Select::make('payment_terms')->label('Оплата')->options(PaymentTerms::class)->required()->live(),
                    TextInput::make('prepay_percent')->label('Аванс, %')->numeric()->minValue(1)->maxValue(99)
                        ->visible(fn (Get $get) => $get('payment_terms') === PaymentTerms::PrepayPartial->value || $get('payment_terms') === PaymentTerms::PrepayPartial)
                        ->required(fn (Get $get) => $get('payment_terms') === PaymentTerms::PrepayPartial->value || $get('payment_terms') === PaymentTerms::PrepayPartial),
                    TextInput::make('deferral_days')->label('Отсрочка, дней')->numeric()
                        ->visible(fn (Get $get) => in_array($get('payment_terms'), [PaymentTerms::Deferral->value, PaymentTerms::Deferral], true)),
                    Select::make('receive_method')->label('Получение')->options(ReceiveMethod::class),
                    Textarea::make('lead_time_text')->label('Срок поставки (как напечатать в документах)')->columnSpanFull(),
                    TextInput::make('reason')->label('Причина этой версии')->columnSpanFull(),
                ]),
                Section::make('Строки')->schema([
                    Repeater::make('lines')
                        ->hiddenLabel()
                        ->relationship('lines')
                        ->orderColumn('sort')
                        ->addActionLabel('Добавить строку')
                        ->defaultItems(0)
                        ->columns(6)
                        ->itemLabel(fn (array $state) => $state['name'] ?? null)
                        ->schema([
                            Select::make('line_type')->label('Тип')->required()->default('goods')
                                ->options(['goods' => 'Товар', 'delivery' => 'Доставка', 'service' => 'Услуга']),
                            TextInput::make('name')->label('Наименование')->required()->columnSpan(3),
                            TextInput::make('grade')->label('Марка'),
                            TextInput::make('gost')->label('ГОСТ / ТУ'),
                            TextInput::make('size_text')->label('Размер'),
                            TextInput::make('qty')->label('Кол-во')->numeric()->required(),
                            Select::make('unit')->label('Ед.')->options(Unit::class)->required(),
                            TextInput::make('unit_price')->label('Цена за ед., ₽ (с НДС)')->numeric()->required(),
                            TextInput::make('vat_rate')->label('НДС, %')->numeric()->required()->default(0),
                            Textarea::make('lead_time_text')->label('Срок по строке')->rows(1),
                            Hidden::make('product_id'),
                            // Себестоимость не просто скрывается, а не включается в форму без права costs.view:
                            // скрытые поля Filament всё равно загружают данные в состояние страницы.
                            ...static::costComponents(),
                        ]),
                ]),
                Section::make('Итоги (пересчитываются при сохранении)')->columns(4)->schema([
                    TextInput::make('goods_total')->label('Товары')->disabled()->dehydrated(false)->suffix('₽'),
                    TextInput::make('goods_vat')->label('в т. ч. НДС')->disabled()->dehydrated(false)->suffix('₽'),
                    TextInput::make('delivery_total')->label('Доставка')->disabled()->dehydrated(false)->suffix('₽'),
                    TextInput::make('grand_total')->label('Итого к оплате')->disabled()->dehydrated(false)->suffix('₽'),
                ]),
            ]);
    }

    /** @return list<\Filament\Schemas\Components\Component> */
    private static function costComponents(): array
    {
        if (! static::canSeeCosts()) {
            return [];
        }

        return [
            Text::make(fn (Get $get, $livewire) => static::priceHint($get, $livewire->getRecord()))->columnSpanFull(),
            Group::make()
                ->relationship('cost')
                ->columns(6)
                ->columnSpanFull()
                ->schema([
                    Select::make('supplier_id')->label('Поставщик (план)')->relationship('supplier', 'name')->searchable()->preload(),
                    TextInput::make('purchase_price')->label('Закупка за ед., ₽')->numeric(),
                    TextInput::make('logistics_cost')->label('Логистика, ₽')->numeric()->default(0),
                    TextInput::make('handling_cost')->label('Погрузка/разгрузка, ₽')->numeric()->default(0),
                    TextInput::make('other_costs')->label('Прочее, ₽')->numeric()->default(0),
                    TextInput::make('margin_percent')->label('Маржа, %')->disabled()->dehydrated(false),
                ]),
        ];
    }

    /** Подсказка цены для менеджера: лучшая закупочная цена и цена по правилам наценки. */
    private static function priceHint(Get $get, ?Offer $offer): string
    {
        $product = ($id = $get('product_id')) ? Product::find($id) : null;
        if ($product === null || $offer === null) {
            return 'Подсказка цены: позиция не из каталога.';
        }
        $hint = app(PricingService::class)->suggest($product, $offer->order->region_id, $offer->order->organization_id);
        if ($hint === null) {
            return 'Подсказка цены: нет актуальных предложений поставщиков.';
        }

        return sprintf('Подсказка: закупка от %s ₽/%s (%s), цена по правилам наценки %s ₽.',
            $hint['purchase_price'], $hint['unit']->label(), $hint['supplier_offer']->supplier->name, $hint['suggested_price']);
    }

    /** Списка предложений нет — «назад» ведёт к списку заказов. */
    public static function getIndexUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?Model $tenant = null, bool $shouldGuessMissingParameters = false): string
    {
        return OrderResource::getUrl('index', isAbsolute: $isAbsolute, panel: $panel, tenant: $tenant);
    }

    public static function table(Table $table): Table
    {
        return $table;
    }

    public static function getPages(): array
    {
        return [
            'edit' => EditOffer::route('/{record}/edit'),
        ];
    }
}
