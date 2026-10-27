# Стилкор — мобильное приложение (iOS и Android)

Expo SDK 58 + Expo Router + TypeScript. Один код для iOS, Android и веб-версии.

## Экраны

| Экран | Файл |
|---|---|
| Выбор города | `src/app/region.tsx` |
| Каталог, поиск, категория с фильтрами | `src/app/(tabs)/index.tsx`, `search.tsx`, `src/app/category/[id].tsx` |
| Карточка позиции с калькулятором шт/м/кг/т | `src/app/product/[id].tsx` |
| Корзина и оформление (один экран, как в Яндекс Еде) | `src/app/(tabs)/cart.tsx`, `src/app/checkout.tsx` |
| Вход по SMS с согласием на обработку ПДн | `src/app/login.tsx` |
| Организация (подсказка по ИНН), адреса | `src/app/organization-new.tsx`, `address-new.tsx` |
| Заказы, заказ: принять условия, счёт, сроки, доставка | `src/app/(tabs)/orders.tsx`, `src/app/order/[id].tsx` |
| Профиль | `src/app/(tabs)/profile.tsx` |

Общение с сервером — `src/api/client.ts`, сессия (токен в защищённом хранилище телефона) — `src/state/session.tsx`.
Ключи банка, ЭДО и ТК в приложении не хранятся и не появятся: всё через сервер.

## Запуск

```bash
npm install
EXPO_PUBLIC_API_URL=http://<адрес-сервера>:8000 npx expo start
```

- На телефоне: приложение **Expo Go** → отсканировать QR-код (телефон и компьютер в одной сети).
- В браузере: клавиша `w` в терминале.
- Сборка для магазинов — в облаке Expo (EAS Build), свой Mac не нужен.

## Проверка

```bash
npx tsc --noEmit   # типы
npx eslint .       # линтер
```

## Чего пока нет

Push-уведомления (expo-notifications), документы (PDF/ЭДО), загрузка платёжки файлом, редактирование и приглашение сотрудников организации в интерфейсе (в API уже есть).
