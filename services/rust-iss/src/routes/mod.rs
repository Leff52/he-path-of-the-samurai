use axum::{
    routing::{get, post},
    Router,
};
use std::sync::Arc;

use crate::handlers::{
    get_cache, get_latest, get_trend, health, list_datasets,
    refresh_iss, refresh_space, sync_osdr,
    IssServiceState, OsdrServiceState, SpaceServiceState,
};
use crate::repo::{CacheRepository, IssRepository, OsdrRepository};
use crate::services::{IssService, OsdrService, SpaceService};

pub fn create_router<I, O, C>(
    iss_service: Arc<IssService<I>>,
    osdr_service: Arc<OsdrService<O>>,
    space_service: Arc<SpaceService<C>>,
) -> Router
where
    I: IssRepository + 'static,
    O: OsdrRepository + 'static,
    C: CacheRepository + 'static,
{
    let iss_routes = Router::new()
        .route("/latest", get(get_latest::<I>))
        .route("/trend", get(get_trend::<I>))
        .route("/refresh", post(refresh_iss::<I>))
        .with_state(iss_service as IssServiceState<I>);

    let osdr_routes = Router::new()
        .route("/", get(list_datasets::<O>))
        .route("/sync", post(sync_osdr::<O>))
        .with_state(osdr_service as OsdrServiceState<O>);

    let space_routes = Router::new()
        .route("/cache/:source", get(get_cache::<C>))
        .route("/refresh", post(refresh_space::<C>))
        .with_state(space_service as SpaceServiceState<C>);

    Router::new()
        .route("/health", get(health))
        .nest("/api/iss", iss_routes)
        .nest("/api/osdr", osdr_routes)
        .nest("/api/space", space_routes)
}
