<?php

namespace App\Providers\Filament;

use App\Filament\Support\LocalAvatarProvider;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/** Админ-панель сотрудников «Стилкор»: отдельный вход (guard staff) и второй фактор. */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('Стилкор — админка')
            ->defaultAvatarProvider(LocalAvatarProvider::class)
            ->authGuard('staff')
            ->authPasswordBroker('staff_users')
            ->login()
            ->passwordReset()
            ->profile()
            // Второй фактор (приложение-аутентификатор). Обязателен в боевой среде.
            ->multiFactorAuthentication(
                [AppAuthentication::make()->recoverable()],
                isRequired: fn (): bool => app()->isProduction(),
            )
            ->colors([
                'primary' => Color::Blue,
            ])
            ->navigationGroups([
                NavigationGroup::make('Продажи'),
                NavigationGroup::make('Закупки'),
                NavigationGroup::make('Финансы'),
                NavigationGroup::make('Доставка'),
                NavigationGroup::make('Документы'),
                NavigationGroup::make('Каталог'),
                NavigationGroup::make('Справочники'),
                NavigationGroup::make('Администрирование'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
