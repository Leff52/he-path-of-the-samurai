-- =====================================================
-- ОПТИМИЗИРОВАННАЯ СХЕМА БАЗЫ ДАННЫХ "Кассиопея"
-- =====================================================

-- ISS fetch log с партицированием
CREATE TABLE IF NOT EXISTS iss_fetch_log (
    id BIGSERIAL,
    fetched_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    source_url TEXT NOT NULL,
    payload JSONB NOT NULL
) PARTITION BY RANGE (fetched_at);

-- Создание партиций (автоматизировать через pg_cron)
DO $$
DECLARE
    start_date DATE := CURRENT_DATE;
    end_date DATE;
    partition_name TEXT;
BEGIN
    FOR i IN 0..6 LOOP
        end_date := start_date + INTERVAL '1 day';
        partition_name := 'iss_fetch_log_' || TO_CHAR(start_date, 'YYYYMMDD');
        
        EXECUTE format('
            CREATE TABLE IF NOT EXISTS %I PARTITION OF iss_fetch_log
            FOR VALUES FROM (%L) TO (%L)
        ', partition_name, start_date, end_date);
        
        start_date := end_date;
    END LOOP;
END $$;

-- Индексы для ISS
CREATE INDEX IF NOT EXISTS idx_iss_fetched_at ON iss_fetch_log(fetched_at DESC);
CREATE INDEX IF NOT EXISTS idx_iss_payload_gin ON iss_fetch_log USING GIN(payload);

-- OSDR items с UPSERT-friendly структурой
CREATE TABLE IF NOT EXISTS osdr_items (
    id BIGSERIAL PRIMARY KEY,
    dataset_id TEXT UNIQUE,
    title TEXT,
    organism TEXT,
    study_type TEXT,
    status TEXT,
    updated_at TIMESTAMPTZ,
    inserted_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    raw JSONB NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_osdr_updated ON osdr_items(updated_at DESC);
CREATE INDEX IF NOT EXISTS idx_osdr_inserted ON osdr_items(inserted_at DESC);
CREATE INDEX IF NOT EXISTS idx_osdr_raw_gin ON osdr_items USING GIN(raw);
CREATE INDEX IF NOT EXISTS idx_osdr_organism ON osdr_items(organism);
CREATE INDEX IF NOT EXISTS idx_osdr_study_type ON osdr_items(study_type);

-- Space cache для APOD, NEO, DONKI, SpaceX
CREATE TABLE IF NOT EXISTS space_cache (
    id BIGSERIAL PRIMARY KEY,
    source TEXT NOT NULL,
    fetched_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    payload JSONB NOT NULL
);

-- Composite индекс для оптимизации запросов WHERE source=X ORDER BY fetched_at
CREATE INDEX IF NOT EXISTS idx_space_cache_source_time ON space_cache(source, fetched_at DESC);
CREATE INDEX IF NOT EXISTS idx_space_cache_payload_gin ON space_cache USING GIN(payload);

-- Telemetry legacy (из Pascal)
CREATE TABLE IF NOT EXISTS telemetry_legacy (
    id BIGSERIAL PRIMARY KEY,
    recorded_at TIMESTAMPTZ NOT NULL,
    voltage NUMERIC(6,2) NOT NULL CHECK (voltage BETWEEN 0 AND 20),
    temp NUMERIC(6,2) NOT NULL CHECK (temp BETWEEN -100 AND 150),
    source_file TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_telemetry_recorded ON telemetry_legacy(recorded_at DESC);

-- CMS pages (ИСПРАВЛЕНО: было cms_blocks, стало cms_pages)
CREATE TABLE IF NOT EXISTS cms_pages (
    id BIGSERIAL PRIMARY KEY,
    slug TEXT UNIQUE NOT NULL CHECK (slug ~ '^[a-z0-9\-]+$'),
    title TEXT NOT NULL,
    body TEXT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Seed БЕЗ XSS уязвимостей
INSERT INTO cms_pages(slug, title, body, is_active)
VALUES
('welcome', 'Добро пожаловать в Кассиопею', 
 '<h3>Система мониторинга космических данных</h3><p>Этот контент безопасен и хранится в БД</p>', 
 TRUE),
('about', 'О проекте', 
 '<p>Проект "Кассиопея" собирает данные из открытых API NASA, SpaceX, AstronomyAPI</p>', 
 TRUE)
ON CONFLICT (slug) DO UPDATE SET updated_at = NOW();

-- Материализованное представление для Dashboard метрик
CREATE MATERIALIZED VIEW IF NOT EXISTS dashboard_metrics AS
SELECT
    'iss' AS metric_type,
    COUNT(*) AS total_records,
    MAX(fetched_at) AS last_update,
    jsonb_build_object(
        'avg_velocity', ROUND(AVG((payload->>'velocity')::FLOAT)::NUMERIC, 2),
        'avg_altitude', ROUND(AVG((payload->>'altitude')::FLOAT)::NUMERIC, 2)
    ) AS aggregates
FROM iss_fetch_log
WHERE fetched_at > NOW() - INTERVAL '24 hours'
UNION ALL
SELECT
    'osdr' AS metric_type,
    COUNT(*) AS total_records,
    MAX(inserted_at) AS last_update,
    NULL AS aggregates
FROM osdr_items
UNION ALL
SELECT
    'telemetry' AS metric_type,
    COUNT(*) AS total_records,
    MAX(recorded_at) AS last_update,
    jsonb_build_object(
        'avg_voltage', ROUND(AVG(voltage)::NUMERIC, 2),
        'avg_temp', ROUND(AVG(temp)::NUMERIC, 2)
    ) AS aggregates
FROM telemetry_legacy
WHERE recorded_at > NOW() - INTERVAL '24 hours';

CREATE UNIQUE INDEX IF NOT EXISTS idx_dashboard_metrics ON dashboard_metrics(metric_type);

-- Функция для автоматической очистки старых данных
CREATE OR REPLACE FUNCTION cleanup_old_data() RETURNS void AS $$
BEGIN
    -- Удаление данных старше retention period
    DELETE FROM iss_fetch_log WHERE fetched_at < NOW() - INTERVAL '90 days';
    DELETE FROM space_cache WHERE fetched_at < NOW() - INTERVAL '30 days';
    DELETE FROM telemetry_legacy WHERE recorded_at < NOW() - INTERVAL '180 days';
    
    -- Обновление материализованного представления
    REFRESH MATERIALIZED VIEW CONCURRENTLY dashboard_metrics;
    
    RAISE NOTICE 'Old data cleanup completed';
END;
$$ LANGUAGE plpgsql;

-- Триггер для автоматического обновления updated_at
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER update_cms_pages_updated_at
    BEFORE UPDATE ON cms_pages
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

-- Таблица CMS блоков для динамических вставок на страницах
CREATE TABLE IF NOT EXISTS cms_blocks (
    id SERIAL PRIMARY KEY,
    slug VARCHAR(255) UNIQUE NOT NULL,
    title VARCHAR(500),
    content TEXT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_cms_blocks_slug ON cms_blocks(slug) WHERE is_active = TRUE;

-- Вставка демо-данных для CMS блоков
INSERT INTO cms_blocks (slug, title, content, is_active) VALUES
('dashboard_experiment', 'Экспериментальный блок Dashboard', 
'<div class="alert alert-info">
    <h5><i class="bi bi-flask"></i> Экспериментальная функция</h5>
    <p>Эта платформа объединяет данные о МКС, астрономические события и научные исследования NASA OSDR.</p>
    <ul>
        <li>Отслеживание МКС в реальном времени</li>
        <li>Астрономические события через AstronomyAPI</li>
        <li>База данных научных исследований NASA</li>
        <li>Кэширование космических данных (APOD, NEO, DONKI)</li>
    </ul>
</div>', TRUE),
('dashboard_info', 'Информация о платформе', 
'<div class="alert alert-primary">
    <h5><i class="bi bi-info-circle"></i> О платформе Cassiopeia</h5>
    <p>Cassiopeia — это распределённая платформа для агрегации космических данных.</p>
    <p>Архитектура: Rust (backend API) + PHP/Laravel (frontend) + PostgreSQL + Redis</p>
</div>', TRUE)
ON CONFLICT (slug) DO NOTHING;

-- Комментарии для документации
COMMENT ON TABLE iss_fetch_log IS 'Логи запросов к ISS API с партицированием по дням';
COMMENT ON TABLE osdr_items IS 'Данные из NASA OSDR (Open Science Data Repository)';
COMMENT ON TABLE space_cache IS 'Универсальный кэш для космических данных (APOD, NEO, DONKI, SpaceX)';
COMMENT ON TABLE telemetry_legacy IS 'Телеметрия от legacy Pascal сервиса';
COMMENT ON TABLE cms_pages IS 'Статические страницы CMS';

-- Вывод статистики
DO $$
BEGIN
    RAISE NOTICE '=== Database initialization completed ===';
    RAISE NOTICE 'Tables: iss_fetch_log (partitioned), osdr_items, space_cache, telemetry_legacy, cms_pages, cms_blocks';
    RAISE NOTICE 'Indexes: Created for all tables';
    RAISE NOTICE 'Materialized View: dashboard_metrics';
    RAISE NOTICE 'Functions: cleanup_old_data(), update_updated_at_column()';
END $$;

