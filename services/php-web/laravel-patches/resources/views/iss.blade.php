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
  
  // Иконка МКС
  const issIcon = L.icon({
    iconUrl: 'https://upload.wikimedia.org/wikipedia/commons/d/d0/International_Space_Station.svg',
    iconSize: [50, 30],
    iconAnchor: [25, 15]
  });
  
  let marker = null;
  let trajectory = [];
  let polyline = null;
  
  if (lat && lon) {
    marker = L.marker([lat, lon], { icon: issIcon }).addTo(map)
      .bindPopup('<b>МКС</b><br>Широта: ' + lat.toFixed(4) + '°<br>Долгота: ' + lon.toFixed(4) + '°')
      .openPopup();
    trajectory.push([lat, lon]);
  }

  // Тренд данные
  let trendData = @json($trend ?? []);
  const labels = (trendData || []).map(t => t.hour || '');
  const avgVelocity = (trendData || []).map(t => t.avg_velocity || 0);
  const avgAltitude = (trendData || []).map(t => t.avg_altitude || 0);

  // Тренд график
  const trendChart = new Chart(document.getElementById('trendChart'), {
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
  const velocityChart = new Chart(document.getElementById('velocityChart'), {
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
    options: { 
      responsive: true,
      scales: {
        y: {
          beginAtZero: false
        }
      }
    }
  });

  // Высота график
  const altitudeChart = new Chart(document.getElementById('altitudeChart'), {
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
    options: { 
      responsive: true,
      scales: {
        y: {
          beginAtZero: false
        }
      }
    }
  });

  // Функция обновления данных
  function updateISSData() {
    fetch('/api/iss/latest')
      .then(r => r.json())
      .then(data => {
        if (data.ok && data.data) {
          const iss = data.data;
          const newLat = parseFloat(iss.latitude);
          const newLon = parseFloat(iss.longitude);
          const alt = parseFloat(iss.altitude);
          const vel = parseFloat(iss.velocity);
          
          // Обновление маркера на карте
          if (marker) {
            marker.setLatLng([newLat, newLon]);
            marker.setPopupContent(
              '<b>МКС</b><br>' +
              'Широта: ' + newLat.toFixed(4) + '°<br>' +
              'Долгота: ' + newLon.toFixed(4) + '°<br>' +
              'Высота: ' + alt.toFixed(1) + ' км<br>' +
              'Скорость: ' + vel.toFixed(0) + ' км/ч'
            );
          } else {
            marker = L.marker([newLat, newLon], { icon: issIcon }).addTo(map)
              .bindPopup('<b>МКС</b><br>Широта: ' + newLat.toFixed(4) + '°')
              .openPopup();
          }
          
          // Добавление точки в траекторию (последние 10 точек)
          trajectory.push([newLat, newLon]);
          if (trajectory.length > 10) {
            trajectory.shift();
          }
          
          // Отрисовка траектории
          if (polyline) {
            map.removeLayer(polyline);
          }
          polyline = L.polyline(trajectory, {
            color: '#0d6efd',
            weight: 3,
            opacity: 0.7,
            dashArray: '5, 10'
          }).addTo(map);
          
          // Центрирование карты на МКС
          map.panTo([newLat, newLon]);
          
          // Обновление статистики
          document.querySelectorAll('.stat-card').forEach((card, idx) => {
            const value = card.querySelector('.fw-bold');
            if (idx === 0) value.innerHTML = newLat.toFixed(4) + '°';
            if (idx === 1) value.innerHTML = newLon.toFixed(4) + '°';
            if (idx === 2) value.innerHTML = alt.toFixed(1) + ' <small>км</small>';
            if (idx === 3) value.innerHTML = vel.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' <small>км/ч</small>';
          });
          
          // Обновление времени
          const timeEl = document.querySelector('.text-muted.small i.bi-clock').parentElement;
          if (timeEl) {
            timeEl.innerHTML = '<i class="bi bi-clock me-1"></i>Обновлено: ' + iss.timestamp;
          }
          
          // Обновление графиков (добавление новых точек)
          const now = new Date().toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
          
          // Обновление графика скорости
          if (velocityChart.data.labels.length > 20) {
            velocityChart.data.labels.shift();
            velocityChart.data.datasets[0].data.shift();
          }
          velocityChart.data.labels.push(now);
          velocityChart.data.datasets[0].data.push(vel);
          velocityChart.update();
          
          // Обновление графика высоты
          if (altitudeChart.data.labels.length > 20) {
            altitudeChart.data.labels.shift();
            altitudeChart.data.datasets[0].data.shift();
          }
          altitudeChart.data.labels.push(now);
          altitudeChart.data.datasets[0].data.push(alt);
          altitudeChart.update();
        }
      })
      .catch(err => console.error('Ошибка обновления данных МКС:', err));
  }

  // Автообновление каждые 60 секунд (1 минута)
  setInterval(updateISSData, 60000);
  
  // Первое автоматическое обновление через 60 секунд
  setTimeout(updateISSData, 60000);
});
</script>
@endsection
