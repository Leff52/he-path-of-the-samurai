use std::sync::Arc;
use axum::{
    extract::{Query, State},
    response::Json,
};
use serde::{Deserialize, Serialize};
use serde_json::{json, Value};

use crate::repo::IssRepository;
use crate::services::IssService;

pub type IssServiceState<R> = Arc<IssService<R>>;

#[derive(Debug, Deserialize)]
pub struct TrendQuery {
    #[serde(default = "default_hours")]
    pub hours: i64,
}

fn default_hours() -> i64 {
    24
}

#[derive(Debug, Serialize)]
pub struct IssResponse {
    pub ok: bool,
    #[serde(skip_serializing_if = "Option::is_none")]
    pub data: Option<Value>,
    #[serde(skip_serializing_if = "Option::is_none")]
    pub error: Option<Value>,
}

pub async fn get_latest<R: IssRepository>(
    State(svc): State<IssServiceState<R>>,
) -> Json<IssResponse> {
    match svc.get_latest().await {
        Ok(Some(log)) => Json(IssResponse {
            ok: true,
            data: Some(json!({
                "latitude": log.lat(),
                "longitude": log.lon(),
                "altitude": log.altitude(),
                "velocity": log.velocity(),
                "visibility": log.visibility(),
                "timestamp": log.fetched_at.to_string(),
                "payload": log.payload,
            })),
            error: None,
        }),
        Ok(None) => Json(IssResponse {
            ok: true,
            data: Some(json!(null)),
            error: None,
        }),
        Err(e) => {
            Json(IssResponse {
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

pub async fn get_trend<R: IssRepository>(
    State(svc): State<IssServiceState<R>>,
    Query(query): Query<TrendQuery>,
) -> Json<IssResponse> {
    let hours = query.hours.clamp(1, 168); // Max 7 days

    match svc.get_trend(hours).await {
        Ok(trends) => {
            let data: Vec<Value> = trends
                .into_iter()
                .map(|t| json!({
                    "hour": t.hour,
                    "avg_lat": t.avg_lat,
                    "avg_lon": t.avg_lon,
                    "avg_altitude": t.avg_altitude,
                    "avg_velocity": t.avg_velocity,
                    "cnt": t.cnt,
                }))
                .collect();
            
            Json(IssResponse {
                ok: true,
                data: Some(json!(data)),
                error: None,
            })
        }
        Err(e) => {
            Json(IssResponse {
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

pub async fn refresh_iss<R: IssRepository>(
    State(svc): State<IssServiceState<R>>,
) -> Json<IssResponse> {
    match svc.fetch_and_store().await {
        Ok(_) => Json(IssResponse {
            ok: true,
            data: Some(json!({"status": "refreshed"})),
            error: None,
        }),
        Err(e) => {
            Json(IssResponse {
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
