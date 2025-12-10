import os
import csv
import json
import time
import logging
import sys
from datetime import datetime, timezone
from typing import Any, Optional

import requests
import psycopg2
from psycopg2.extras import RealDictCursor

# Настройка логирования
logging.basicConfig(
    level=logging.INFO,
    format='[python-legacy] %(asctime)s - %(levelname)s - %(message)s',
    stream=sys.stdout
)
logger = logging.getLogger(__name__)


def get_env(name: str, default: str = '') -> str:
    return os.environ.get(name, default)


def get_db_connection():
    return psycopg2.connect(
        host=get_env('PGHOST', 'db'),
        port=get_env('PGPORT', '5432'),
        user=get_env('PGUSER', 'postgres'),
        password=get_env('PGPASSWORD', 'postgres'),
        database=get_env('PGDATABASE', 'iss_osdr')
    )


def fetch_osdr_data() -> list[dict]:
    """Загрузить данные из NASA OSDR API"""
    api_url = get_env('NASA_API_URL', 'https://visualization.osdr.nasa.gov/biodata/api/v2/datasets/?format=json')
    api_key = get_env('NASA_API_KEY', '')
    
    headers = {
        'User-Agent': 'OSDR-Python-Exporter/1.0',
        'Accept': 'application/json'
    }
    
    if api_key:
        headers['Authorization'] = f'Bearer {api_key}'
    
    try:
        logger.info(f"Запрос к OSDR API: {api_url}")
        response = requests.get(api_url, headers=headers, timeout=60)
        response.raise_for_status()
        
        data = response.json()
        
        if isinstance(data, list):
            items = data
        elif isinstance(data, dict):
            items = data.get('items') or data.get('results') or data.get('data') or [data]
        else:
            items = []
        
        logger.info(f"Получено {len(items)} записей из OSDR API")
        return items
        
    except requests.exceptions.RequestException as e:
        logger.error(f"Ошибка запроса к OSDR API: {e}")
        return []
    except json.JSONDecodeError as e:
        logger.error(f"Ошибка разбора JSON: {e}")
        return []


def extract_field(item: dict, keys: list[str], default: Any = None) -> Any:
    for key in keys:
        if key in item and item[key] is not None:
            return item[key]
    return default


def to_bool_russian(value: Any) -> str:
    if value is None:
        return 'ЛОЖЬ'
    if isinstance(value, bool):
        return 'ИСТИНА' if value else 'ЛОЖЬ'
    if isinstance(value, str):
        return 'ИСТИНА' if value.lower() in ('true', '1', 'yes', 'да') else 'ЛОЖЬ'
    if isinstance(value, (int, float)):
        return 'ИСТИНА' if value != 0 else 'ЛОЖЬ'
    return 'ИСТИНА' if value else 'ЛОЖЬ'


def to_timestamp(value: Any) -> Optional[str]:
    if value is None:
        return None
    
    if isinstance(value, datetime):
        return value.isoformat()
    
    if isinstance(value, str):
        # Попробовать распарсить различные форматы
        formats = [
            '%Y-%m-%dT%H:%M:%S.%fZ',
            '%Y-%m-%dT%H:%M:%SZ',
            '%Y-%m-%dT%H:%M:%S',
            '%Y-%m-%d %H:%M:%S',
            '%Y-%m-%d',
        ]
        for fmt in formats:
            try:
                dt = datetime.strptime(value, fmt)
                return dt.replace(tzinfo=timezone.utc).isoformat()
            except ValueError:
                continue
        return value 
    
    return str(value)


def to_numeric(value: Any) -> Optional[float]:
    """Преобразовать значение в числовой формат"""
    if value is None:
        return None
    try:
        return float(value)
    except (ValueError, TypeError):
        return None


def sanitize_string(value: Any) -> str:
    """Очистить и преобразовать в текст"""
    if value is None:
        return ''
    s = str(value)
    s = ' '.join(s.split())
    return s


def transform_item(item: dict, index: int) -> dict:
    """Преобразовать элемент OSDR в формат CSV"""
    now = datetime.now(timezone.utc)
    
    dataset_id = extract_field(item, ['dataset_id', 'id', 'uuid', 'studyId', 'accession', 'osdr_id'], f'unknown_{index}')
    title = extract_field(item, ['title', 'name', 'label'], '')
    organism = extract_field(item, ['organism', 'species', 'model_organism'], '')
    study_type = extract_field(item, ['study_type', 'type', 'experiment_type'], '')
    status = extract_field(item, ['status', 'state', 'lifecycle'], '')
    updated = extract_field(item, ['updated', 'updated_at', 'modified', 'lastUpdated', 'timestamp'], '')
    
    is_public = extract_field(item, ['is_public', 'public', 'isPublic'], True)
    sample_count = extract_field(item, ['samples', 'sample_count', 'num_samples'], 0)
    assay_count = extract_field(item, ['assays', 'assay_count', 'num_assays'], 0)
    
    return {
        'export_timestamp': now.isoformat(),
        'updated_at': to_timestamp(updated),
        
        'is_public': to_bool_russian(is_public),
        'has_samples': to_bool_russian(sample_count and int(sample_count) > 0),
        'has_assays': to_bool_russian(assay_count and int(assay_count) > 0),
        
        'row_number': index + 1,
        'sample_count': to_numeric(sample_count) or 0,
        'assay_count': to_numeric(assay_count) or 0,
        
        'dataset_id': sanitize_string(dataset_id),
        'title': sanitize_string(title),
        'organism': sanitize_string(organism),
        'study_type': sanitize_string(study_type),
        'status': sanitize_string(status),
        
        'raw_json': json.dumps(item, ensure_ascii=False, default=str)
    }


def generate_csv(items: list[dict], output_dir: str) -> str:
    """Сгенерировать CSV файл с данными OSDR"""
    now = datetime.now()
    filename = f'osdr_export_{now.strftime("%Y%m%d_%H%M%S")}.csv'
    filepath = os.path.join(output_dir, filename)
    
    os.makedirs(output_dir, exist_ok=True)
    
    fieldnames = [
        'export_timestamp', 'updated_at',
        # (русский)
        'is_public', 'has_samples', 'has_assays',
        'row_number', 'sample_count', 'assay_count',
        'dataset_id', 'title', 'organism', 'study_type', 'status',
        'raw_json'
    ]
    
    transformed_items = [transform_item(item, i) for i, item in enumerate(items)]
    
    with open(filepath, 'w', newline='', encoding='utf-8') as f:
        writer = csv.DictWriter(f, fieldnames=fieldnames, quoting=csv.QUOTE_ALL)
        writer.writeheader()
        writer.writerows(transformed_items)
    
    logger.info(f"CSV файл создан: {filepath} ({len(transformed_items)} записей)")
    return filepath


def copy_to_postgres(filepath: str, conn):
    create_table_sql = """
    CREATE TABLE IF NOT EXISTS osdr_exports (
        id BIGSERIAL PRIMARY KEY,
        export_timestamp TIMESTAMPTZ NOT NULL,
        updated_at TIMESTAMPTZ,
        is_public BOOLEAN NOT NULL DEFAULT TRUE,
        has_samples BOOLEAN NOT NULL DEFAULT FALSE,
        has_assays BOOLEAN NOT NULL DEFAULT FALSE,
        row_number INTEGER NOT NULL,
        sample_count INTEGER DEFAULT 0,
        assay_count INTEGER DEFAULT 0,
        dataset_id TEXT,
        title TEXT,
        organism TEXT,
        study_type TEXT,
        status TEXT,
        raw_json JSONB,
        source_file TEXT NOT NULL,
        imported_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
    );
    
    CREATE INDEX IF NOT EXISTS idx_osdr_exports_timestamp ON osdr_exports(export_timestamp DESC);
    CREATE INDEX IF NOT EXISTS idx_osdr_exports_dataset ON osdr_exports(dataset_id);
    CREATE INDEX IF NOT EXISTS idx_osdr_exports_organism ON osdr_exports(organism);
    """
    
    with conn.cursor() as cur:
        cur.execute(create_table_sql)
        conn.commit()
    source_file = os.path.basename(filepath)
    inserted = 0
    
    with open(filepath, 'r', encoding='utf-8') as f:
        reader = csv.DictReader(f)
        
        with conn.cursor() as cur:
            for row in reader:
                is_public = row['is_public'] == 'ИСТИНА'
                has_samples = row['has_samples'] == 'ИСТИНА'
                has_assays = row['has_assays'] == 'ИСТИНА'
                
                cur.execute("""
                    INSERT INTO osdr_exports (
                        export_timestamp, updated_at, is_public, has_samples, has_assays,
                        row_number, sample_count, assay_count,
                        dataset_id, title, organism, study_type, status,
                        raw_json, source_file
                    ) VALUES (
                        %s, %s, %s, %s, %s,
                        %s, %s, %s,
                        %s, %s, %s, %s, %s,
                        %s, %s
                    )
                """, (
                    row['export_timestamp'] or None,
                    row['updated_at'] or None,
                    is_public,
                    has_samples,
                    has_assays,
                    int(row['row_number']),
                    int(float(row['sample_count'])) if row['sample_count'] else 0,
                    int(float(row['assay_count'])) if row['assay_count'] else 0,
                    row['dataset_id'] or None,
                    row['title'] or None,
                    row['organism'] or None,
                    row['study_type'] or None,
                    row['status'] or None,
                    row['raw_json'] or None,
                    source_file
                ))
                inserted += 1
            
            conn.commit()
    
    logger.info(f"Импортировано {inserted} записей в PostgreSQL из {source_file}")
    return inserted


def sync_to_osdr_items(conn, items: list[dict]):
    upserted = 0
    
    with conn.cursor() as cur:
        for i, item in enumerate(items):
            transformed = transform_item(item, i)
            
            # Преобразуем updated_at в пустую строку в None
            updated_at = transformed['updated_at']
            if updated_at == '' or updated_at is None:
                updated_at = None
            
            cur.execute("""
                INSERT INTO osdr_items (dataset_id, title, organism, study_type, status, updated_at, raw)
                VALUES (%s, %s, %s, %s, %s, %s, %s)
                ON CONFLICT (dataset_id) DO UPDATE SET
                    title = EXCLUDED.title,
                    organism = EXCLUDED.organism,
                    study_type = EXCLUDED.study_type,
                    status = EXCLUDED.status,
                    updated_at = COALESCE(EXCLUDED.updated_at, osdr_items.updated_at),
                    raw = EXCLUDED.raw,
                    inserted_at = NOW()
            """, (
                transformed['dataset_id'] or f'unknown_{i}',
                transformed['title'] or None,
                transformed['organism'] or None,
                transformed['study_type'] or None,
                transformed['status'] or None,
                updated_at,
                transformed['raw_json']
            ))
            upserted += 1
        
        conn.commit()
    
    logger.info(f"Синхронизировано {upserted} записей в osdr_items")
    return upserted


def get_latest_csv_file(output_dir: str) -> Optional[str]:
    try:
        files = [f for f in os.listdir(output_dir) if f.startswith('osdr_export_') and f.endswith('.csv')]
        if not files:
            return None
        files.sort(reverse=True)
        return os.path.join(output_dir, files[0])
    except OSError:
        return None


def run_export_cycle():
    output_dir = get_env('CSV_OUT_DIR', '/data/csv')
    items = fetch_osdr_data()
    
    if not items:
        logger.warning("Нет данных для экспорта, пропуск цикла")
        return False
    csv_path = generate_csv(items, output_dir)
    try:
        conn = get_db_connection()
        try:
            # Импорт в osdr_exports
            copy_to_postgres(csv_path, conn)
            
            # Синхронизация с osdr_items
            sync_to_osdr_items(conn, items)
            
        finally:
            conn.close()
    except psycopg2.Error as e:
        logger.error(f"Ошибка PostgreSQL: {e}")
        return False
    
    return True


def main():
    logger.info("=== Python-Legacy OSDR Exporter запущен ===") 
    period = int(get_env('GEN_PERIOD_SEC', '300'))
    logger.info(f"Период генерации: {period} секунд")
    initial_delay = int(get_env('INITIAL_DELAY_SEC', '10'))
    logger.info(f"Начальная задержка: {initial_delay} секунд")
    time.sleep(initial_delay)
    
    while True:
        try:
            success = run_export_cycle()
            if success:
                logger.info(f"Цикл экспорта завершен успешно. Следующий через {period} сек.")
            else:
                logger.warning(f"Цикл экспорта пропущен. Повтор через {period} сек.")
        except Exception as e:
            logger.exception(f"Неожиданная ошибка в цикле экспорта: {e}")
        
        time.sleep(period)


if __name__ == '__main__':
    main()
