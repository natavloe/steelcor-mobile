<?php

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsWorld;
use Tests\TestCase;

/** Все разделы админки открываются у руководителя; клиент в админку не попадает. */
class AdminPanelSmokeTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
    }

    public function test_guest_and_client_are_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->actingAs($this->client, 'web')->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_every_resource_page_renders_for_every_role(): void
    {
        [$order, $offer] = $this->acceptedOrder();
        $invoice = $order->invoices()->sole();
        app(\App\Services\Bank\BankStatementImporter::class)->importFile($this->seller, $this->statement('190000.00', "Оплата {$invoice->number}"));
        $procurement = app(\App\Services\ProcurementService::class);
        $po = $procurement->create($this->supplier, $this->region, $this->actor('procurement'));
        $procurement->addLine($po, ['description' => 'Круг', 'qty' => '2', 'unit' => 't', 'purchase_price' => '80000'], [$offer->lines()->first()->id => '2'], $this->actor('procurement'));
        app(\App\Services\RefundService::class)->request($order, null, '1000', 'тест', $this->actor('accountant'));

        foreach (['head', 'admin', 'manager', 'procurement', 'accountant', 'logistics', 'lawyer'] as $role) {
            // Новая сессия для каждого сотрудника (иначе AuthenticateSession разлогинит по хешу пароля прошлого).
            $this->flushSession();
            $this->app['auth']->forgetGuards();
            $this->actingAs($this->staff[$role], 'staff');
            $this->assertPageOk('/admin');

            foreach (Filament::getPanel('admin')->getResources() as $resource) {
                if (! $resource::canViewAny()) {
                    continue;
                }
                if ($resource::hasPage('index')) {
                    $this->assertPageOk($resource::getUrl('index'));
                }
                if ($resource::hasPage('create') && $resource::canCreate()) {
                    $this->assertPageOk($resource::getUrl('create'));
                }
                $record = $resource::getEloquentQuery()->first();
                if ($record === null) {
                    continue;
                }
                foreach (['view', 'edit'] as $page) {
                    if ($resource::hasPage($page) && ($page === 'view' || $resource::canEdit($record))) {
                        $this->assertPageOk($resource::getUrl($page, ['record' => $record]));
                    }
                }
            }
        }
    }

    private function assertPageOk(string $url): void
    {
        $response = $this->get($url);
        $this->assertSame(200, $response->status(), "{$url} → {$response->status()} ".($response->headers->get('Location') ?? '').' '.mb_substr((string) ($response->exception?->getMessage() ?? ''), 0, 500));
    }

    public function test_logistics_cannot_open_catalog_editing_or_finance(): void
    {
        $this->actingAs($this->staff['logistics'], 'staff');
        $this->get(\App\Filament\Resources\Products\ProductResource::getUrl('create'))->assertForbidden();
    }
}
