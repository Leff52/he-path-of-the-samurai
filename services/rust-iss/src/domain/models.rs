use chrono::{DateTime, Utc};
use serde::{Deserialize, Serialize};
use serde_json::Value;

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct IssFetchLog {
    pub id: i64,
    pub fetched_at: DateTime<Utc>,
    pub source_url: String,
    pub payload: Value,
}

impl IssFetchLog {
    pub fn lat(&self) -> Option<f64> {
        Self::parse_number(&self.payload["latitude"])
    }
    
    pub fn lon(&self) -> Option<f64> {
        Self::parse_number(&self.payload["longitude"])
    }
    
    pub fn altitude(&self) -> Option<f64> {
        Self::parse_number(&self.payload["altitude"])
    }
    
    pub fn velocity(&self) -> Option<f64> {
        Self::parse_number(&self.payload["velocity"])
    }
    
    pub fn visibility(&self) -> Option<String> {
        self.payload["visibility"].as_str().map(|s| s.to_string())
    }
    
    fn parse_number(v: &Value) -> Option<f64> {
        if let Some(x) = v.as_f64() {
            return Some(x);
        }
        if let Some(s) = v.as_str() {
            return s.parse::<f64>().ok();
        }
        None
    }
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct IssTrend {
    pub hour: String,
    pub avg_lat: f64,
    pub avg_lon: f64,
    pub avg_altitude: f64,
    pub avg_velocity: f64,
    pub cnt: i64,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct OsdrItem {
    pub id: i64,
    pub dataset_id: Option<String>,
    pub title: Option<String>,
    pub organism: Option<String>,
    pub study_type: Option<String>,
    pub status: Option<String>,
    pub updated_at: Option<DateTime<Utc>>,
    pub inserted_at: DateTime<Utc>,
    pub raw: Value,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct SpaceCache {
    pub id: i64,
    pub source: String,
    pub fetched_at: DateTime<Utc>,
    pub payload: Value,
}

#[derive(Debug, Serialize, Deserialize)]
pub struct Health {
    pub status: String,
    pub now: DateTime<Utc>,
    pub version: String,
}
