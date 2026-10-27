<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Filament\Resources\Offers\Pages\EditOffer;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\BuildsWorld;
use Tests\TestCase;

/** Менеджер в админке: берёт заказ, правит предложение, видит пересчёт; себестоимость — только по правам. */
class AdminOfferEditingTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
    }

    public function test_manager_takes_order_and_edits_offer_lines(): void
    {
        $order = $this->placeOrder('3');
        $this->actingAs($this->staff['head'], 'staff');

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('take')
            ->assertHasNoErrors();
        $this->assertSame(OrderStatus::UnderReview, $order->refresh()->status);

        $offer = $this->workflow()->createOfferDraft($order, $this->actor('head'));
        $component = Livewire::test(EditOffer::class, ['record' => $offer->getRouteKey()]);
        $lines = $component->get('data.lines');
        $key = array_key_first($lines);
        $component
            ->set("data.lines.{$key}.unit_price", '100000')
            ->set("data.lines.{$key}.cost.purchase_price", '85000')
            ->call('save')
            ->assertHasNoFormErrors();

        $offer->refresh();
        $this->assertSame('300000.00', (string) $offer->grand_total);
        $line = $offer->lines()->first();
        $this->assertSame('255000.00', (string) $line->cost->purchase_amount);
        $this->assertSame('15.00', (string) $line->cost->margin_percent);
    }

    public function test_manager_without_cost_rights_does_not_see_purchase_fields(): void
    {
        $order = $this->placeOrder();
        $this->workflow()->takeForReview($order, $this->actor('manager'));
        $offer = $this->workflow()->createOfferDraft($order->refresh(), $this->actor('manager'));
        $offer->lines()->first()->cost()->create(['purchase_price' => '77777']);

        $this->actingAs($this->staff['manager'], 'staff');
        $this->get(\App\Filament\Resources\Offers\OfferResource::getUrl('edit', ['record' => $offer]))
            ->assertOk()
            ->assertDontSee('77777')
            ->assertDontSee('Закупка за ед.');

        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->staff['head'], 'staff');
        $this->get(\App\Filament\Resources\Offers\OfferResource::getUrl('edit', ['record' => $offer]))
            ->assertOk()
            ->assertSee('Закупка за ед.');
    }
}
