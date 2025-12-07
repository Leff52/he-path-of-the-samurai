use async_trait::async_trait;
use chrono::{DateTime, Utc};
use serde_json::Value;
use sqlx::{PgPool, Row};

use crate::domain::{IssFetchLog, IssTrend};
use crate::errors::ApiError;

#[async_trait]
pub trait IssRepository: Send + Sync {
    async fn insert(&self, source_url: &str, payload: Value) -> Result<i64, ApiError>;
    async fn get_last(&self) -> Result<Option<IssFetchLog>, ApiError>;
    async fn get_last_n(&self, n: i64) -> Result<Vec<IssFetchLog>, ApiError>;
    async fn get_trend(&self, hours: i64) -> Result<Vec<IssTrend>, ApiError>;
}

pub struct PgIssRepo {
    pool: PgPool,
}

impl PgIssRepo {
    pub fn new(pool: PgPool) -> Self {
        Self { pool }
    }
}

#[async_trait]
impl IssRepository for PgIssRepo {
    async fn insert(&self, source_url: &str, payload: Value) -> Result<i64, ApiError> {
        let row = sqlx::query(
            r#"
            INSERT INTO iss_fetch_log (source_url, payload)
            VALUES ($1, $2)
            RETURNING id
            "#
        )
        .bind(source_url)
        .bind(&payload)
        .fetch_one(&self.pool)
        .await?;

        Ok(row.get("id"))
    }

    async fn get_last(&self) -> Result<Option<IssFetchLog>, ApiError> {
        let row = sqlx::query(
            r#"
            SELECT id, fetched_at, source_url, payload
            FROM iss_fetch_log
            ORDER BY fetched_at DESC
            LIMIT 1
            "#
        )
        .fetch_optional(&self.pool)
        .await?;

        Ok(row.map(|r| IssFetchLog {
            id: r.get("id"),
            fetched_at: r.get("fetched_at"),
            source_url: r.get("source_url"),
            payload: r.get("payload"),
        }))
    }

    async fn get_last_n(&self, n: i64) -> Result<Vec<IssFetchLog>, ApiError> {
        let rows = sqlx::query(
            r#"
            SELECT id, fetched_at, source_url, payload
            FROM iss_fetch_log
            ORDER BY fetched_at DESC
            LIMIT $1
            "#
        )
        .bind(n)
        .fetch_all(&self.pool)
        .await?;

        Ok(rows.into_iter().map(|r| IssFetchLog {
            id: r.get("id"),
            fetched_at: r.get("fetched_at"),
            source_url: r.get("source_url"),
            payload: r.get("payload"),
        }).collect())
    }

    async fn get_trend(&self, hours: i64) -> Result<Vec<IssTrend>, ApiError> {
        let rows = sqlx::query(
            r#"
            SELECT 
                date_trunc('hour', fetched_at) as hour,
                AVG((payload->>'latitude')::float) as avg_lat,
                AVG((payload->>'longitude')::float) as avg_lon,
                AVG((payload->>'altitude')::float) as avg_altitude,
                AVG((payload->>'velocity')::float) as avg_velocity,
                COUNT(*) as cnt
            FROM iss_fetch_log
            WHERE fetched_at >= NOW() - ($1 || ' hours')::interval
            GROUP BY date_trunc('hour', fetched_at)
            ORDER BY hour DESC
            "#
        )
        .bind(hours)
        .fetch_all(&self.pool)
        .await?;

        Ok(rows.into_iter().map(|r| {
            let hour: DateTime<Utc> = r.get("hour");
            IssTrend {
                hour: hour.format("%Y-%m-%d %H:00").to_string(),
                avg_lat: r.get::<Option<f64>, _>("avg_lat").unwrap_or(0.0),
                avg_lon: r.get::<Option<f64>, _>("avg_lon").unwrap_or(0.0),
                avg_altitude: r.get::<Option<f64>, _>("avg_altitude").unwrap_or(0.0),
                avg_velocity: r.get::<Option<f64>, _>("avg_velocity").unwrap_or(0.0),
                cnt: r.get("cnt"),
            }
        }).collect())
    }
}
