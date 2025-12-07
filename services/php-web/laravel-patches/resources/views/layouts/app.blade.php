<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Cassiopeia — Space Data Platform</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    #map { height: 340px; }
    .card { border-radius: 0.75rem; }
    .card-title { color: #2c3e50; }
    .stat-card { transition: transform 0.2s; }
    .stat-card:hover { transform: translateY(-2px); }
    .nav-link.active { font-weight: 600; }
    .bg-space { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); }
    .text-space { color: #e94560; }
  </style>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg bg-space navbar-dark shadow-sm mb-4">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="/dashboard">
      <i class="bi bi-stars me-2 text-space"></i>
      <span>Cassiopeia</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item">
          <a class="nav-link {{ request()->is('dashboard') ? 'active' : '' }}" href="/dashboard">
            <i class="bi bi-speedometer2 me-1"></i> Dashboard
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link {{ request()->is('iss*') ? 'active' : '' }}" href="/iss">
            <i class="bi bi-rocket-takeoff me-1"></i> ISS
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link {{ request()->is('osdr*') ? 'active' : '' }}" href="/osdr">
            <i class="bi bi-database me-1"></i> OSDR
          </a>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
            <i class="bi bi-globe me-1"></i> Space Data
          </a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="/apod">APOD</a></li>
            <li><a class="dropdown-item" href="/neo">Near Earth Objects</a></li>
            <li><a class="dropdown-item" href="/donki">Space Weather</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="/spacex">SpaceX</a></li>
          </ul>
        </li>
      </ul>
      <ul class="navbar-nav">
        <li class="nav-item">
          <a class="nav-link" href="/health" target="_blank">
            <i class="bi bi-heart-pulse me-1"></i> Health
          </a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<main class="container-fluid px-4">
  @yield('content')
</main>

<footer class="text-center text-muted py-4 mt-5 border-top">
  <small>Cassiopeia Space Data Platform &copy; {{ date('Y') }}</small>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
