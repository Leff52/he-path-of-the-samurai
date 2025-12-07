use std::sync::Arc;
use axum::{
    extract::{Path, Query, State},
    response::Json,
};
use serde::{Deserialize, Serialize};
use serde_json::{json, Value};

use crate::repo::CacheRepository;
use crate::services::SpaceService;

pub type SpaceServiceState<C> = Arc<SpaceService<C>>;

#[derive(Debug, Deserialize)]
pub struct RefreshQuery {
    #[serde(default)]
    pub sources: Option<String>,
}

#[derive(Debug, Serialize)]
pub struct SpaceResponse {
    pub ok: bool,
    #[serde(skip_serializing_if = "Option::is_none")]
    pub data: Option<Value>,
    #[serde(skip_serializing_if = "Option::is_none")]
    pub error: Option<Value>,
}

pub async fn get_cache<C: CacheRepository>(
    State(svc): State<SpaceServiceState<C>>,
    Path(source): Path<String>,
) -> Json<SpaceResponse> {
    match svc.get_latest(&source).await {
        Ok(Some(cache)) => Json(SpaceResponse {
            ok: true,
            data: Some(json!({
                "source": cache.source,
                "fetched_at": cache.fetched_at.to_string(),
                "payload": cache.payload,
            })),
            error: None,
        }),
        Ok(None) => Json(SpaceResponse {
            ok: true,
            data: Some(json!(null)),
            error: None,
        }),
        Err(e) => {
            Json(SpaceResponse {
                ok: false,
                data: None,
                error: Some(json!({
                    "code": e.code,
                    "message": e.message,
                    "trace_id": e.trace_id,
                })),
            })
        }
    }
}

pub async fn refresh_space<C: CacheRepository>(
    State(svc): State<SpaceServiceState<C>>,
    Query(query): Query<RefreshQuery>,
) -> Json<SpaceResponse> {
    let sources: Vec<&str> = match &query.sources {
        Some(s) => s.split(',').map(|x| x.trim()).collect(),
        None => vec!["apod", "neo", "flr", "cme", "spacex"],
    };

    match svc.refresh(sources).await {
        Ok(done) => Json(SpaceResponse {
            ok: true,
            data: Some(json!({
                "status": "refreshed",
                "sources": done,
            })),
            error: None,
        }),
        Err(e) => {
            Json(SpaceResponse {
                ok: false,
                data: None,
                error: Some(json!({
                    "code": e.code,
                    "message": e.message,
                    "trace_id": e.trace_id,
                })),
            })
        }
    }
}

pub async fn health() -> Json<SpaceResponse> {
    Json(SpaceResponse {
        ok: true,
        data: Some(json!({
            "status": "healthy",
            "service": "kosmostars-space",
            "version": env!("CARGO_PKG_VERSION"),
        })),
        error: None,
    })
}
