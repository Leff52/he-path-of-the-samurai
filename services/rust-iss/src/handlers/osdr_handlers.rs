use std::sync::Arc;
use axum::{
    extract::{Query, State},
    response::Json,
};
use serde::{Deserialize, Serialize};
use serde_json::{json, Value};

use crate::repo::OsdrRepository;
use crate::services::OsdrService;

pub type OsdrServiceState<R> = Arc<OsdrService<R>>;

#[derive(Debug, Deserialize)]
pub struct ListQuery {
    #[serde(default = "default_limit")]
    pub limit: i32,
    #[serde(default)]
    pub offset: i32,
    #[serde(default)]
    pub search: Option<String>,
}

fn default_limit() -> i32 {
    20
}

#[derive(Debug, Serialize)]
pub struct OsdrResponse {
    pub ok: bool,
    #[serde(skip_serializing_if = "Option::is_none")]
    pub data: Option<Value>,
    #[serde(skip_serializing_if = "Option::is_none")]
    pub error: Option<Value>,
}

pub async fn list_datasets<R: OsdrRepository>(
    State(svc): State<OsdrServiceState<R>>,
    Query(query): Query<ListQuery>,
) -> Json<OsdrResponse> {
    let limit = query.limit.clamp(1, 100);
    let offset = query.offset.max(0);

    match svc.list(limit, offset, query.search).await {
        Ok(items) => {
            let data: Vec<Value> = items
                .into_iter()
                .map(|item| json!({
                    "id": item.id,
                    "dataset_id": item.dataset_id,
                    "title": item.title,
                    "organism": item.organism,
                    "study_type": item.study_type,
                    "updated_at": item.updated_at.map(|t| t.to_string()),
                }))
                .collect();

            Json(OsdrResponse {
                ok: true,
                data: Some(json!({
                    "items": data,
                    "limit": limit,
                    "offset": offset,
                })),
                error: None,
            })
        }
        Err(e) => {
            Json(OsdrResponse {
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

pub async fn sync_osdr<R: OsdrRepository>(
    State(svc): State<OsdrServiceState<R>>,
) -> Json<OsdrResponse> {
    match svc.sync_datasets().await {
        Ok(count) => Json(OsdrResponse {
            ok: true,
            data: Some(json!({
                "status": "synced",
                "count": count,
            })),
            error: None,
        }),
        Err(e) => {
            Json(OsdrResponse {
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
