<?php

use App\Services\OrderWorkflow;
use Illuminate\Support\Facades\Schedule;

// Истёкшие предложения → «истёк срок», заказ возвращается менеджеру.
Schedule::call(fn () => app(OrderWorkflow::class)->expireOffers())
    ->name('expire-offers')
    ->everyFifteenMinutes()
    ->withoutOverlapping();
