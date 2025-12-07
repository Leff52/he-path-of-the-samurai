@extends('layouts.app')

@section('content')
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">
      <i class="bi bi-rocket me-2"></i>Near Earth Objects
    </h3>
    <span class="badge bg-warning text-dark">{{ $element_count }} объектов</span>
  </div>

  {{-- Filters --}}
  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <form method="GET" action="/neo" class="row g-3">
        <div class="col-md-5">
          <label class="form-label">Дата начала</label>
          <input type="date" class="form-control" name="start_date" value="{{ $start_date }}">
        </div>
        <div class="col-md-5">
          <label class="form-label">Дата окончания</label>
          <input type="date" class="form-control" name="end_date" value="{{ $end_date }}">
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-search me-1"></i>Найти
          </button>
        </div>
      </form>
    </div>
  </div>

  {{-- Asteroids Table --}}
  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Название</th>
            <th>Диаметр (км)</th>
            <th>Скорость (км/ч)</th>
            <th>Дистанция (км)</th>
            <th>Дата сближения</th>
            <th>Опасность</th>
          </tr>
        </thead>
        <tbody>
          @forelse($asteroids as $index => $asteroid)
          <tr>
            <td><span class="badge bg-secondary">{{ $index + 1 }}</span></td>
            <td>
              <strong>{{ $asteroid['name'] ?? 'Unknown' }}</strong>
              <br>
              <small class="text-muted">ID: {{ $asteroid['id'] ?? 'N/A' }}</small>
            </td>
            <td>
              @php
                $min = $asteroid['estimated_diameter']['kilometers']['estimated_diameter_min'] ?? 0;
                $max = $asteroid['estimated_diameter']['kilometers']['estimated_diameter_max'] ?? 0;
              @endphp
              {{ number_format($min, 2) }} - {{ number_format($max, 2) }}
            </td>
            <td>
              @php
                $velocity = $asteroid['close_approach_data'][0]['relative_velocity']['kilometers_per_hour'] ?? 0;
              @endphp
              {{ number_format($velocity, 0) }}
            </td>
            <td>
              @php
                $distance = $asteroid['close_approach_data'][0]['miss_distance']['kilometers'] ?? 0;
              @endphp
              {{ number_format($distance, 0) }}
            </td>
            <td>
              <i class="bi bi-calendar-event me-1"></i>
              {{ $asteroid['close_approach_data'][0]['close_approach_date'] ?? 'Unknown' }}
            </td>
            <td>
              @if($asteroid['is_potentially_hazardous_asteroid'] ?? false)
                <span class="badge bg-danger">
                  <i class="bi bi-exclamation-triangle me-1"></i>Опасен
                </span>
              @else
                <span class="badge bg-success">
                  <i class="bi bi-check-circle me-1"></i>Безопасен
                </span>
              @endif
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="7" class="text-center py-4">
              <i class="bi bi-inbox display-4 text-muted d-block mb-2"></i>
              <p class="text-muted mb-0">Нет данных за выбранный период</p>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- Legend --}}
  <div class="alert alert-info mt-4">
    <i class="bi bi-info-circle me-2"></i>
    <strong>Потенциально опасные астероиды (PHA)</strong> - объекты размером более 140 метров, которые проходят в пределах 7.5 миллионов километров от Земли.
  </div>
</div>
@endsection
