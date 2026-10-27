# Стилкор — сервер и админ-панель

Laravel 13 + Filament 5 + PostgreSQL. Клиентский API для мобильного приложения и админка для сотрудников.
Архитектура и решения: [`../docs`](../docs).

## Что внутри

| Часть | Где |
|---|---|
| Бизнес-логика заказа, статусы и «замки» | `app/Services/OrderWorkflow.php` |
| Закупки у поставщиков | `app/Services/ProcurementService.php` |
| Выписка банка и привязка платежей | `app/Services/Bank/` |
| Возвраты | `app/Services/RefundService.php` |
| Роли и права сотрудников | `app/Support/Permissions.php` |
| Админка (разделы) | `app/Filament/Resources/` |
| Клиентский API | `routes/api.php`, `app/Http/Controllers/Client/` |
| Что видит клиент (без закупочных данных) | `app/Http/Resources/Client/` |
| Настройки процесса (срок КП и др.) | `config/steelcor.php` |

## Запуск на своём компьютере

Нужны PHP 8.3+, Composer, PostgreSQL 16, Redis.

```bash
cp .env.example .env            # укажите DB_PASSWORD и прочее
composer install
php artisan key:generate
php artisan migrate --seed      # таблицы + роли
php artisan db:seed --class=DemoSeeder   # ДЕМО-данные (только не в боевой базе!)
php artisan serve
```

Админка: http://127.0.0.1:8000/admin — вход `head@demo.local` / `demo-password-123`
(есть также `admin@`, `manager@`, `procurement@`, `accountant@`, `logistics@`, `lawyer@demo.local`).

API: http://127.0.0.1:8000/api/client/v1/regions. SMS-коды при разработке пишутся в `storage/logs/laravel.log`.

## Проверка

```bash
php artisan test
```

Тесты идут на отдельной базе `steelcor_test` (PostgreSQL). Среди них — проверка, что клиентский API
не отдаёт поставщиков, закупочные цены и маржу, и что себестоимость не попадает в HTML админки
у сотрудников без права.

## Плановые задачи

В боевой среде нужен планировщик (`php artisan schedule:work` или cron) и обработчик очередей (`php artisan queue:work`).
Задача «истечение срока предложений» запускается каждые 15 минут.

## Чего пока нет (следующие этапы)

- Генерация PDF счетов и договоров по утверждённым шаблонам (этап 4).
- API банка вместо загрузки файла выписки (этап 4).
- ЭДО (этап 5), СДЭК и Деловые Линии (этап 6).
- Реальный SMS-шлюз и почтовый сервис (перед запуском).
