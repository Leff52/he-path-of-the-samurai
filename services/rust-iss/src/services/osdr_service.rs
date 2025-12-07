use std::sync::Arc;
use serde_json::Value;

use crate::domain::{OsdrItem, extract_string, extract_timestamp};
use crate::errors::ApiError;
use crate::repo::OsdrRepository;

pub struct OsdrService<R: OsdrRepository> {
    repo: Arc<R>,
    osdr_url: String,
    http_client: reqwest::Client,
}

impl<R: OsdrRepository> OsdrService<R> {
    pub fn new(repo: Arc<R>, osdr_url: String) -> Self {
        let http_client = reqwest::Client::builder()
            .timeout(std::time::Duration::from_secs(30))
            .user_agent("KosmoStars-Space/1.0")
            .gzip(true)
            .build()
            .expect("Failed to build HTTP client");

        Self {
            repo,
            osdr_url,
            http_client,
        }
    }

    pub async fn sync_datasets(&self) -> Result<usize, ApiError> {
        let response = self.http_client
            .get(&self.osdr_url)
            .send()
            .await?;

        if !response.status().is_success() {
            return Err(ApiError::upstream(
                response.status().as_u16(),
                format!("OSDR API returned {}", response.status()),
            ));
        }

        let json: Value = response.json().await?;
        let items = self.extract_items(&json);

        let mut written = 0;
        for item in items {
            let dataset_id = extract_string(&item, &["dataset_id", "id", "uuid", "studyId", "accession", "osdr_id"]);
            let title = extract_string(&item, &["title", "name", "label"]);
            let organism = extract_string(&item, &["organism", "species", "model_organism"]);
            let study_type = extract_string(&item, &["study_type", "type", "experiment_type"]);
            let status = extract_string(&item, &["status", "state", "lifecycle"]);
            let updated_at = extract_timestamp(&item, &["updated", "updated_at", "modified", "lastUpdated", "timestamp"]);

            self.repo.upsert(dataset_id, title, organism, study_type, status, updated_at, item).await?;
            written += 1;
        }

        Ok(written)
    }

    pub async fn list(&self, limit: i32, offset: i32, search: Option<String>) -> Result<Vec<OsdrItem>, ApiError> {
        self.repo.list(limit, offset, search).await
    }

    pub async fn count(&self) -> Result<i64, ApiError> {
        self.repo.count().await
    }

    fn extract_items(&self, json: &Value) -> Vec<Value> {
        if let Some(arr) = json.as_array() {
            return arr.clone();
        }
        if let Some(items) = json.get("items").and_then(|x| x.as_array()) {
            return items.clone();
        }
        if let Some(results) = json.get("results").and_then(|x| x.as_array()) {
            return results.clone();
        }
        vec![json.clone()]
    }
}
