# Notification Service

Laravel 12 микросервис уведомлений для тестового задания: массовая отправка SMS/Email, приоритезация transactional traffic, статусы доставки, idempotency и интеграционные тесты.

## Что внутри

- Laravel 12, PHP 8.3, Eloquent ORM.
- PostgreSQL для подписчиков, batch-ей, уведомлений и попыток доставки.
- Redis как cache/in-memory слой.
- Apache Kafka через `mateusjunges/laravel-kafka`.
- Два Kafka topic: `notifications.transactional` и `notifications.marketing`.
- Fake SMS/Email провайдеры с управляемыми ошибками по подписчику.
- Swagger UI: `http://localhost:8080/docs`.

В коде есть короткие русские комментарии в местах, где Laravel отличается от Symfony: `ServiceProvider`, config-файлы, producer/consumer.

## Запуск через Docker Desktop

```bash
cd notification-service
docker compose up --build
```

Compose поднимает:

- `nginx` на `http://localhost:8080`;
- `app` PHP-FPM;
- `postgres` на host-порту `54320`;
- `redis` на host-порту `63790`;
- `kafka` на host-порту `9094`, внутри docker-сети брокер доступен как `kafka:19092`;
- `kafka-init`, который создает topics;
- `consumer-transactional`;
- `consumer-marketing`;
- `setup`, который выполняет миграции и сиды.

Тестовые подписчики создаются с ID `1..10`. Подписчик `4` имитирует постоянную ошибку провайдера, подписчик `5` имитирует временную ошибку на первой попытке.

Seeder также создает demo batch для Postman:

```text
Batch ID:        019eb82a-1605-7392-ba67-7287397244fa
Notification ID: 019eb82a-1605-7392-ba67-7287397244fb
Subscriber ID:   1
Status:          delivered
```

## API

Swagger UI:

```text
http://localhost:8080/docs
```

### Создать batch

```bash
curl -X POST http://localhost:8080/api/notification-batches \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: critical-first-001" \
  -d "{\"channel\":\"sms\",\"priority\":\"transactional\",\"message\":\"Критичное изменение маршрута\",\"recipient_ids\":[1]}"
```

Повтор `POST /api/notification-batches` с тем же `Idempotency-Key` вернет тот же batch и не опубликует новые Kafka messages.

### Получить demo batch

```bash
curl http://localhost:8080/api/notification-batches/019eb82a-1605-7392-ba67-7287397244fa
```

### Получить историю подписчика

```bash
curl http://localhost:8080/api/subscribers/1/notifications
```

### Маркетинговая рассылка

```bash
curl -X POST http://localhost:8080/api/notification-batches \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: marketing-001" \
  -d "{\"channel\":\"email\",\"priority\":\"marketing\",\"message\":\"Маркетинговая рассылка\",\"recipient_ids\":[2,3,4,5,6,7,8,9]}"
```

Подписчик `4` в этом сценарии перейдет в `dropped`, потому что fake provider вернет постоянную ошибку.

## Тесты

На Windows с OSPanel можно запускать тесты без Kafka broker и без `ext-rdkafka`: в `APP_ENV=testing` используется `FakeMessagePublisher`.

```bash
php artisan test
```

Если локальный `php` не в PATH:

```bash
C:\OSPanel\modules\PHP-8.3\php.exe vendor\phpunit\phpunit\phpunit
```

Покрытые сценарии:

- 10 сообщений: первое и последнее transactional, остальные marketing; локальный drain обрабатывает transactional перед marketing.
- Постоянная ошибка провайдера переводит уведомление в `dropped`.
- Idempotency-key защищает от повторного создания notifications и Kafka messages.
- Временная ошибка провайдера ретраится и затем становится `delivered`.
- API истории подписчика возвращает статусы и attempts.
- Integration suite проверяет чтение seeded batch, историю подписчика и end-to-end API -> БД -> publisher -> delivery -> provider attempt.

## Полезные команды

Локально обработать все queued notifications без Kafka, transactional first:

```bash
php artisan notifications:drain-local
```

Запустить consumer вручную:

```bash
php artisan notifications:consume transactional
php artisan notifications:consume marketing
```

Сбросить БД в Docker:

```bash
docker compose down -v
docker compose up --build
```
