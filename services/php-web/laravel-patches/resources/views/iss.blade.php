@extends('layouts.app')

@section('content')
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">
      <i class="bi bi-rocket-takeoff me-2"></i>Международная космическая станция
    </h3>
    <span class="badge bg-success">Live Data</span>
  </div>

  <div class="row g-4">
    {{-- Карта --}}
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-body">
          <h5 class="card-title mb-3">Текущее положение МКС</h5>
          <div id="map" class="rounded" style="height: 400px;"></div>
        </div>
      </div>
    </div>

    {{-- Текущие данные --}}
    <div class="col-md-6">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h5 class="card-title">
            <i class="bi bi-geo-alt me-2"></i>Текущие координаты
          </h5>
          @if(!empty($latest))
            <div class="row g-3 mt-2">
              <div class="col-6">
                <div class="border rounded p-3 text-center stat-card">
                  <div class="text-muted small">Широта</div>
                  <div class="fs-4 fw-bold">{{ number_format($latest['latitude'] ?? 0, 4) }}°</div>
                </div>
              </div>
              <div class="col-6">
                <div class="border rounded p-3 text-center stat-card">
                  <div class="text-muted small">Долгота</div>
                  <div class="fs-4 fw-bold">{{ number_format($latest['longitude'] ?? 0, 4) }}°</div>
                </div>
              </div>
              <div class="col-6">
                <div class="border rounded p-3 text-center stat-card">
                  <div class="text-muted small">Высота</div>
                  <div class="fs-4 fw-bold">{{ number_format($latest['altitude'] ?? 0, 1) }} <small>км</small></div>
                </div>
              </div>
              <div class="col-6">
                <div class="border rounded p-3 text-center stat-card">
                  <div class="text-muted small">Скорость</div>
                  <div class="fs-4 fw-bold">{{ number_format($latest['velocity'] ?? 0, 0, '', ' ') }} <small>км/ч</small></div>
                </div>
              </div>
            </div>
            <div class="mt-3 text-muted small">
              <i class="bi bi-clock me-1"></i>Обновлено: {{ $latest['timestamp'] ?? now()->toDateTimeString() }}
            </div>
          @else
            <div class="alert alert-info">Нет данных о положении МКС</div>
          @endif
        </div>
      </div>
    </div>

    {{-- Тренд --}}
    <div class="col-md-6">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h5 class="card-title">
            <i class="bi bi-graph-up me-2"></i>Тренд за 24 часа
          </h5>
          <canvas id="trendChart" height="200"></canvas>
        </div>
      </div>
    </div>

    {{-- Графики --}}
    <div class="col-md-6">
      <div class="card shadow-sm">
        <div class="card-body">
          <h5 class="card-title">Скорость (км/ч)</h5>
          <canvas id="velocityChart" height="150"></canvas>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card shadow-sm">
        <div class="card-body">
          <h5 class="card-title">Высота (км)</h5>
          <canvas id="altitudeChart" height="150"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Карта
  const lat = {{ $latest['latitude'] ?? 0 }};
  const lon = {{ $latest['longitude'] ?? 0 }};
  
  const map = L.map('map').setView([lat || 0, lon || 0], lat ? 3 : 2);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap'
  }).addTo(map);
  
  if (lat && lon) {
    const marker = L.marker([lat, lon]).addTo(map)
      .bindPopup('<b>МКС</b><br>Широта: ' + lat.toFixed(4) + '°<br>Долгота: ' + lon.toFixed(4) + '°')
      .openPopup();
  }

  // Тренд данные
  const trendData = @json($trend ?? []);
  const labels = (trendData || []).map(t => t.hour || '');
  const avgVelocity = (trendData || []).map(t => t.avg_velocity || 0);
  const avgAltitude = (trendData || []).map(t => t.avg_altitude || 0);

  // Тренд график
  new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
      labels: labels.slice(0, 12).reverse(),
      datasets: [{
        label: 'Кол-во замеров',
        data: (trendData || []).slice(0, 12).map(t => t.cnt || 0).reverse(),
        borderColor: '#0d6efd',
        tension: 0.3
      }]
    },
    options: { responsive: true, plugins: { legend: { display: false } } }
  });

  // Скорость график
  new Chart(document.getElementById('velocityChart'), {
    type: 'line',
    data: {
      labels: labels.slice(0, 12).reverse(),
      datasets: [{
        label: 'Ср. скорость',
        data: avgVelocity.slice(0, 12).reverse(),
        borderColor: '#198754',
        backgroundColor: 'rgba(25, 135, 84, 0.1)',
        fill: true,
        tension: 0.3
      }]
    },
    options: { responsive: true }
  });

  // Высота график
  new Chart(document.getElementById('altitudeChart'), {
    type: 'line',
    data: {
      labels: labels.slice(0, 12).reverse(),
      datasets: [{
        label: 'Ср. высота',
        data: avgAltitude.slice(0, 12).reverse(),
        borderColor: '#dc3545',
        backgroundColor: 'rgba(220, 53, 69, 0.1)',
        fill: true,
        tension: 0.3
      }]
    },
    options: { responsive: true }
  });

  // Автообновление каждые 30 секунд
  setInterval(() => {
    fetch('/api/iss/latest')
      .then(r => r.json())
      .then(data => {
        if (data.ok && data.data) {
          // Обновление позиции на карте можно добавить здесь
        }
      })
      .catch(() => {});
  }, 30000);
});
</script>
@endsection
