use axum::{
    http::StatusCode,
    response::{IntoResponse, Response},
    Json,
};
use serde::{Deserialize, Serialize};
use std::fmt;
use uuid::Uuid;

/// Unified API error structure
#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct ApiError {
    pub code: String,
    pub message: String,
    pub trace_id: String,
}

impl ApiError {
    pub fn new(code: impl Into<String>, message: impl Into<String>) -> Self {
        Self {
            code: code.into(),
            message: message.into(),
            trace_id: Uuid::new_v4().to_string(),
        }
    }

    pub fn database(msg: impl Into<String>) -> Self {
        Self::new("DATABASE_ERROR", msg)
    }

    pub fn upstream(status: u16, msg: impl Into<String>) -> Self {
        Self::new(format!("UPSTREAM_{}", status), msg)
    }

    pub fn internal(msg: impl Into<String>) -> Self {
        Self::new("INTERNAL_ERROR", msg)
    }

    pub fn validation(msg: impl Into<String>) -> Self {
        Self::new("VALIDATION_ERROR", msg)
    }

    pub fn not_found(msg: impl Into<String>) -> Self {
        Self::new("NOT_FOUND", msg)
    }
}

impl fmt::Display for ApiError {
    fn fmt(&self, f: &mut fmt::Formatter<'_>) -> fmt::Result {
        write!(f, "[{}] {}", self.code, self.message)
    }
}

impl std::error::Error for ApiError {}

#[derive(Debug, Serialize)]
struct ErrorEnvelope {
    ok: bool,
    error: ApiError,
}

impl IntoResponse for ApiError {
    fn into_response(self) -> Response {
        let envelope = ErrorEnvelope {
            ok: false,
            error: self,
        };
        (StatusCode::OK, Json(envelope)).into_response()
    }
}

impl From<sqlx::Error> for ApiError {
    fn from(err: sqlx::Error) -> Self {
        tracing::error!("Database error: {:?}", err);
        Self::database(err.to_string())
    }
}

impl From<reqwest::Error> for ApiError {
    fn from(err: reqwest::Error) -> Self {
        tracing::error!("HTTP client error: {:?}", err);
        let status = err.status().map(|s| s.as_u16()).unwrap_or(500);
        Self::upstream(status, err.to_string())
    }
}

impl From<anyhow::Error> for ApiError {
    fn from(err: anyhow::Error) -> Self {
        tracing::error!("Internal error: {:?}", err);
        Self::internal(err.to_string())
    }
}

#[derive(Debug, Serialize)]
pub struct ApiResponse<T> {
    pub ok: bool,
    #[serde(skip_serializing_if = "Option::is_none")]
    pub data: Option<T>,
    #[serde(skip_serializing_if = "Option::is_none")]
    pub error: Option<ApiError>,
}

impl<T> ApiResponse<T> {
    pub fn success(data: T) -> Self {
        Self {
            ok: true,
            data: Some(data),
            error: None,
        }
    }

    pub fn error(error: ApiError) -> ApiResponse<()> {
        ApiResponse {
            ok: false,
            data: None,
            error: Some(error),
        }
    }
}
