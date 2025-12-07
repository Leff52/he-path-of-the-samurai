pub mod models;

pub use models::*;

// Utility functions для domain logic
use chrono::{DateTime, Utc};
use serde_json::Value;

pub fn parse_number(v: &Value) -> Option<f64> {
    if let Some(x) = v.as_f64() {
        return Some(x);
    }
    if let Some(s) = v.as_str() {
        return s.parse::<f64>().ok();
    }
    None
}

pub fn haversine_distance_km(lat1: f64, lon1: f64, lat2: f64, lon2: f64) -> f64 {
    let r_lat1 = lat1.to_radians();
    let r_lat2 = lat2.to_radians();
    let d_lat = (lat2 - lat1).to_radians();
    let d_lon = (lon2 - lon1).to_radians();
    
    let a = (d_lat / 2.0).sin().powi(2)
        + r_lat1.cos() * r_lat2.cos() * (d_lon / 2.0).sin().powi(2);
    let c = 2.0 * a.sqrt().atan2((1.0 - a).sqrt());
    
    6371.0 * c // Earth radius in km
}

pub fn extract_string(json: &Value, keys: &[&str]) -> Option<String> {
    for key in keys {
        if let Some(val) = json.get(*key) {
            if let Some(s) = val.as_str() {
                if !s.is_empty() {
                    return Some(s.to_string());
                }
            } else if val.is_number() {
                return Some(val.to_string());
            }
        }
    }
    None
}

pub fn extract_timestamp(json: &Value, keys: &[&str]) -> Option<DateTime<Utc>> {
    use chrono::NaiveDateTime;
    
    for key in keys {
        if let Some(val) = json.get(*key) {
            if let Some(s) = val.as_str() {
                // Try parsing as RFC3339/ISO8601
                if let Ok(dt) = s.parse::<DateTime<Utc>>() {
                    return Some(dt);
                }
                // Try naive datetime
                if let Ok(ndt) = NaiveDateTime::parse_from_str(s, "%Y-%m-%d %H:%M:%S") {
                    return Some(DateTime::from_naive_utc_and_offset(ndt, Utc));
                }
            } else if let Some(n) = val.as_i64() {
                // Unix timestamp
                return DateTime::from_timestamp(n, 0);
            }
        }
    }
    None
}
