use async_trait::async_trait;
use chrono::{DateTime, Utc};
use serde_json::Value;
use sqlx::{PgPool, Row};

use crate::domain::OsdrItem;
use crate::errors::ApiError;

#[async_trait]
pub trait OsdrRepository: Send + Sync {
    async fn upsert(
        &self,
        dataset_id: Option<String>,
        title: Option<String>,
        organism: Option<String>,
        study_type: Option<String>,
        status: Option<String>,
        updated_at: Option<DateTime<Utc>>,
        raw: Value,
    ) -> Result<i64, ApiError>;
    
    async fn list(&self, limit: i32, offset: i32, search: Option<String>) -> Result<Vec<OsdrItem>, ApiError>;
    async fn count(&self) -> Result<i64, ApiError>;
}

pub struct PgOsdrRepo {
    pool: PgPool,
}

impl PgOsdrRepo {
    pub fn new(pool: PgPool) -> Self {
        Self { pool }
    }
}

#[async_trait]
impl OsdrRepository for PgOsdrRepo {
    async fn upsert(
        &self,
        dataset_id: Option<String>,
        title: Option<String>,
        organism: Option<String>,
        study_type: Option<String>,
        status: Option<String>,
        updated_at: Option<DateTime<Utc>>,
        raw: Value,
    ) -> Result<i64, ApiError> {
        let row = sqlx::query(
            r#"
            INSERT INTO osdr_items (dataset_id, title, organism, study_type, status, updated_at, raw)
            VALUES ($1, $2, $3, $4, $5, $6, $7)
            ON CONFLICT (dataset_id) DO UPDATE
            SET title = EXCLUDED.title,
                organism = EXCLUDED.organism,
                study_type = EXCLUDED.study_type,
                status = EXCLUDED.status,
                updated_at = EXCLUDED.updated_at,
                raw = EXCLUDED.raw
            RETURNING id
            "#
        )
        .bind(&dataset_id)
        .bind(&title)
        .bind(&organism)
        .bind(&study_type)
        .bind(&status)
        .bind(&updated_at)
        .bind(&raw)
        .fetch_one(&self.pool)
        .await?;

        Ok(row.get("id"))
    }

    async fn list(&self, limit: i32, offset: i32, search: Option<String>) -> Result<Vec<OsdrItem>, ApiError> {
        let rows = if let Some(s) = search {
            let pattern = format!("%{}%", s);
            sqlx::query(
                r#"
                SELECT id, dataset_id, title, organism, study_type, status, updated_at, inserted_at, raw
                FROM osdr_items
                WHERE title ILIKE $1 OR organism ILIKE $1 OR dataset_id ILIKE $1
                ORDER BY updated_at DESC NULLS LAST
                LIMIT $2 OFFSET $3
                "#
            )
            .bind(&pattern)
            .bind(limit)
            .bind(offset)
            .fetch_all(&self.pool)
            .await?
        } else {
            sqlx::query(
                r#"
                SELECT id, dataset_id, title, organism, study_type, status, updated_at, inserted_at, raw
                FROM osdr_items
                ORDER BY updated_at DESC NULLS LAST
                LIMIT $1 OFFSET $2
                "#
            )
            .bind(limit)
            .bind(offset)
            .fetch_all(&self.pool)
            .await?
        };

        Ok(rows.into_iter().map(|r| OsdrItem {
            id: r.get("id"),
            dataset_id: r.get("dataset_id"),
            title: r.get("title"),
            organism: r.get("organism"),
            study_type: r.get("study_type"),
            status: r.get("status"),
            updated_at: r.get("updated_at"),
            inserted_at: r.get("inserted_at"),
            raw: r.get("raw"),
        }).collect())
    }

    async fn count(&self) -> Result<i64, ApiError> {
        let row = sqlx::query(r#"SELECT COUNT(*) as count FROM osdr_items"#)
            .fetch_one(&self.pool)
            .await?;

        Ok(row.get::<i64, _>("count"))
    }
}
