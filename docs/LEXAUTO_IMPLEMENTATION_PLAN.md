# План внедрения: Telegram-бот для розыгрыша (LEXAUTO)

**Версия ТЗ:** 7.0 (ФИНАЛ: Разделение новичков/старичков, Докупка, Бронь, Web-админка)

---

## 1. Общие вводные

| Параметр | Значение |
|----------|----------|
| Цель | Продажа билетов («наклеек») на розыгрыш с автоматическим учётом остатков |
| Платформа | Telegram Bot + Web-интерфейс администратора |
| Ключевое требование | Строгий учёт мест (не продать больше, чем есть), удобная докупка для уже зарегистрированных |

**Примечание:** LEXAUTO — отдельный продукт от бота анализа договоров (p-d-a-b). Реализуется в том же Laravel-проекте под префиксом `lexauto_` (таблицы, маршруты, namespace) для возможности раздельного деплоя или выделения в отдельный репозиторий позже.

---

## 2. Структура БД (миграции)

### 2.1 `lexauto_users`
| Поле | Тип | Описание |
|------|-----|----------|
| id | bigint PK | |
| tg_id | bigint unique | Telegram user id |
| username | string nullable | @username |
| fio | string | ФИО полностью |
| phone | string | Телефон |
| created_at, updated_at | timestamp | |

### 2.2 `lexauto_settings`
| Поле | Тип | Описание |
|------|-----|----------|
| id | bigint PK | |
| key | string unique | total_seats, price, qr_image, google_sheet_url, reservation_minutes |
| value | text nullable | |
| created_at, updated_at | timestamp | |

### 2.3 `lexauto_orders`
| Поле | Тип | Описание |
|------|-----|----------|
| id | bigint PK | |
| user_id | FK lexauto_users | |
| status | enum | reserved, review, sold, rejected |
| reserved_until | timestamp nullable | Истечение брони |
| quantity | int unsigned | Кол-во билетов |
| amount | decimal(10,2) | Сумма к оплате |
| check_file_id | string nullable | Telegram file_id чека (PDF) |
| created_at, updated_at | timestamp | |

### 2.4 `lexauto_tickets`
| Поле | Тип | Описание |
|------|-----|----------|
| id | bigint PK | |
| number | int unsigned unique | Порядковый номер билета (1, 2, 3...) |
| user_id | FK lexauto_users | |
| order_id | FK lexauto_orders | |
| created_at | timestamp | |

- Номера билетов выдаются последовательно при одобрении заявки (max(number)+1, ...).

---

## 3. Логика User Flow (бот)

### 3.1 Команда /start
1. Вычислить: `free_seats = total_seats - (проданные билеты) - (забронированные места по заявкам reserved/review)`.
2. Найти пользователя по `tg_id` в `lexauto_users`.

| Условие | Сценарий | Действие бота |
|---------|----------|----------------|
| Новый + места есть | А | Приветствие + кнопка [Заполнить анкету] |
| Уже был + места есть | Б | «Рад видеть снова, {Имя}!» + текущие номера + [Купить ещё] |
| Мест нет + есть билеты | В1 | «Места закончились! Ты уже в игре, номера: ...» |
| Мест нет + нет билетов | В2 | «Все места заняты. Следи за новостями.» |

### 3.2 Регистрация (только сценарий А)
- FSM: ожидание ФИО → ожидание телефона.
- Сохранить в `lexauto_users`, перейти к 3.3.

### 3.3 Выбор количества и бронирование
- Бот: «Стоимость одной наклейки: {price} руб. Введите количество (цифрой):»
- Пользователь вводит N.
- **Backend (транзакция):**
  1. Посчитать свободные места.
  2. Если N > free → ответ «Осталось всего X. Введите другое число».
  3. Иначе: создать `lexauto_orders` (status=reserved, reserved_until=now+30min, quantity=N, amount=N*price).
  4. Занять места условно (бронь).
- Ответ: «Заявка принята. Переходим к оплате» + реквизиты + QR + инструкции + «Пришлите чек в PDF».

### 3.4 Приём чека
- Валидация: только PDF (document.mime_type или file_name).
- Обновить заявку: status=review, check_file_id=file_id, таймер брони не трогать (уже не удаляем по таймеру после приёма чека).
- Сообщение: «Чек получен! Статус: На проверке у администратора.»

### 3.5 Истечение брони (Cron)
- Раз в минуту: найти заявки status=reserved и reserved_until < now.
- Удалить или status=rejected, освободить места.
- Отправить юзеру: «Время брони вышло.»

---

## 4. Панель администратора (Web)

- Раздел **LEXAUTO → Заявки** (или в том же /admin с подменю).
- Список заявок со статусом review (и опционально reserved, sold, rejected).
- Карточка заявки:
  - ФИО, телефон, количество, сумма, дата.
  - Файл чека (ссылка на скачивание по file_id или сохранённый путь).
  - Кнопки: [✅ Одобрить] [❌ Отклонить] [✏️ Редактировать].

### 4.1 Одобрить
- Статус → sold.
- Выдать следующие свободные номера билетов (insert в lexauto_tickets с number = следующему после max).
- Отправить данные в Google Sheets (ID заказа | ФИО | Телефон | Сумма | Номера | Дата).
- Сообщение юзеру: «Платёж подтверждён! Ваши номерки: 101, 102.»

### 4.2 Отклонить
- Статус → rejected. Места освобождаются.
- Сообщение: «Чек не принят. Оформите заявку заново.»

### 4.3 Редактировать
- Форма: реальная сумма, реальное кол-во билетов. После сохранения админ жмёт Одобрить — логика как в 4.1.

---

## 5. Технические требования

### 5.1 Race conditions
- При создании брони (шаг 3.3) использовать DB::transaction и блокировку по строкам/таблице (SELECT FOR UPDATE по счётчику занятых мест или по сумме quantity заявок reserved+review).

### 5.2 Cron
- Команда: `php artisan lexauto:clean-reservations` (каждую минуту в scheduler).
- Освобождать просроченные reserved, уведомлять пользователя.

### 5.3 Google Sheets
- Запись только при одобрении. Колонки: ID заказа | ФИО | Телефон | Сумма | Номера | Дата.
- Библиотека: Google API Client или простой export по URL (если используется форма/webhook таблицы).

---

## 6. Порядок реализации (задачи)

| № | Задача | Зависимости |
|---|--------|-------------|
| 1 | Миграции: lexauto_users, lexauto_settings, lexauto_orders, lexauto_tickets | — |
| 2 | Модели: LexautoUser, LexautoSetting, LexautoOrder, LexautoTicket | 1 |
| 3 | Сидер/конфиг настроек по умолчанию (total_seats, price, reservation_minutes) | 2 |
| 4 | Сервис расчёта свободных мест и бронирования (транзакция) | 2 |
| 5 | Webhook LEXAUTO: маршрут, контроллер, FSM (состояния: start, ask_fio, ask_phone, ask_quantity, wait_receipt) | 4 |
| 6 | Хранение FSM состояния пользователя (cache или lexauto_user_states) | 5 |
| 7 | Команда lexauto:clean-reservations + scheduler | 2 |
| 8 | Сервис Google Sheets (запись при одобрении) | 2 |
| 9 | API админки: список заявок, одобрить/отклонить/редактировать | 2 |
| 10 | Страницы Vue: список заявок, карточка заявки с кнопками | 9 |

---

## 7. Размещение в проекте

- **Маршрут webhook:** `POST /api/telegram/lexauto-webhook` (отдельный бот, свой токен в настройках или .env).
- **Конфиг:** `config/lexauto.php` (или настройки в БД lexauto_settings).
- **Модели:** `App\Models\Lexauto\*`.
- **Контроллеры API:** `App\Http\Controllers\Api\Lexauto\*`.
- **Фронт:** роут `/admin/lexauto/orders`, страница заявок в существующем AdminLayout.

---

## 8. Переменные окружения (.env)

```env
LEXAUTO_BOT_TOKEN=          # Токен бота розыгрыша (от @BotFather)
LEXAUTO_RESERVATION_MINUTES=30
LEXAUTO_TOTAL_SEATS=100
LEXAUTO_PRICE=500
LEXAUTO_GOOGLE_SHEET_URL=   # URL для отправки данных (webhook формы или API)
```

Webhook бота в Telegram: `https://<ваш-домен>/api/telegram/lexauto-webhook`

---

## 9. Критерии приёмки

- [ ] /start корректно различает новичка и старичка, показывает нужный сценарий.
- [ ] Бронь создаётся в транзакции, при нехватке мест — сообщение об ошибке.
- [ ] Два одновременных запроса на последнее место: один успех, второй — отказ.
- [ ] Cron раз в минуту снимает просроченные брони и уведомляет юзера.
- [ ] При приёме PDF статус → review, таймер не сбрасывает заявку.
- [ ] Админка: одобрить → билеты выданы, запись в Google Sheets, уведомление в Telegram.
- [ ] Админка: отклонить → места освобождены, уведомление юзеру.
- [ ] Редактирование суммы/количества перед одобрением работает.

---

## 10. Реализовано в проекте

- Миграции: `lexauto_users`, `lexauto_settings`, `lexauto_orders`, `lexauto_tickets`, `lexauto_user_states`.
- Модели: `App\Models\Lexauto\*`.
- Сервисы: `LexautoSeatsService` (бронь в транзакции), `LexautoGoogleSheetsService` (отправка в таблицу).
- Webhook: `POST /api/telegram/lexauto-webhook`, `LexautoWebhookController` (FSM: регистрация → количество → чек).
- Команда: `php artisan lexauto:clean-reservations` (ежеминутно в scheduler).
- API админки: `GET/PUT /lexauto/orders`, `POST .../approve`, `.../reject`.
- Страница админки: `/admin/lexauto/orders` (фильтр, Одобрить, Отклонить, Редактировать).
- Сидер: `LexautoSettingsSeeder` — настройки по умолчанию.
