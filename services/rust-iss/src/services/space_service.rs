use std::sync::Arc;
use chrono::Utc;
use serde_json::Value;

use crate::domain::SpaceCache;
use crate::errors::ApiError;
use crate::repo::CacheRepository;

pub struct SpaceService<C: CacheRepository> {
    cache_repo: Arc<C>,
    nasa_key: String,
    http_client: reqwest::Client,
}

impl<C: CacheRepository> SpaceService<C> {
    pub fn new(cache_repo: Arc<C>, nasa_key: String) -> Self {
        let http_client = reqwest::Client::builder()
            .timeout(std::time::Duration::from_secs(30))
            .user_agent("KosmoStars-Space/1.0")
            .gzip(true)
            .brotli(true)
            .build()
            .expect("Failed to build HTTP client");

        Self {
            cache_repo,
            nasa_key,
            http_client,
        }
    }

    pub async fn get_latest(&self, source: &str) -> Result<Option<SpaceCache>, ApiError> {
        self.cache_repo.get_latest(source).await
    }

    pub async fn fetch_apod(&self) -> Result<(), ApiError> {
        let url = "https://api.nasa.gov/planetary/apod";
        
        let mut req = self.http_client.get(url).query(&[("thumbs", "true")]);
        if !self.nasa_key.is_empty() {
            req = req.query(&[("api_key", &self.nasa_key)]);
        }

        let response = req.send().await?;
        if !response.status().is_success() {
            return Err(ApiError::upstream(
                response.status().as_u16(),
                "APOD API error".to_string(),
            ));
        }

        let json: Value = response.json().await?;
        self.cache_repo.insert("apod", json).await?;
        Ok(())
    }

    pub async fn fetch_neo(&self) -> Result<(), ApiError> {
        let today = Utc::now().date_naive();
        let start = today - chrono::Days::new(2);
        
        let url = "https://api.nasa.gov/neo/rest/v1/feed";
        let mut req = self.http_client.get(url)
            .query(&[
                ("start_date", start.to_string()),
                ("end_date", today.to_string()),
            ]);
        
        if !self.nasa_key.is_empty() {
            req = req.query(&[("api_key", &self.nasa_key)]);
        }

        let response = req.send().await?;
        if !response.status().is_success() {
            return Err(ApiError::upstream(
                response.status().as_u16(),
                "NEO API error".to_string(),
            ));
        }

        let json: Value = response.json().await?;
        self.cache_repo.insert("neo", json).await?;
        Ok(())
    }

    pub async fn fetch_donki_flr(&self) -> Result<(), ApiError> {
        let (from, to) = Self::last_days(5);
        let url = "https://api.nasa.gov/DONKI/FLR";
        
        let mut req = self.http_client.get(url)
            .query(&[("startDate", &from), ("endDate", &to)]);
        
        if !self.nasa_key.is_empty() {
            req = req.query(&[("api_key", &self.nasa_key)]);
        }

        let response = req.send().await?;
        let json: Value = response.json().await?;
        self.cache_repo.insert("flr", json).await?;
        Ok(())
    }

    pub async fn fetch_donki_cme(&self) -> Result<(), ApiError> {
        let (from, to) = Self::last_days(5);
        let url = "https://api.nasa.gov/DONKI/CME";
        
        let mut req = self.http_client.get(url)
            .query(&[("startDate", &from), ("endDate", &to)]);
        
        if !self.nasa_key.is_empty() {
            req = req.query(&[("api_key", &self.nasa_key)]);
        }

        let response = req.send().await?;
        let json: Value = response.json().await?;
        self.cache_repo.insert("cme", json).await?;
        Ok(())
    }

    pub async fn fetch_spacex(&self) -> Result<(), ApiError> {
        let url = "https://api.spacexdata.com/v4/launches/next";
        
        let response = self.http_client.get(url).send().await?;
        let json: Value = response.json().await?;
        self.cache_repo.insert("spacex", json).await?;
        Ok(())
    }

    pub async fn refresh(&self, sources: Vec<&str>) -> Result<Vec<String>, ApiError> {
        let mut done = Vec::new();

        for source in sources {
            match source {
                "apod" => {
                    if self.fetch_apod().await.is_ok() {
                        done.push("apod".to_string());
                    }
                }
                "neo" => {
                    if self.fetch_neo().await.is_ok() {
                        done.push("neo".to_string());
                    }
                }
                "flr" => {
                    if self.fetch_donki_flr().await.is_ok() {
                        done.push("flr".to_string());
                    }
                }
                "cme" => {
                    if self.fetch_donki_cme().await.is_ok() {
                        done.push("cme".to_string());
                    }
                }
                "spacex" => {
                    if self.fetch_spacex().await.is_ok() {
                        done.push("spacex".to_string());
                    }
                }
                _ => {}
            }
        }

        Ok(done)
    }

    fn last_days(n: u64) -> (String, String) {
        let to = Utc::now().date_naive();
        let from = to - chrono::Days::new(n);
        (from.to_string(), to.to_string())
    }
}
