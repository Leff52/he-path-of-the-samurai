use async_trait::async_trait;
use serde_json::Value;
use sqlx::{PgPool, Row};

use crate::domain::SpaceCache;
use crate::errors::ApiError;

#[async_trait]
pub trait CacheRepository: Send + Sync {
    async fn insert(&self, source: &str, payload: Value) -> Result<i64, ApiError>;
    async fn get_latest(&self, source: &str) -> Result<Option<SpaceCache>, ApiError>;
    async fn cleanup_old(&self, source: &str, keep_days: i32) -> Result<u64, ApiError>;
}

pub struct PgCacheRepo {
    pool: PgPool,
}

impl PgCacheRepo {
    pub fn new(pool: PgPool) -> Self {
        Self { pool }
    }
}

#[async_trait]
impl CacheRepository for PgCacheRepo {
    async fn insert(&self, source: &str, payload: Value) -> Result<i64, ApiError> {
        let row = sqlx::query(
            r#"
            INSERT INTO space_cache (source, payload)
            VALUES ($1, $2)
            RETURNING id
            "#
        )
        .bind(source)
        .bind(&payload)
        .fetch_one(&self.pool)
        .await?;

        Ok(row.get("id"))
    }

    async fn get_latest(&self, source: &str) -> Result<Option<SpaceCache>, ApiError> {
        let row = sqlx::query(
            r#"
            SELECT id, source, fetched_at, payload
            FROM space_cache
            WHERE source = $1
            ORDER BY fetched_at DESC
            LIMIT 1
            "#
        )
        .bind(source)
        .fetch_optional(&self.pool)
        .await?;

        Ok(row.map(|r| SpaceCache {
            id: r.get("id"),
            source: r.get("source"),
            fetched_at: r.get("fetched_at"),
            payload: r.get("payload"),
        }))
    }

    async fn cleanup_old(&self, source: &str, keep_days: i32) -> Result<u64, ApiError> {
        let result = sqlx::query(
            r#"
            DELETE FROM space_cache
            WHERE source = $1 AND fetched_at < NOW() - INTERVAL '1 day' * $2
            "#
        )
        .bind(source)
        .bind(keep_days)
        .execute(&self.pool)
        .await?;

        Ok(result.rows_affected())
    }
}
