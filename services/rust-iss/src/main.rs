//! KosmoStars Space Data Platform - Rust Backend
//! Модульная архитектура:
//! - config/     - конфигурация приложения
//! - domain/     - доменные модели
//! - errors/     - унифицированная обработка ошибок
//! - repo/       - репозитории (доступ к БД)
//! - services/   - бизнес-логика
//! - handlers/   - HTTP-обработчики
//! - routes/     - маршрутизация
//! - clients/    - внешние HTTP-клиенты

mod config;
mod domain;
mod errors;
mod handlers;
mod repo;
mod routes;
mod services;
mod clients;

use std::sync::Arc;
use std::time::Duration;

use sqlx::postgres::PgPoolOptions;
use tracing::{error, info};
use tracing_subscriber::{EnvFilter, FmtSubscriber};

use crate::config::AppConfig;
use crate::repo::{PgIssRepo, PgOsdrRepo, PgCacheRepo};
use crate::services::{IssService, OsdrService, SpaceService};
use crate::routes::create_router;

#[tokio::main]
async fn main() -> anyhow::Result<()> {
    // Инициализация логгера
    let subscriber = FmtSubscriber::builder()
        .with_env_filter(EnvFilter::from_default_env())
        .finish();
    let _ = tracing::subscriber::set_global_default(subscriber);

    // Загрузка .env
    dotenvy::dotenv().ok();

    // Загрузка конфигурации
    let config = AppConfig::from_env()?;
    info!("Configuration loaded");

    // Подключение к БД
    let pool = PgPoolOptions::new()
        .max_connections(config.db_pool_size)
        .acquire_timeout(Duration::from_secs(10))
        .connect(&config.database_url)
        .await?;
    info!("Database connected");

    // Инициализация репозиториев
    let iss_repo = Arc::new(PgIssRepo::new(pool.clone()));
    let osdr_repo = Arc::new(PgOsdrRepo::new(pool.clone()));
    let cache_repo = Arc::new(PgCacheRepo::new(pool.clone()));

    // Инициализация сервисов
    let iss_service = Arc::new(IssService::new(
        iss_repo.clone(),
        config.where_iss_url.clone(),
    ));
    let osdr_service = Arc::new(OsdrService::new(
        osdr_repo.clone(),
        config.nasa_api_url.clone(),
    ));
    let space_service = Arc::new(SpaceService::new(
        cache_repo.clone(),
        config.nasa_api_key.clone(),
    ));

    // Фоновые задачи
    spawn_background_tasks(
        iss_service.clone(),
        osdr_service.clone(),
        space_service.clone(),
        &config,
    );

    // Создание роутера
    let app = create_router(
        iss_service,
        osdr_service,
        space_service,
    );

    // Запуск сервера
    let addr = format!("0.0.0.0:{}", config.port);
    info!("Starting server on {}", addr);
    
    let listener = tokio::net::TcpListener::bind(&addr).await?;
    axum::serve(listener, app).await?;

    Ok(())
}

fn spawn_background_tasks<I, O, C>(
    iss_service: Arc<IssService<I>>,
    osdr_service: Arc<OsdrService<O>>,
    space_service: Arc<SpaceService<C>>,
    config: &AppConfig,
) where
    I: crate::repo::IssRepository + 'static,
    O: crate::repo::OsdrRepository + 'static,
    C: crate::repo::CacheRepository + 'static,
{
    // ISS fetch task
    {
        let svc = iss_service.clone();
        let interval = config.iss_every_seconds;
        tokio::spawn(async move {
            loop {
                if let Err(e) = svc.fetch_and_store().await {
                    error!("ISS fetch error: {:?}", e);
                }
                tokio::time::sleep(Duration::from_secs(interval)).await;
            }
        });
    }

    // OSDR sync task
    {
        let svc = osdr_service.clone();
        let interval = config.osdr_every_seconds;
        tokio::spawn(async move {
            loop {
                if let Err(e) = svc.sync_datasets().await {
                    error!("OSDR sync error: {:?}", e);
                }
                tokio::time::sleep(Duration::from_secs(interval)).await;
            }
        });
    }

    // APOD task
    {
        let svc = space_service.clone();
        let interval = config.apod_every_seconds;
        tokio::spawn(async move {
            loop {
                if let Err(e) = svc.fetch_apod().await {
                    error!("APOD fetch error: {:?}", e);
                }
                tokio::time::sleep(Duration::from_secs(interval)).await;
            }
        });
    }

    // NEO task
    {
        let svc = space_service.clone();
        let interval = config.neo_every_seconds;
        tokio::spawn(async move {
            loop {
                if let Err(e) = svc.fetch_neo().await {
                    error!("NEO fetch error: {:?}", e);
                }
                tokio::time::sleep(Duration::from_secs(interval)).await;
            }
        });
    }

    // DONKI FLR task
    {
        let svc = space_service.clone();
        let interval = config.donki_every_seconds;
        tokio::spawn(async move {
            loop {
                if let Err(e) = svc.fetch_donki_flr().await {
                    error!("DONKI FLR fetch error: {:?}", e);
                }
                tokio::time::sleep(Duration::from_secs(interval)).await;
            }
        });
    }

    // DONKI CME task
    {
        let svc = space_service.clone();
        let interval = config.donki_every_seconds;
        tokio::spawn(async move {
            loop {
                if let Err(e) = svc.fetch_donki_cme().await {
                    error!("DONKI CME fetch error: {:?}", e);
                }
                tokio::time::sleep(Duration::from_secs(interval)).await;
            }
        });
    }

    // SpaceX task
    {
        let svc = space_service.clone();
        let interval = config.spacex_every_seconds;
        tokio::spawn(async move {
            loop {
                if let Err(e) = svc.fetch_spacex().await {
                    error!("SpaceX fetch error: {:?}", e);
                }
                tokio::time::sleep(Duration::from_secs(interval)).await;
            }
        });
    }

    info!("Background tasks spawned");
}
