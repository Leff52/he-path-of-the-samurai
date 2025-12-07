@extends('layouts.app')

@section('content')
<div class="container pb-5">
  {{-- верхние карточки --}}
  <div class="row g-3 mb-2">
    <div class="col-6 col-md-3"><div class="border rounded p-2 text-center stat-card">
      <div class="small text-muted">Скорость МКС</div>
      <div class="fs-4" id="issSpeed">{{ isset(($iss['payload'] ?? [])['velocity']) ? number_format($iss['payload']['velocity'],0,'',' ') : '—' }}</div>
    </div></div>
    <div class="col-6 col-md-3"><div class="border rounded p-2 text-center stat-card">
      <div class="small text-muted">Высота МКС</div>
      <div class="fs-4" id="issAlt">{{ isset(($iss['payload'] ?? [])['altitude']) ? number_format($iss['payload']['altitude'],0,'',' ') : '—' }}</div>
    </div></div>
  </div>

  <div class="row g-3">
    {{-- левая колонка: JWST наблюдение --}}
    <div class="col-lg-7">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h5 class="card-title">
            <i class="bi bi-telescope me-2"></i>JWST — выбранное наблюдение
          </h5>
          <div id="jwstObservation" class="mt-3">
            <div class="text-muted">Загрузка данных JWST...</div>
          </div>
        </div>
      </div>
    </div>

    {{-- правая колонка: карта МКС --}}
    <div class="col-lg-5">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h5 class="card-title">МКС — положение и движение</h5>
          <div id="map" class="rounded mb-2 border" style="height:300px"></div>
          <div class="row g-2">
            <div class="col-6"><canvas id="issSpeedChart" height="110"></canvas></div>
            <div class="col-6"><canvas id="issAltChart"   height="110"></canvas></div>
          </div>
        </div>
      </div>
    </div>

    {{-- НИЖНЯЯ ПОЛОСА: НОВАЯ ГАЛЕРЕЯ JWST --}}
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="card-title m-0">JWST — последние изображения</h5>
            <form id="jwstFilter" class="row g-2 align-items-center">
              <div class="col-auto">
                <select class="form-select form-select-sm" name="source" id="srcSel">
                  <option value="jpg" selected>Все JPG</option>
                  <option value="suffix">По суффиксу</option>
                  <option value="program">По программе</option>
                </select>
              </div>
              <div class="col-auto">
                <input type="text" class="form-control form-control-sm" name="suffix" id="suffixInp" placeholder="_cal / _thumb" style="width:140px;display:none">
                <input type="text" class="form-control form-control-sm" name="program" id="progInp" placeholder="2734" style="width:110px;display:none">
              </div>
              <div class="col-auto">
                <select class="form-select form-select-sm" name="instrument" style="width:130px">
                  <option value="">Любой инструмент</option>
                  <option>NIRCam</option><option>MIRI</option><option>NIRISS</option><option>NIRSpec</option><option>FGS</option>
                </select>
              </div>
              <div class="col-auto">
                <select class="form-select form-select-sm" name="perPage" style="width:90px">
                  <option>12</option><option selected>24</option><option>36</option><option>48</option>
                </select>
              </div>
              <div class="col-auto">
                <button class="btn btn-sm btn-primary" type="submit">Показать</button>
              </div>
            </form>
          </div>

          <style>
            .jwst-slider{position:relative}
            .jwst-track{
              display:flex; gap:.75rem; overflow:auto; scroll-snap-type:x mandatory; padding:.25rem;
            }
            .jwst-item{flex:0 0 180px; scroll-snap-align:start}
            .jwst-item img{width:100%; height:180px; object-fit:cover; border-radius:.5rem}
            .jwst-cap{font-size:.85rem; margin-top:.25rem}
            .jwst-nav{position:absolute; top:40%; transform:translateY(-50%); z-index:2}
            .jwst-prev{left:-.25rem} .jwst-next{right:-.25rem}
          </style>

          <div class="jwst-slider">
            <button class="btn btn-light border jwst-nav jwst-prev" type="button" aria-label="Prev">‹</button>
            <div id="jwstTrack" class="jwst-track border rounded"></div>
            <button class="btn btn-light border jwst-nav jwst-next" type="button" aria-label="Next">›</button>
          </div>

          <div id="jwstInfo" class="small text-muted mt-2"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async function () {
  // ====== карта и графики МКС ======
  if (typeof L !== 'undefined' && typeof Chart !== 'undefined') {
    const last = @json(($iss['payload'] ?? []));
    let lat0 = Number(last.latitude || 0), lon0 = Number(last.longitude || 0);
    
    // Иконка МКС
    const issIcon = L.icon({
      iconUrl: 'https://upload.wikimedia.org/wikipedia/commons/d/d0/International_Space_Station.svg',
      iconSize: [50, 30],
      iconAnchor: [25, 15]
    });
    
    const map = L.map('map', { attributionControl:false }).setView([lat0||0, lon0||0], lat0?3:2);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      noWrap: true,
      maxZoom: 19,
      attribution: '© OpenStreetMap'
    }).addTo(map);
    
    // Массивы для хранения истории точек
    let trajectoryPoints = lat0 && lon0 ? [[lat0, lon0]] : [];
    let speedHistory = [];
    let altHistory = [];
    let timeLabels = [];
    
    const trail = L.polyline(trajectoryPoints, {color:'#0d6efd', weight:3, opacity:0.7, dashArray:'5,10'}).addTo(map);
    const marker = L.marker([lat0||0, lon0||0], { icon: issIcon }).addTo(map).bindPopup('МКС');

    const speedChart = new Chart(document.getElementById('issSpeedChart'), {
      type: 'line', 
      data: { 
        labels: [], 
        datasets: [{ 
          label: 'Скорость (км/ч)', 
          data: [],
          borderColor: '#198754',
          backgroundColor: 'rgba(25, 135, 84, 0.1)',
          fill: true,
          tension: 0.3
        }] 
      },
      options: { 
        responsive: true, 
        maintainAspectRatio: false,
        scales: { 
          x: { display: false },
          y: { beginAtZero: false }
        },
        plugins: {
          legend: { display: true, position: 'top' }
        }
      }
    });
    
    const altChart = new Chart(document.getElementById('issAltChart'), {
      type: 'line', 
      data: { 
        labels: [], 
        datasets: [{ 
          label: 'Высота (км)', 
          data: [],
          borderColor: '#dc3545',
          backgroundColor: 'rgba(220, 53, 69, 0.1)',
          fill: true,
          tension: 0.3
        }] 
      },
      options: { 
        responsive: true,
        maintainAspectRatio: false,
        scales: { 
          x: { display: false },
          y: { beginAtZero: false }
        },
        plugins: {
          legend: { display: true, position: 'top' }
        }
      }
    });

    async function updateISS() {
      try {
        // Получение последних данных
        const rLatest = await fetch('/api/iss/latest');
        const jsLatest = await rLatest.json();
        
        if (jsLatest.ok && jsLatest.data) {
          const iss = jsLatest.data;
          const newLat = parseFloat(iss.latitude);
          const newLon = parseFloat(iss.longitude);
          const vel = parseFloat(iss.velocity);
          const alt = parseFloat(iss.altitude);
          const timestamp = new Date().toLocaleTimeString('ru-RU', {hour:'2-digit',minute:'2-digit'});
          
          // Обновление карточек вверху страницы
          document.getElementById('issSpeed').textContent = vel.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
          document.getElementById('issAlt').textContent = alt.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
          
          // Добавление новой точки в траекторию (максимум 20 точек)
          trajectoryPoints.push([newLat, newLon]);
          if (trajectoryPoints.length > 20) {
            trajectoryPoints.shift();
          }
          trail.setLatLngs(trajectoryPoints);
          
          // Обновление маркера
          marker.setLatLng([newLat, newLon]);
          marker.setPopupContent(`<b>МКС</b><br>Широта: ${newLat.toFixed(4)}°<br>Долгота: ${newLon.toFixed(4)}°<br>Высота: ${alt.toFixed(1)} км<br>Скорость: ${vel.toFixed(0)} км/ч`);
          map.panTo([newLat, newLon]);
          
          // Добавление данных в историю графиков (максимум 30 точек)
          timeLabels.push(timestamp);
          speedHistory.push(vel);
          altHistory.push(alt);
          
          if (timeLabels.length > 30) {
            timeLabels.shift();
            speedHistory.shift();
            altHistory.shift();
          }
          
          // Обновление графиков
          speedChart.data.labels = timeLabels;
          speedChart.data.datasets[0].data = speedHistory;
          speedChart.update();
          
          altChart.data.labels = timeLabels;
          altChart.data.datasets[0].data = altHistory;
          altChart.update();
        }
        
      } catch(e) {
        console.error('Ошибка обновления МКС:', e);
      }
    }
    
    // Первоначальная загрузка трендовых данных для инициализации графиков
    async function initializeCharts() {
      try {
        const r = await fetch('/api/iss/trend?hours=2');
        const js = await r.json();
        if (js.ok && js.data && js.data.length > 0) {
          // Заполнение начальных данных из тренда
          js.data.reverse().forEach(point => {
            const hour = point.hour.substring(11, 16); // Берем только время HH:MM
            timeLabels.push(hour);
            speedHistory.push(point.avg_velocity);
            altHistory.push(point.avg_altitude);
          });
          
          speedChart.data.labels = timeLabels;
          speedChart.data.datasets[0].data = speedHistory;
          speedChart.update();
          
          altChart.data.labels = timeLabels;
          altChart.data.datasets[0].data = altHistory;
          altChart.update();
        }
      } catch(e) {
        console.error('Ошибка инициализации графиков:', e);
      }
    }
    
    // Инициализация
    await initializeCharts();
    
    // Первое обновление через 60 секунд
    setTimeout(updateISS, 60000);
    
    // Автообновление каждые 60 секунд (1 минута)
    setInterval(updateISS, 60000);
  }

  // ====== JWST Наблюдение (верхний блок) ======
  const jwstObs = document.getElementById('jwstObservation');
  async function loadJWSTObservation() {
    try {
      const r = await fetch('/api/jwst/feed?source=jpg&perPage=1&instrument=NIRCam');
      const js = await r.json();
      if (js.items && js.items.length > 0) {
        const item = js.items[0];
        jwstObs.innerHTML = `
          <div class="row">
            <div class="col-md-5">
              <img src="${item.url}" class="img-fluid rounded" alt="JWST">
            </div>
            <div class="col-md-7">
              <h6 class="mt-2">Последнее изображение NIRCam</h6>
              <p class="small text-muted">${item.caption || 'Изображение телескопа Джеймса Уэбба'}</p>
              <div class="small">
                <strong>Источник:</strong> ${js.source || 'JWST Archive'}<br>
                <strong>Инструмент:</strong> NIRCam<br>
                <a href="${item.link || item.url}" target="_blank" class="btn btn-sm btn-outline-primary mt-2">
                  <i class="bi bi-box-arrow-up-right me-1"></i>Открыть в полном размере
                </a>
              </div>
            </div>
          </div>
        `;
      } else {
        jwstObs.innerHTML = '<div class="text-muted">Нет доступных изображений JWST</div>';
      }
    } catch(e) {
      jwstObs.innerHTML = '<div class="text-danger">Ошибка загрузки данных JWST</div>';
    }
  }
  loadJWSTObservation();

  // ====== JWST ГАЛЕРЕЯ ======
  const track = document.getElementById('jwstTrack');
  const info  = document.getElementById('jwstInfo');
  const form  = document.getElementById('jwstFilter');
  const srcSel = document.getElementById('srcSel');
  const sfxInp = document.getElementById('suffixInp');
  const progInp= document.getElementById('progInp');

  function toggleInputs(){
    sfxInp.style.display  = (srcSel.value==='suffix')  ? '' : 'none';
    progInp.style.display = (srcSel.value==='program') ? '' : 'none';
  }
  srcSel.addEventListener('change', toggleInputs); toggleInputs();

  async function loadFeed(qs){
    track.innerHTML = '<div class="p-3 text-muted">Загрузка…</div>';
    info.textContent= '';
    try{
      const url = '/api/jwst/feed?'+new URLSearchParams(qs).toString();
      const r = await fetch(url);
      const js = await r.json();
      track.innerHTML = '';
      (js.items||[]).forEach(it=>{
        const fig = document.createElement('figure');
        fig.className = 'jwst-item m-0';
        fig.innerHTML = `
          <a href="${it.link||it.url}" target="_blank" rel="noreferrer">
            <img loading="lazy" src="${it.url}" alt="JWST">
          </a>
          <figcaption class="jwst-cap">${(it.caption||'').replaceAll('<','&lt;')}</figcaption>`;
        track.appendChild(fig);
      });
      info.textContent = `Источник: ${js.source} · Показано ${js.count||0}`;
    }catch(e){
      track.innerHTML = '<div class="p-3 text-danger">Ошибка загрузки</div>';
    }
  }

  form.addEventListener('submit', function(ev){
    ev.preventDefault();
    const fd = new FormData(form);
    const q = Object.fromEntries(fd.entries());
    loadFeed(q);
  });

  // навигация
  document.querySelector('.jwst-prev').addEventListener('click', ()=> track.scrollBy({left:-600, behavior:'smooth'}));
  document.querySelector('.jwst-next').addEventListener('click', ()=> track.scrollBy({left: 600, behavior:'smooth'}));

  // стартовые данные
  loadFeed({source:'jpg', perPage:24});
});
</script>
@endsection

    <!-- ASTRO — события -->
    <div class="col-12 order-first mt-3">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="card-title m-0">Астрономические события (AstronomyAPI)</h5>
            <form id="astroForm" class="row g-2 align-items-center">
              <div class="col-auto">
                <input type="number" step="0.0001" class="form-control form-control-sm" name="lat" value="55.7558" placeholder="lat">
              </div>
              <div class="col-auto">
                <input type="number" step="0.0001" class="form-control form-control-sm" name="lon" value="37.6176" placeholder="lon">
              </div>
              <div class="col-auto">
                <input type="number" min="1" max="365" class="form-control form-control-sm" name="days" value="365" style="width:80px" title="дней">
              </div>
              <div class="col-auto">
                <input type="number" min="1" max="50" class="form-control form-control-sm" name="limit" value="5" style="width:70px" title="лимит">
              </div>
              <div class="col-auto">
                <button class="btn btn-sm btn-primary" type="submit">Показать</button>
              </div>
            </form>
          </div>

          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead>
                <tr><th>#</th><th>Тело</th><th>Событие</th><th>Когда (UTC)</th><th>Дополнительно</th></tr>
              </thead>
              <tbody id="astroBody">
                <tr><td colspan="5" class="text-muted">нет данных</td></tr>
              </tbody>
            </table>
          </div>

          <details class="mt-2">
            <summary>Полный JSON</summary>
            <pre id="astroRaw" class="bg-light rounded p-2 small m-0" style="white-space:pre-wrap"></pre>
          </details>
        </div>
      </div>
    </div>

    <script>
      document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('astroForm');
        const body = document.getElementById('astroBody');
        const raw  = document.getElementById('astroRaw');

        function formatDate(dateStr) {
          if (!dateStr) return '—';
          try {
            const d = new Date(dateStr);
            return d.toLocaleString('ru-RU', {
              year: 'numeric',
              month: '2-digit',
              day: '2-digit',
              hour: '2-digit',
              minute: '2-digit',
              timeZone: 'UTC',
              timeZoneName: 'short'
            });
          } catch {
            return dateStr;
          }
        }

        function parseEvents(data) {
          const events = [];
          
          if (data && data.data && data.data.rows) {
            data.data.rows.forEach(row => {
              const bodyName = row.body?.name || row.body?.id || 'Unknown';
              
              if (row.events && Array.isArray(row.events)) {
                row.events.forEach(event => {
                  let eventDate = event.date || event.time;
                  
                  if (!eventDate && event.eventHighlights?.peak?.date) {
                    eventDate = event.eventHighlights.peak.date;
                  }
                  
                  let extra = '';
                  if (event.extraInfo && event.extraInfo.obscuration !== undefined) {
                    // Obscuration в процентах (0.8 -> 80%)
                    extra = `Покрытие: ${Math.round(event.extraInfo.obscuration * 100)}%`;
                  } else if (event.altitude !== undefined) {
                    extra = `Alt: ${event.altitude}°`;
                  } else if (event.magnitude !== undefined) {
                    extra = `Mag: ${event.magnitude}`;
                  } else if (event.note) {
                    extra = event.note;
                  } else if (event.eventHighlights) {
                    const peakAlt = event.eventHighlights.peak?.altitude;
                    if (peakAlt !== undefined) {
                      extra = `Высота пика: ${peakAlt.toFixed(1)}°`;
                    }
                  }
                  
                  events.push({
                    body: bodyName,
                    type: event.type || event.event_type || '—',
                    date: eventDate || '—',
                    extra: extra
                  });
                });
              }
            });
          }
          
          return events;
        }

        async function load(q){
          body.innerHTML = '<tr><td colspan="5" class="text-muted">Загрузка…</td></tr>';
          const url = '/api/astro/events?' + new URLSearchParams(q).toString();
          const limit = parseInt(q.limit) || 5;
          try{
            const r  = await fetch(url);
            const js = await r.json();
            raw.textContent = JSON.stringify(js, null, 2);

            const events = parseEvents(js);
            
            if (!events.length) {
              body.innerHTML = '<tr><td colspan="5" class="text-muted">события не найдены</td></tr>';
              return;
            }
            
            body.innerHTML = events.slice(0, limit).map((evt, i) => `
              <tr>
                <td>${i + 1}</td>
                <td><strong>${evt.body}</strong></td>
                <td>${evt.type.replace(/_/g, ' ')}</td>
                <td><code class="small">${formatDate(evt.date)}</code></td>
                <td><span class="small text-muted">${evt.extra}</span></td>
              </tr>
            `).join('');
            
            if (events.length > limit) {
              body.innerHTML += `<tr><td colspan="5" class="text-center text-muted small">
                Показано ${limit} из ${events.length} событий. Увеличьте лимит для просмотра остальных.
              </td></tr>`;
            }
          }catch(e){
            console.error('AstronomyAPI error:', e);
            body.innerHTML = '<tr><td colspan="5" class="text-danger">Ошибка загрузки: ' + e.message + '</td></tr>';
          }
        }

        form.addEventListener('submit', ev=>{
          ev.preventDefault();
          const q = Object.fromEntries(new FormData(form).entries());
          load(q);
        });

        // автозагрузка
        load({lat: form.lat.value, lon: form.lon.value, days: form.days.value, limit: form.limit.value});
      });
    </script>
    </script>


{{-- ===== Данный блок ===== --}}
<div class="card mt-3">
  <div class="card-header fw-semibold">CMS</div>
  <div class="card-body">
    @php
      try {
        // «плохо»: запрос из Blade, без кэша, без репозитория
        $___b = DB::selectOne("SELECT content FROM cms_blocks WHERE slug='dashboard_experiment' AND is_active = TRUE LIMIT 1");
        echo $___b ? $___b->content : '<div class="text-muted">блок не найден</div>';
      } catch (\Throwable $e) {
        echo '<div class="text-danger">ошибка БД: '.e($e->getMessage()).'</div>';
      }
    @endphp
  </div>
</div>

{{-- ===== CMS-блок из БД (нарочно сырая вставка) ===== --}}
<div class="card mt-3">
  <div class="card-header fw-semibold">CMS — блок из БД</div>
  <div class="card-body">
    @php
      try {
        // «плохо»: запрос из Blade, без кэша, без репозитория
        $___b = DB::selectOne("SELECT content FROM cms_blocks WHERE slug='dashboard_experiment' AND is_active = TRUE LIMIT 1");
        echo $___b ? $___b->content : '<div class="text-muted">блок не найден</div>';
      } catch (\Throwable $e) {
        echo '<div class="text-danger">ошибка БД: '.e($e->getMessage()).'</div>';
      }
    @endphp
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  if (window.L && window._issMapTileLayer) {
    const map  = window._issMap;
    let   tl   = window._issMapTileLayer;
    tl.on('tileerror', () => {
      try {
        map.removeLayer(tl);
      } catch(e) {}
      tl = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {attribution: ''});
      tl.addTo(map);
      window._issMapTileLayer = tl;
    });
  }
});
</script>

{{-- ===== CMS-блок из БД (нарочно сырая вставка) ===== --}}
<div class="card mt-3">
  <div class="card-header fw-semibold">CMS — блок из БД</div>
  <div class="card-body">
    @php
      try {
        // «плохо»: запрос из Blade, без кэша, без репозитория
        $___b = DB::selectOne("SELECT content FROM cms_blocks WHERE slug='dashboard_experiment' AND is_active = TRUE LIMIT 1");
        echo $___b ? $___b->content : '<div class="text-muted">блок не найден</div>';
      } catch (\Throwable $e) {
        echo '<div class="text-danger">ошибка БД: '.e($e->getMessage()).'</div>';
      }
    @endphp
  </div>
</div>
