<?php

namespace App\Providers;

use App\Services\CompanyLookup\CompanyLookup;
use App\Services\CompanyLookup\DaDataLookup;
use App\Services\Sms\LogSmsSender;
use App\Services\Sms\SmsSender;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Адаптеры интеграций: замена провайдера — замена одной строки здесь.
        $this->app->bind(SmsSender::class, LogSmsSender::class);
        $this->app->bind(CompanyLookup::class, DaDataLookup::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Запрос SMS-кода: не чаще 1 раза в минуту на номер и 10 раз в час с одного IP.
        RateLimiter::for('otp', fn (Request $request) => [
            Limit::perMinute(1)->by('otp-phone:'.preg_replace('/\D+/', '', (string) $request->input('phone'))),
            Limit::perHour(10)->by('otp-ip:'.$request->ip()),
        ]);
        RateLimiter::for('client-api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
    }
}
