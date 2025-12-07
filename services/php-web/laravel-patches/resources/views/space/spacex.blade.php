@extends('layouts.app')

@section('content')
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">
      <i class="bi bi-rocket-takeoff me-2"></i>SpaceX Launches
    </h3>
    <span class="badge bg-primary">{{ count($launches) }} запусков</span>
  </div>

  {{-- Info Card --}}
  <div class="alert alert-info mb-4">
    <i class="bi bi-info-circle me-2"></i>
    История запусков ракет SpaceX, включая Falcon 9, Falcon Heavy и Starship.
  </div>

  {{-- Launches Grid --}}
  <div class="row">
    @forelse($launches as $launch)
    <div class="col-md-6 mb-4">
      <div class="card shadow-sm h-100">
        <div class="row g-0">
          @if(isset($launch['links']['patch']['small']))
          <div class="col-4">
            <img src="{{ $launch['links']['patch']['small'] }}" class="img-fluid rounded-start p-3" alt="{{ $launch['name'] ?? 'Mission Patch' }}" style="max-height: 180px; object-fit: contain;">
          </div>
          @endif
          
          <div class="{{ isset($launch['links']['patch']['small']) ? 'col-8' : 'col-12' }}">
            <div class="card-body">
              <h5 class="card-title">{{ $launch['name'] ?? 'Unknown Mission' }}</h5>
              
              <div class="mb-2">
                @if(isset($launch['success']))
                  @if($launch['success'])
                    <span class="badge bg-success">
                      <i class="bi bi-check-circle me-1"></i>Успешно
                    </span>
                  @else
                    <span class="badge bg-danger">
                      <i class="bi bi-x-circle me-1"></i>Неудача
                    </span>
                  @endif
                @else
                  <span class="badge bg-secondary">
                    <i class="bi bi-hourglass me-1"></i>Запланирован
                  </span>
                @endif
              </div>
              
              <p class="text-muted small mb-2">
                <i class="bi bi-calendar-event me-1"></i>
                {{ \Carbon\Carbon::parse($launch['date_utc'] ?? '')->format('d M Y, H:i') }} UTC
              </p>
              
              @if(isset($launch['details']) && $launch['details'])
                <p class="card-text small">{{ Str::limit($launch['details'], 120) }}</p>
              @endif
              
              @if(isset($launch['links']['webcast']))
                <a href="{{ $launch['links']['webcast'] }}" target="_blank" class="btn btn-sm btn-outline-primary">
                  <i class="bi bi-youtube me-1"></i>Видео
                </a>
              @endif
            </div>
          </div>
        </div>
      </div>
    </div>
    @empty
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-body text-center py-5">
          <i class="bi bi-rocket display-4 text-muted d-block mb-3"></i>
          <h5 class="text-muted">Нет данных о запусках</h5>
          <p class="text-muted mb-0">Попробуйте обновить страницу позже</p>
        </div>
      </div>
    </div>
    @endforelse
  </div>

  {{-- Load More --}}
  @if(count($launches) >= $limit)
  <div class="text-center mt-4">
    <a href="/spacex?limit={{ $limit + 10 }}" class="btn btn-outline-primary">
      <i class="bi bi-arrow-clockwise me-1"></i>Загрузить еще
    </a>
  </div>
  @endif

  {{-- Stats Card --}}
  <div class="card shadow-sm mt-4">
    <div class="card-body">
      <div class="row text-center">
        <div class="col-md-4">
          <h3 class="mb-0">{{ count(array_filter($launches, fn($l) => $l['success'] ?? false)) }}</h3>
          <small class="text-muted">Успешных запусков</small>
        </div>
        <div class="col-md-4">
          <h3 class="mb-0">{{ count($launches) }}</h3>
          <small class="text-muted">Всего запусков</small>
        </div>
        <div class="col-md-4">
          <h3 class="mb-0">
            {{ count($launches) > 0 ? round(count(array_filter($launches, fn($l) => $l['success'] ?? false)) / count($launches) * 100, 1) : 0 }}%
          </h3>
          <small class="text-muted">Процент успеха</small>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
