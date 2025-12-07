use std::env;

#[derive(Debug, Clone)]
pub struct AppConfig {
    // Database
    pub database_url: String,
    pub db_pool_size: u32,
    
    // Server
    pub port: u16,
    
    // External APIs
    pub nasa_api_url: String,
    pub nasa_api_key: String,
    pub where_iss_url: String,
    
    // Intervals (seconds)
    pub iss_every_seconds: u64,
    pub osdr_every_seconds: u64,
    pub apod_every_seconds: u64,
    pub neo_every_seconds: u64,
    pub donki_every_seconds: u64,
    pub spacex_every_seconds: u64,
}

impl AppConfig {
    pub fn from_env() -> anyhow::Result<Self> {
        Ok(Self {
            database_url: env::var("DATABASE_URL")
                .expect("DATABASE_URL must be set"),
            db_pool_size: parse_env("DB_POOL_SIZE", 5),
            
            port: parse_env("PORT", 3000),
            
            nasa_api_url: env::var("NASA_API_URL")
                .unwrap_or_else(|_| "https://visualization.osdr.nasa.gov/biodata/api/v2/datasets/?format=json".to_string()),
            nasa_api_key: env::var("NASA_API_KEY")
                .unwrap_or_default(),
            where_iss_url: env::var("WHERE_ISS_URL")
                .unwrap_or_else(|_| "https://api.wheretheiss.at/v1/satellites/25544".to_string()),
            
            iss_every_seconds: parse_env("ISS_EVERY_SECONDS", 120),
            osdr_every_seconds: parse_env("FETCH_EVERY_SECONDS", 600),
            apod_every_seconds: parse_env("APOD_EVERY_SECONDS", 43200),
            neo_every_seconds: parse_env("NEO_EVERY_SECONDS", 7200),
            donki_every_seconds: parse_env("DONKI_EVERY_SECONDS", 3600),
            spacex_every_seconds: parse_env("SPACEX_EVERY_SECONDS", 3600),
        })
    }
}

fn parse_env<T>(key: &str, default: T) -> T
where
    T: std::str::FromStr,
{
    env::var(key)
        .ok()
        .and_then(|s| s.parse().ok())
        .unwrap_or(default)
}
