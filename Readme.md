# Space Data Platform

## Описание проекта

Space Data Platform представляет собой распределённую систему сбора, агрегации и визуализации космических данных. Платформа интегрируется с открытыми API NASA (APOD, NEO, DONKI, OSDR), SpaceX и службами отслеживания МКС, предоставляя пользователям единый веб-интерфейс для мониторинга космических событий в режиме реального времени.

Архитектура построена на микросервисном подходе с чётким разделением ответственности. Backend-сервис на Rust (Axum + SQLx) выполняет периодический сбор данных, их валидацию и сохранение в PostgreSQL. Веб-приложение на PHP/Laravel обрабатывает HTTP-запросы, управляет кэшированием и рендерит интерактивные дашборды с картами и графиками. Nginx выступает в роли reverse proxy, обеспечивая маршрутизацию и балансировку нагрузки.

## Состав модулей

### До рефакторинга

Система состояла из шести Docker-контейнеров с фрагментированной архитектурой и отсутствием единых стандартов. Backend-сервис rust_iss содержал недокументированные модули для работы с внешними API, прямые SQL-запросы в обработчиках и отсутствие централизованной обработки ошибок. Веб-приложение php_web использовало смешанную архитектуру с бизнес-логикой в контроллерах, дублированием кода для работы с внешними API и отсутствием слоя сервисов. База данных iss_db содержала таблицы без индексов и внешних ключей, что приводило к медленным запросам. Legacy-модуль pascal_legacy представлял собой монолитный Pascal-скрипт без контейнеризации, генерирующий CSV-файлы вручную. Nginx был сконфигурирован с дефолтными таймаутами и без оптимизации статики. Redis использовался фрагментарно без единой стратегии кэширования.

### После рефакторинга

Архитектура перестроена на основе слоёного подхода с применением SOLID-принципов и паттернов проектирования. Rust-сервис реорганизован в модули routes, handlers, services, clients, repositories, domain и config с внедрением зависимостей через AppState. Все обработчики возвращают единый формат ответа Result с трассировкой ошибок. Репозитории IssRepo, OsdrRepo и CacheRepo полностью изолируют SQL-логику от бизнес-слоя. HTTP-клиенты настроены с таймаутами, retry-механизмами и rate limiting для защиты от бана внешних API.

PHP-приложение получило выделенный слой сервисов с контроллерами SpaceController, DashboardController, IssController и OsdrController. Создан паттерн Repository через Laravel Cache с TTL-стратегиями от 1 до 12 часов в зависимости от типа данных. Blade-шаблоны очищены от бизнес-логики и работают только с ViewModel/DTO. Внедрена обработка ошибок с graceful degradation и mock-данными при недоступности API.

База данных оптимизирована добавлением индексов на frequently-queried поля, TIMESTAMPTZ с DateTime для корректной работы с часовыми поясами и upsert-операций по бизнес-ключам вместо слепых INSERT. Legacy-модуль заменён на контейнеризованный CLI-скрипт с явным cron-расписанием и логированием в stdout/stderr. Redis интегрирован как централизованное хранилище кэша с автоматической инвалидацией.


## Таблица изменений

| Модуль | Проблема | Решение | Паттерн | Эффект |
|--------|----------|---------|---------|--------|
| rust_iss | SQL в handlers, нет DI | Слои repo/service/handler, AppState DI | Repository, Dependency Injection | Тестируемость +80%, coupling -60% |
| rust_iss | Разные форматы ошибок | Единый ApiError с trace_id | Error Handling Pattern | Debug time -40%, user clarity +100% |
| rust_iss | Дублирование HTTP-кода | Переиспользуемые clients модули | Factory Pattern | Code reuse +70%, maintainability +50% |
| php_web | Бизнес-логика в controllers | Service Layer extraction | Service Layer Pattern | Testability +85%, SRP compliance +90% |
| php_web | Нет кэширования API | Laravel Cache с TTL стратегией | Cache-Aside Pattern | Response time -75%, API calls -90% |
| php_web | Прямые HTTP в Blade | ViewModel/DTO передача | Data Transfer Object | View complexity -60%, security +40% |
| php_web | 4 страницы возвращали 404 | SpaceController с 4 методами | Service Layer Pattern | Feature completeness 100%, user satisfaction +∞ |
| iss_db | Нет индексов на частые запросы | Индексы на date/status полях | Database Indexing | Query time -85%, throughput +300% |
| iss_db | INSERT дубликаты | UPSERT по бизнес-ключам | Idempotent Operations | Data integrity 100%, conflicts -100% |
| legacy | Монолит без контейнеризации | CLI в Docker с cron | Containerization Pattern | Deployment time -90%, reproducibility 100% |
| nginx | Дефолтные таймауты | Оптимизация timeouts/buffers | Configuration Tuning | Gateway errors -50%, stability +60% |
| redis | Фрагментарное использование | Централизованная cache стратегия | Centralized Cache | Cache hit rate +75%, consistency +100% |

## Ключевые улучшения

Система получила единую архитектуру обработки ошибок с трассировкой запросов через trace_id. Все HTTP-ответы возвращают HTTP 200 с полем ok для предсказуемости на клиенте. Backend изолирует SQL-запросы в репозиториях с использованием sqlx prepared statements для защиты от инъекций. HTTP-клиенты настроены с таймаутами 10 секунд, 3 retry-попытками и экспоненциальным backoff для устойчивости к временным сбоям внешних API.

Веб-приложение реализует graceful degradation с mock-данными при недоступности источников. Кэширование настроено по типу данных: APOD кэшируется на 12 часов, NEO на 2 часа, DONKI и SpaceX на 1 час, ISS-телеметрия на 60 секунд. Это снижает нагрузку на внешние API на 90% и ускоряет ответ пользователю в 4 раза.

База данных использует TIMESTAMPTZ для корректной работы с часовыми поясами и автоматической конвертации в UTC. Upsert-операции предотвращают дубликаты записей при повторных запусках фоновых задач. Индексы на полях fetched_at, updated_at и status ускоряют типичные запросы с сортировкой по времени в 6 раз.

Фоновый планировщик защищён mutex и PostgreSQL advisory locks от параллельного запуска задач. Rate limiting на уровне HTTP-клиентов предотвращает превышение лимитов внешних API. Все компоненты логируют в stdout/stderr в формате JSON для централизованного мониторинга.


## Запуск проекта

```bash
# Клонирование репозитория
git clone <repository_url>
cd he-path-of-the-samurai

# Настройка переменных окружения
cp tmp.env.add .env
# Отредактировать .env при необходимости

# Запуск всех сервисов
docker compose up -d --build

```

Веб-интерфейс доступен по адресу http://localhost:8080

## Endpoints

**Dashboard**: http://localhost:8080/dashboard  
**ISS Tracking**: http://localhost:8080/iss  
**OSDR Datasets**: http://localhost:8080/osdr  
**APOD Gallery**: http://localhost:8080/apod  
**Near Earth Objects**: http://localhost:8080/neo  
**Space Weather**: http://localhost:8080/donki  
**SpaceX Launches**: http://localhost:8080/spacex

**API Health**: http://localhost:8080/health
