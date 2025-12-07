@extends('layouts.app')

@section('content')
<div class="container py-4">
  <div class="text-center mb-4">
    <h2 class="mb-2">
      <i class="bi bi-heart-pulse me-2 text-danger"></i>System Health
    </h2>
    <p class="text-muted">Статус всех сервисов приложения</p>
  </div>

  <div class="row g-4 justify-content-center">
    {{-- Общий статус --}}
    <div class="col-12">
      <div class="card shadow-sm border-0" id="overallCard">
        <div class="card-body text-center py-4">
          <div id="overallStatus">
            <div class="spinner-border text-primary mb-3" role="status"></div>
            <h4 class="text-muted">Проверка сервисов...</h4>
          </div>
        </div>
      </div>
    </div>

    {{-- Сервисы --}}
    <div class="col-md-6 col-lg-4">
      <div class="card shadow-sm h-100 service-card" data-service="php">
        <div class="card-body">
          <div class="d-flex align-items-center mb-3">
            <div class="service-icon bg-primary bg-opacity-10 rounded-circle p-3 me-3">
              <i class="bi bi-filetype-php fs-4 text-primary"></i>
            </div>
            <div>
              <h5 class="mb-0">PHP / Laravel</h5>
              <small class="text-muted">Web Application</small>
            </div>
            <div class="ms-auto">
              <span class="status-badge badge rounded-pill bg-secondary">
                <i class="bi bi-hourglass-split"></i>
              </span>
            </div>
          </div>
          <div class="service-details small">
            <div class="d-flex justify-content-between py-1 border-bottom">
              <span class="text-muted">Версия PHP:</span>
              <span class="fw-medium">{{ PHP_VERSION }}</span>
            </div>
            <div class="d-flex justify-content-between py-1 border-bottom">
              <span class="text-muted">Laravel:</span>
              <span class="fw-medium">{{ app()->version() }}</span>
            </div>
            <div class="d-flex justify-content-between py-1">
              <span class="text-muted">Environment:</span>
              <span class="fw-medium">{{ config('app.env') }}</span>
            </div>
          </div>
          <div class="response-time mt-2 text-end small text-muted"></div>
        </div>
      </div>
    </div>

    <div class="col-md-6 col-lg-4">
      <div class="card shadow-sm h-100 service-card" data-service="db">
        <div class="card-body">
          <div class="d-flex align-items-center mb-3">
            <div class="service-icon bg-success bg-opacity-10 rounded-circle p-3 me-3">
              <i class="bi bi-database fs-4 text-success"></i>
            </div>
            <div>
              <h5 class="mb-0">PostgreSQL</h5>
              <small class="text-muted">Database</small>
            </div>
            <div class="ms-auto">
              <span class="status-badge badge rounded-pill bg-secondary">
                <i class="bi bi-hourglass-split"></i>
              </span>
            </div>
          </div>
          <div class="service-details small">
            <div class="d-flex justify-content-between py-1 border-bottom">
              <span class="text-muted">Host:</span>
              <span class="fw-medium">{{ config('database.connections.pgsql.host') }}</span>
            </div>
            <div class="d-flex justify-content-between py-1 border-bottom">
              <span class="text-muted">Database:</span>
              <span class="fw-medium">{{ config('database.connections.pgsql.database') }}</span>
            </div>
            <div class="d-flex justify-content-between py-1">
              <span class="text-muted">Port:</span>
              <span class="fw-medium">{{ config('database.connections.pgsql.port') }}</span>
            </div>
          </div>
          <div class="response-time mt-2 text-end small text-muted"></div>
        </div>
      </div>
    </div>

    <div class="col-md-6 col-lg-4">
      <div class="card shadow-sm h-100 service-card" data-service="rust">
        <div class="card-body">
          <div class="d-flex align-items-center mb-3">
            <div class="service-icon bg-warning bg-opacity-10 rounded-circle p-3 me-3">
              <i class="bi bi-gear fs-4 text-warning"></i>
            </div>
            <div>
              <h5 class="mb-0">Rust ISS</h5>
              <small class="text-muted">ISS Tracker Service</small>
            </div>
            <div class="ms-auto">
              <span class="status-badge badge rounded-pill bg-secondary">
                <i class="bi bi-hourglass-split"></i>
              </span>
            </div>
          </div>
          <div class="service-details small">
            <div class="d-flex justify-content-between py-1 border-bottom">
              <span class="text-muted">Endpoint:</span>
              <span class="fw-medium">rust_iss:3000</span>
            </div>
            <div class="d-flex justify-content-between py-1 border-bottom">
              <span class="text-muted">Function:</span>
              <span class="fw-medium">ISS Position</span>
            </div>
            <div class="d-flex justify-content-between py-1">
              <span class="text-muted">Protocol:</span>
              <span class="fw-medium">HTTP REST</span>
            </div>
          </div>
          <div class="response-time mt-2 text-end small text-muted"></div>
        </div>
      </div>
    </div>

    <div class="col-md-6 col-lg-4">
      <div class="card shadow-sm h-100 service-card" data-service="jwst">
        <div class="card-body">
          <div class="d-flex align-items-center mb-3">
            <div class="service-icon bg-info bg-opacity-10 rounded-circle p-3 me-3">
              <i class="bi bi-telescope fs-4 text-info"></i>
            </div>
            <div>
              <h5 class="mb-0">JWST API</h5>
              <small class="text-muted">James Webb Telescope</small>
            </div>
            <div class="ms-auto">
              <span class="status-badge badge rounded-pill bg-secondary">
                <i class="bi bi-hourglass-split"></i>
              </span>
            </div>
          </div>
          <div class="service-details small">
            <div class="d-flex justify-content-between py-1 border-bottom">
              <span class="text-muted">Host:</span>
              <span class="fw-medium">api.jwstapi.com</span>
            </div>
            <div class="d-flex justify-content-between py-1 border-bottom">
              <span class="text-muted">Type:</span>
              <span class="fw-medium">External API</span>
            </div>
            <div class="d-flex justify-content-between py-1">
              <span class="text-muted">Auth:</span>
              <span class="fw-medium">API Key</span>
            </div>
          </div>
          <div class="response-time mt-2 text-end small text-muted"></div>
        </div>
      </div>
    </div>

    <div class="col-md-6 col-lg-4">
      <div class="card shadow-sm h-100 service-card" data-service="nasa">
        <div class="card-body">
          <div class="d-flex align-items-center mb-3">
            <div class="service-icon bg-danger bg-opacity-10 rounded-circle p-3 me-3">
              <i class="bi bi-rocket-takeoff fs-4 text-danger"></i>
            </div>
            <div>
              <h5 class="mb-0">NASA API</h5>
              <small class="text-muted">NEO & APOD</small>
            </div>
            <div class="ms-auto">
              <span class="status-badge badge rounded-pill bg-secondary">
                <i class="bi bi-hourglass-split"></i>
              </span>
            </div>
          </div>
          <div class="service-details small">
            <div class="d-flex justify-content-between py-1 border-bottom">
              <span class="text-muted">Host:</span>
              <span class="fw-medium">api.nasa.gov</span>
            </div>
            <div class="d-flex justify-content-between py-1 border-bottom">
              <span class="text-muted">Services:</span>
              <span class="fw-medium">NEO, APOD, DONKI</span>
            </div>
            <div class="d-flex justify-content-between py-1">
              <span class="text-muted">Auth:</span>
              <span class="fw-medium">API Key</span>
            </div>
          </div>
          <div class="response-time mt-2 text-end small text-muted"></div>
        </div>
      </div>
    </div>

    <div class="col-md-6 col-lg-4">
      <div class="card shadow-sm h-100 service-card" data-service="astro">
        <div class="card-body">
          <div class="d-flex align-items-center mb-3">
            <div class="service-icon bg-purple bg-opacity-10 rounded-circle p-3 me-3" style="background-color: rgba(128,0,128,0.1);">
              <i class="bi bi-stars fs-4" style="color: purple;"></i>
            </div>
            <div>
              <h5 class="mb-0">Astronomy API</h5>
              <small class="text-muted">Planets & Events</small>
            </div>
            <div class="ms-auto">
              <span class="status-badge badge rounded-pill bg-secondary">
                <i class="bi bi-hourglass-split"></i>
              </span>
            </div>
          </div>
          <div class="service-details small">
            <div class="d-flex justify-content-between py-1 border-bottom">
              <span class="text-muted">Host:</span>
              <span class="fw-medium">api.astronomyapi.com</span>
            </div>
            <div class="d-flex justify-content-between py-1 border-bottom">
              <span class="text-muted">Services:</span>
              <span class="fw-medium">Positions, Events</span>
            </div>
            <div class="d-flex justify-content-between py-1">
              <span class="text-muted">Auth:</span>
              <span class="fw-medium">Basic Auth</span>
            </div>
          </div>
          <div class="response-time mt-2 text-end small text-muted"></div>
        </div>
      </div>
    </div>
  </div>

  {{-- Timestamp --}}
  <div class="text-center mt-4">
    <small class="text-muted">
      <i class="bi bi-clock me-1"></i>
      Последняя проверка: <span id="lastCheck">—</span>
    </small>
    <button class="btn btn-sm btn-outline-primary ms-3" id="refreshBtn">
      <i class="bi bi-arrow-clockwise me-1"></i>Обновить
    </button>
  </div>
</div>

<style>
  .service-card {
    transition: all 0.3s ease;
    border-left: 4px solid #dee2e6 !important;
  }
  .service-card.healthy {
    border-left-color: #198754 !important;
  }
  .service-card.unhealthy {
    border-left-color: #dc3545 !important;
  }
  .service-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 .5rem 1rem rgba(0,0,0,.1) !important;
  }
  .service-icon {
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .status-badge {
    font-size: 0.9rem;
    padding: 0.5em 0.8em;
  }
  .status-badge.bg-success {
    animation: pulse-green 2s infinite;
  }
  @keyframes pulse-green {
    0%, 100% { box-shadow: 0 0 0 0 rgba(25, 135, 84, 0.4); }
    50% { box-shadow: 0 0 0 8px rgba(25, 135, 84, 0); }
  }
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const checks = {
    php: { url: null, check: async () => ({ ok: true, time: 0 }) },
    db: { url: '/api/health/db' },
    rust: { url: '/api/iss/latest' },
    jwst: { url: '/api/jwst/feed?perPage=1' },
    nasa: { url: '/api/health/nasa' },
    astro: { url: '/api/astro/positions?lat=55.7558&lon=37.6176&days=1' }
  };

  async function checkService(name, config) {
    const card = document.querySelector(`.service-card[data-service="${name}"]`);
    const badge = card.querySelector('.status-badge');
    const timeEl = card.querySelector('.response-time');
    
    try {
      const start = performance.now();
      
      if (config.check) {
        const result = await config.check();
        updateCard(card, badge, timeEl, result.ok, result.time);
        return result.ok;
      }
      
      const res = await fetch(config.url);
      const elapsed = Math.round(performance.now() - start);
      const ok = res.ok;
      
      updateCard(card, badge, timeEl, ok, elapsed);
      return ok;
    } catch (e) {
      updateCard(card, badge, timeEl, false, 0);
      return false;
    }
  }

  function updateCard(card, badge, timeEl, ok, time) {
    card.classList.remove('healthy', 'unhealthy');
    card.classList.add(ok ? 'healthy' : 'unhealthy');
    
    badge.className = 'status-badge badge rounded-pill ' + (ok ? 'bg-success' : 'bg-danger');
    badge.innerHTML = ok 
      ? '<i class="bi bi-check-lg"></i>' 
      : '<i class="bi bi-x-lg"></i>';
    
    timeEl.textContent = ok ? `${time}ms` : 'Недоступен';
  }

  function updateOverall(results) {
    const allOk = results.every(r => r);
    const okCount = results.filter(r => r).length;
    const total = results.length;
    
    const overall = document.getElementById('overallStatus');
    const card = document.getElementById('overallCard');
    
    card.className = 'card shadow-sm border-0 ' + (allOk ? 'bg-success' : okCount > total/2 ? 'bg-warning' : 'bg-danger') + ' bg-opacity-10';
    
    overall.innerHTML = `
      <h3 class="${allOk ? 'text-success' : okCount > total/2 ? 'text-warning' : 'text-danger'}">
        ${allOk ? 'Все системы работают' : `${okCount}/${total} сервисов доступно`}
      </h3>
      <p class="text-muted mb-0">
        ${allOk ? 'Приложение полностью функционирует' : 'Некоторые сервисы недоступны'}
      </p>
    `;
  }

  async function runChecks() {
    document.getElementById('lastCheck').textContent = new Date().toLocaleString('ru-RU');
    
    document.querySelectorAll('.service-card').forEach(card => {
      card.classList.remove('healthy', 'unhealthy');
      const badge = card.querySelector('.status-badge');
      badge.className = 'status-badge badge rounded-pill bg-secondary';
      badge.innerHTML = '<i class="bi bi-hourglass-split"></i>';
    });
    
    document.getElementById('overallStatus').innerHTML = `
      <div class="spinner-border text-primary mb-3" role="status"></div>
      <h4 class="text-muted">Проверка сервисов...</h4>
    `;
    
    const results = await Promise.all(
      Object.entries(checks).map(([name, config]) => checkService(name, config))
    );
    
    updateOverall(results);
  }

  document.getElementById('refreshBtn').addEventListener('click', runChecks);
  
  runChecks();
});
</script>
@endsection
