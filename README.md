# City Events API (Bitrix Demo)

REST API для каталога городских событий, реализованное на базе CMS 1С-Битрикс в рамках тестового задания.

- **Сайт:** [http://events.sandbox.galahad.beget.tech/](http://events.sandbox.galahad.beget.tech/)
- **Репозиторий:** [ссылка на ваш репозиторий]
- **API Base URL:** `http://events.sandbox.galahad.beget.tech/events/v1`
- **Документация OpenAPI:** [openapi.yaml](openapi.yaml)

## Функциональные возможности

- Хранение событий в инфоблоке Bitrix.
- Полный CRUD через REST API с авторизацией по API-ключу.
- Автоматический расчёт популярности события по формуле.
- Курсорная пагинация без дубликатов и пропусков.
- Фильтрация по дате, месту, статусу, тегам и популярности.
- Сортировка: по умолчанию (ближайшие + популярные) или только по популярности.
- Интеграция с админкой Bitrix: при редактировании через административный интерфейс автоматически пересчитывается популярность и увеличивается счётчик изменений.

## Авторизация

Для доступа к API необходимо передавать заголовок: X-API-Key: secret-api-key-2026

При отсутствии или неверном ключе возвращается ошибка 401.

## Примеры запросов

### Получить список событий (первые 10)
```bash
curl -H "X-API-Key: secret-api-key-2026" "http://events.sandbox.galahad.beget.tech/events/v1/"
{
  "data": [
    {
      "id": 1,
      "title": "Лекция о космосе",
      "place": "Планетарий",
      "start_at": "2026-02-15 19:00:00",
      "end_at": "2026-04-15 21:00:00",
      "tags": ["космос", "наука"],
      "capacity": 200,
      "status": "draft",
      "popularity": 4,
      "change_number": 4
    },
    {
      "id": 2,
      "title": "Концерт классики",
      "place": "Филармония",
      "start_at": "2026-03-10 18:30:00",
      "end_at": "2026-03-10 21:00:00",
      "tags": ["музыка", "классика"],
      "capacity": 500,
      "status": "published",
      "popularity": 3,
      "change_number": 1
    }
    // ... остальные события
  ],
  "meta": {
    "total": 12,
    "per_page": 10
  },
  "links": {
    "self": "/events/v1/?limit=10",
    "next": "/events/v1/?limit=10&cursor=...",
    "prev": null
  }
}
```

### Фильтрация по дате и статусу: 
```bash
curl -H "X-API-Key: secret-api-key-2026" "http://events.sandbox.galahad.beget.tech/events/v1/?from=2026-03-01&to=2026-03-31&status=published&limit=5"
// Если событий нет:
{
  "data": [],
  "meta": {
    "total": 0,
    "per_page": 5
  },
  "links": {
    "self": "/events/v1/?from=2026-03-01&to=2026-03-31&status=published&limit=5",
    "next": null,
    "prev": null
  }
}
```

### Создание события: 
```bash
curl -X POST -H "X-API-Key: secret-api-key-2026" -H "Content-Type: application/json" -d '{
  "title": "Кинопоказ под открытым небом",
  "place": "Набережная",
  "start_at": "2026-06-12 21:00",
  "end_at": "2026-06-12 23:30",
  "tags": ["кино", "лето"],
  "capacity": 1000,
  "status": "draft"
}' "http://events.sandbox.galahad.beget.tech/events/v1/"
{
  "id": 14,
  "title": "Кинопоказ под открытым небом",
  "place": "Набережная",
  "start_at": "2026-06-12 21:00:00",
  "end_at": "2026-06-12 23:30:00",
  "tags": ["кино", "лето"],
  "capacity": 1000,
  "status": "draft",
  "popularity": 3,
  "change_number": 0
}
```
### Ошибка при создании события с низкой популярностью:
```bash
curl -X POST -H "X-API-Key: secret-api-key-2026" -H "Content-Type: application/json" -d '{
  "title": "Скучное событие",
  "place": "Подвал",
  "start_at": "2026-12-31 10:00",
  "end_at": "2026-12-31 12:00",
  "tags": [],
  "capacity": 10,
  "status": "draft"
}' "http://events.sandbox.galahad.beget.tech/events/v1/"
{
  "error": {
    "message": "Low popularity Not interesting Event",
    "code": "ERROR"
  }
}
```

### Получение события по ID (с рекомендацией по вторникам/средам): 
```bash
curl -H "X-API-Key: secret-api-key-2026" "http://events.sandbox.galahad.beget.tech/events/v1/1"
{
  "id": 1,
  "title": "Лекция о космосе",
  "place": "Планетарий",
  "start_at": "2026-02-15 19:00:00",
  "end_at": "2026-04-15 21:00:00",
  "tags": ["космос", "наука"],
  "capacity": 200,
  "status": "draft",
  "popularity": 4,
  "change_number": 4
}
{
  "id": 1,
  "title": "Лекция о космосе",
  "place": "Планетарий",
  "start_at": "2026-02-15 19:00:00",
  "end_at": "2026-04-15 21:00:00",
  "tags": ["космос", "наука"],
  "capacity": 200,
  "status": "draft",
  "popularity": 4,
  "change_number": 4,
  "recommendation": "Рекомендуем по вторникам и средам"
}
```

### Обновление статуса (необходимо предварительно очистить кэш): 
```bash
curl -X PUT -H "X-API-Key: secret-api-key-2026" -H "Content-Type: application/json" -d '{"status":"published"}' "http://events.sandbox.galahad.beget.tech/events/v1/1"
{
  "id": 1,
  "title": "Лекция о космосе",
  "place": "Планетарий",
  "start_at": "2026-02-15 19:00:00",
  "end_at": "2026-04-15 21:00:00",
  "tags": ["космос", "наука"],
  "capacity": 200,
  "status": "published",
  "popularity": 4,
  "change_number": 5
}
```

### Удаление события (если разрешено): 
```bash
curl -X DELETE -H "X-API-Key: secret-api-key-2026" "http://events.sandbox.galahad.beget.tech/events/v1/6"
// Пустое тело...
```

### Удаление события: 
```bash
curl -X DELETE -H "X-API-Key: secret-api-key-2026" "http://events.sandbox.galahad.beget.tech/events/v1/6"
{
  "error": {
    "message": "Cannot delete published event",
    "code": "ERROR"
  }
}
```

### Попытка удалить событие, которое уже началось:
```bash
curl -X DELETE -H "X-API-Key: secret-api-key-2026" "http://events.sandbox.galahad.beget.tech/events/v1/9"
{
  "error": {
    "message": "Cannot delete event that has already started",
    "code": "ERROR"
  }
}
```

## Коды ответов:
* 200 - Успешный GET/PUT
* 201 - Успешный POST (создание)
* 204	- Успешный DELETE (нет содержимого)
* 400	- Ошибка валидации или низкая популярность
* 401	- Неавторизован (отсутствует/неверный ключ)
* 403	- Действие запрещено (например, удаление опубликованного события)
* 404	- Ресурс не найден
* 422	- Ошибка валидации полей
* 429	- Слишком много запросов (заглушка)
* 500	- Внутренняя ошибка сервера

## Расчёт популярности. Формула: 
```php
raw = 3 + (capacity / 1000) - (days_to_start / 10) + количество_тегов
popularity = max(1, min(5, round(raw)))

days_to_start – количество дней от сегодня до даты начала (отрицательное, если событие уже началось).
Если popularity = 1, создание события запрещено (ошибка 400).
```

## Курсорная пагинация:
Пагинация реализована через курсор (закодированный JSON), который хранит последние значения сортировки. Это гарантирует отсутствие дубликатов и пропусков при листании.

## Интеграция с админкой Bitrix:

### При сохранении элемента инфоблока через административный интерфейс срабатывает обработчик, который:
1. Пересчитывает popularity.
2. Увеличивает change_number на 1.
3. Преобразует даты из строкового формата в объект \Bitrix\Main\Type\DateTime для корректной фильтрации.

## Оптимизация для более 1000 событий:

### При росте числа событий предлагаются следующие меры:
1. Индексы БД: создать составные индексы в таблице свойств инфоблока для полей START_AT, POPULARITY, ID;
2. Кеширование: использовать тегированный кеш Bitrix для списков, которые не требуют мгновенной актуальности (например, для публичных списков событий);
3. Оптимизация подсчёта total: при больших объёмах можно отказаться от точного подсчёта общего количества, заменив его приблизительным значением или убрав из ответа;
4. Асинхронный расчёт популярности: вынести пересчёт popularity в очередь (например, через агенты Bitrix или RabbitMQ) при массовом импорте/обновлении;
5. Пагинация: курсорная пагинация уже оптимизирована, но при необходимости можно добавить индексы, покрывающие сортировку.
Репликация БД: для распределения нагрузки использовать master-slave репликацию.

## Затраченное время
* Проектирование архитектуры: 4 часа
* Написание кода API и обработчиков: 12 часов
* Тестирование и отладка: 2,5 часа
* Документация (OpenAPI, Postman, README): 1,5 часа

## Итого: 20 часов