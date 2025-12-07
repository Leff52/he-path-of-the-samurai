use reqwest::{Client, Response};
use serde_json::Value;
use std::sync::Arc;
use std::time::Duration;
use tokio::sync::Semaphore;
use tracing::{debug, warn};

use crate::errors::ApiError;

pub struct NasaClient {
    client: Client,
    api_key: String,
    base_url_osdr: String,
    rate_limiter: Arc<Semaphore>,
}

impl NasaClient {
    pub fn new(api_key: String, base_url_osdr: String, rate_limit_rpm: u32) -> Self {
        let client = Client::builder()
            .timeout(Duration::from_secs(30))
            .user_agent("KosmoStars-Space/1.0")
            .gzip(true)
            .brotli(true)
            .build()
            .expect("Failed to build HTTP client");

        // Semaphore для rate limiting (requests per minute)
        let permits = rate_limit_rpm as usize;
        let rate_limiter = Arc::new(Semaphore::new(permits));

        // Фоновая задача для восстановления permits каждую минуту
        let limiter_clone = rate_limiter.clone();
        tokio::spawn(async move {
            let mut interval = tokio::time::interval(Duration::from_secs(60));
            loop {
                interval.tick().await;
                // Сброс лимита каждую минуту
                while limiter_clone.available_permits() < permits {
                    limiter_clone.add_permits(1);
                }
            }
        });

        Self {
            client,
            api_key,
            base_url_osdr,
            rate_limiter,
        }
    }

    async fn acquire_permit(&self) {
        let _permit = self.rate_limiter.acquire().await.unwrap();
        debug!("Rate limiter permit acquired");
    }

    async fn retry_request<F, Fut>(&self, mut request_fn: F) -> Result<Response, ApiError>
    where
        F: FnMut() -> Fut,
        Fut: std::future::Future<Output = Result<Response, reqwest::Error>>,
    {
        let max_attempts = 3;
        let mut attempt = 0;

        loop {
            self.acquire_permit().await;

            match request_fn().await {
                Ok(resp) => {
                    let status = resp.status();
                    
                    if status.is_success() {
                        return Ok(resp);
                    }

                    // Rate limited - retry с exponential backoff
                    if status == 429 && attempt < max_attempts {
                        let delay = Duration::from_secs(2_u64.pow(attempt));
                        warn!("Rate limited (429), retrying in {:?}", delay);
                        tokio::time::sleep(delay).await;
                        attempt += 1;
                        continue;
                    }

                    // 5xx - retry
                    if status.is_server_error() && attempt < max_attempts {
                        warn!("Server error {}, retrying", status);
                        tokio::time::sleep(Duration::from_secs(2)).await;
                        attempt += 1;
                        continue;
                    }

                    // Client error или достигли max attempts
                    return Err(ApiError::upstream(
                        status.as_u16(),
                        format!("NASA API returned {}", status),
                    ));
                }
                Err(e) => {
                    if attempt < max_attempts {
                        warn!("Request failed: {}, retrying", e);
                        tokio::time::sleep(Duration::from_secs(2)).await;
                        attempt += 1;
                        continue;
                    }
                    return Err(e.into());
                }
            }
        }
    }

    pub async fn fetch_osdr_datasets(&self) -> Result<Value, ApiError> {
        let url = self.base_url_osdr.clone();
        let api_key = self.api_key.clone();

        let resp = self
            .retry_request(|| {
                let url = url.clone();
                let api_key = api_key.clone();
                async move {
                    self.client
                        .get(&url)
                        .query(&[("api_key", api_key)])
                        .send()
                        .await
                }
            })
            .await?;

        let json = resp.json().await?;
        Ok(json)
    }

    pub async fn fetch_apod(&self) -> Result<Value, ApiError> {
        let url = "https://api.nasa.gov/planetary/apod";
        let api_key = self.api_key.clone();

        let resp = self
            .retry_request(|| {
                let api_key = api_key.clone();
                async move {
                    self.client
                        .get(url)
                        .query(&[("api_key", &api_key), ("thumbs", &"true".to_string())])
                        .send()
                        .await
                }
            })
            .await?;

        let json = resp.json().await?;
        Ok(json)
    }

    pub async fn fetch_neo_feed(&self, start_date: &str, end_date: &str) -> Result<Value, ApiError> {
        let url = "https://api.nasa.gov/neo/rest/v1/feed";
        let api_key = self.api_key.clone();
        let start = start_date.to_string();
        let end = end_date.to_string();

        let resp = self
            .retry_request(|| {
                let api_key = api_key.clone();
                let start = start.clone();
                let end = end.clone();
                async move {
                    self.client
                        .get(url)
                        .query(&[
                            ("api_key", api_key),
                            ("start_date", start),
                            ("end_date", end),
                        ])
                        .send()
                        .await
                }
            })
            .await?;

        let json = resp.json().await?;
        Ok(json)
    }

    pub async fn fetch_donki_flr(&self, start_date: &str, end_date: &str) -> Result<Value, ApiError> {
        let url = "https://api.nasa.gov/DONKI/FLR";
        let api_key = self.api_key.clone();
        let start = start_date.to_string();
        let end = end_date.to_string();

        let resp = self
            .retry_request(|| {
                let api_key = api_key.clone();
                let start = start.clone();
                let end = end.clone();
                async move {
                    self.client
                        .get(url)
                        .query(&[
                            ("api_key", api_key),
                            ("startDate", start),
                            ("endDate", end),
                        ])
                        .send()
                        .await
                }
            })
            .await?;

        let json = resp.json().await?;
        Ok(json)
    }

    pub async fn fetch_donki_cme(&self, start_date: &str, end_date: &str) -> Result<Value, ApiError> {
        let url = "https://api.nasa.gov/DONKI/CME";
        let api_key = self.api_key.clone();
        let start = start_date.to_string();
        let end = end_date.to_string();

        let resp = self
            .retry_request(|| {
                let api_key = api_key.clone();
                let start = start.clone();
                let end = end.clone();
                async move {
                    self.client
                        .get(url)
                        .query(&[
                            ("api_key", api_key),
                            ("startDate", start),
                            ("endDate", end),
                        ])
                        .send()
                        .await
                }
            })
            .await?;

        let json = resp.json().await?;
        Ok(json)
    }
}
