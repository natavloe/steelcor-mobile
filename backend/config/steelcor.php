<?php

/** Настройки бизнес-процесса «Стилкор». Значения по умолчанию — из реестра открытых вопросов (docs/02-arhitektura/README.md). */
return [
    // О-15: срок действия предложения по умолчанию, рабочих дней
    'offer_valid_weekdays' => (int) env('STEELCOR_OFFER_VALID_WEEKDAYS', 3),

    // Срок оплаты счёта, рабочих дней
    'invoice_due_weekdays' => (int) env('STEELCOR_INVOICE_DUE_WEEKDAYS', 3),

    // Версия текста согласия на обработку персональных данных
    'pd_consent_version' => env('STEELCOR_PD_CONSENT_VERSION', '2026-10'),

    // Вход по SMS
    'otp' => [
        'length' => 4,
        'ttl_minutes' => 5,
        'max_attempts' => 5,
    ],
];
