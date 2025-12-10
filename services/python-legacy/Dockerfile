FROM python:3.12-slim

RUN apt-get update && apt-get install -y --no-install-recommends \
    postgresql-client \
    ca-certificates \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /opt/legacy

# Копирование и установка Python зависимостей
COPY requirements.txt ./
RUN pip install --no-cache-dir -r requirements.txt

# Копирование скрипта
COPY osdr_exporter.py ./

# Создание директории для CSV
RUN mkdir -p /data/csv

# Запуск
CMD ["python", "-u", "osdr_exporter.py"]
