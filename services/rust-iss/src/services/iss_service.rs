use std::sync::Arc;
use chrono::Utc;
use serde_json::Value;
use tokio::sync::Mutex;

use crate::domain::IssFetchLog;
use crate::domain::IssTrend;
use crate::errors::ApiError;
use crate::repo::IssRepository;

pub struct IssService<R: IssRepository> {
    iss_repo: Arc<R>,
    iss_url: String,
    http_client: reqwest::Client,
    fetch_mutex: Arc<Mutex<()>>,
}

impl<R: IssRepository> IssService<R> {
    pub fn new(iss_repo: Arc<R>, iss_url: String) -> Self {
        let http_client = reqwest::Client::builder()
            .timeout(std::time::Duration::from_secs(20))
            .user_agent("KosmoStars-Space/1.0")
            .build()
            .expect("Failed to build HTTP client");

        Self {
            iss_repo,
            iss_url,
            http_client,
            fetch_mutex: Arc::new(Mutex::new(())),
        }
    }

    pub async fn get_latest(&self) -> Result<Option<IssFetchLog>, ApiError> {
        self.iss_repo.get_last().await
    }

    pub async fn get_trend(&self, hours: i64) -> Result<Vec<IssTrend>, ApiError> {
        self.iss_repo.get_trend(hours).await
    }

    pub async fn fetch_and_store(&self) -> Result<IssFetchLog, ApiError> {
        // Mutex для защиты от параллельных запросов
        let _guard = self.fetch_mutex.lock().await;

        let response = self.http_client
            .get(&self.iss_url)
            .send()
            .await?;

        if !response.status().is_success() {
            return Err(ApiError::upstream(
                response.status().as_u16(),
                format!("ISS API returned {}", response.status()),
            ));
        }

        let payload: Value = response.json().await?;
        let id = self.iss_repo.insert(&self.iss_url, payload.clone()).await?;

        Ok(IssFetchLog {
            id,
            fetched_at: Utc::now(),
            source_url: self.iss_url.clone(),
            payload,
        })
    }
}
