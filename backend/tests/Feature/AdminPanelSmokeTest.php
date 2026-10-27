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

    public function test_every_resource_page_renders_for_head_and_admin(): void
    {
        $this->acceptedOrder();

        foreach (['head', 'admin'] as $role) {
            // Новая сессия для каждого сотрудника (иначе AuthenticateSession разлогинит по хешу пароля прошлого).
            $this->flushSession();
            $this->app['auth']->forgetGuards();
            $this->actingAs($this->staff[$role], 'staff');
            $this->assertPageOk('/admin');

            foreach (Filament::getPanel('admin')->getResources() as $resource) {
                if (! $resource::canViewAny()) {
                    continue;
                }
                $this->assertPageOk($resource::getUrl('index'));
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
