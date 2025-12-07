@extends('layouts.app')

@section('content')
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">
      <i class="bi bi-sun me-2"></i>Space Weather Events
    </h3>
    <span class="badge bg-danger">{{ count($events) }} событий</span>
  </div>

  {{-- Info Alert --}}
  <div class="alert alert-warning mb-4">
    <i class="bi bi-lightning-charge me-2"></i>
    <strong>DONKI (Database Of Notifications, Knowledge, Information)</strong> - база данных космической погоды NASA, включающая солнечные вспышки, корональные выбросы массы, геомагнитные бури и другие события.
  </div>

  {{-- Filters --}}
  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <form method="GET" action="/donki" class="row g-3">
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

  {{-- Events Timeline --}}
  <div class="row">
    @forelse($events as $event)
    <div class="col-md-6 mb-4">
      <div class="card shadow-sm h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            @php
              $class = $event['classType'] ?? 'Unknown';
              $severity = 'secondary';
              if (str_starts_with($class, 'X')) $severity = 'danger';
              elseif (str_starts_with($class, 'M')) $severity = 'warning';
              elseif (str_starts_with($class, 'C')) $severity = 'info';
            @endphp
            <span class="badge bg-{{ $severity }} me-2">{{ $class }}</span>
            <strong>Solar Flare</strong>
          </div>
          @if(isset($event['activeRegionNum']))
            <small class="text-muted">AR {{ $event['activeRegionNum'] }}</small>
          @endif
        </div>
        <div class="card-body">
          <div class="mb-3">
            <small class="text-muted d-block">
              <i class="bi bi-clock me-1"></i>Начало: {{ \Carbon\Carbon::parse($event['beginTime'] ?? '')->format('Y-m-d H:i') }}
            </small>
            <small class="text-muted d-block">
              <i class="bi bi-lightning me-1"></i>Пик: {{ \Carbon\Carbon::parse($event['peakTime'] ?? '')->format('Y-m-d H:i') }}
            </small>
            <small class="text-muted d-block">
              <i class="bi bi-check2 me-1"></i>Окончание: {{ \Carbon\Carbon::parse($event['endTime'] ?? '')->format('Y-m-d H:i') }}
            </small>
          </div>
          
          @if(isset($event['sourceLocation']))
          <div class="mt-2">
            <span class="badge bg-light text-dark">
              <i class="bi bi-geo-alt me-1"></i>Локация: {{ $event['sourceLocation'] }}
            </span>
          </div>
          @endif
        </div>
      </div>
    </div>
    @empty
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-body text-center py-5">
          <i class="bi bi-inbox display-4 text-muted d-block mb-3"></i>
          <h5 class="text-muted">Нет событий за выбранный период</h5>
          <p class="text-muted mb-0">Попробуйте изменить диапазон дат</p>
        </div>
      </div>
    </div>
    @endforelse
  </div>

  {{-- Legend --}}
  <div class="card shadow-sm mt-4">
    <div class="card-header">
      <strong><i class="bi bi-info-circle me-2"></i>Классификация солнечных вспышек</strong>
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-3">
          <span class="badge bg-danger me-2">X-класс</span>
          <small>Самые сильные вспышки, способные вызвать глобальные радио-затмения и длительные радиационные бури.</small>
        </div>
        <div class="col-md-3">
          <span class="badge bg-warning text-dark me-2">M-класс</span>
          <small>Средние вспышки, могут вызывать кратковременные радио-затмения в полярных регионах.</small>
        </div>
        <div class="col-md-3">
          <span class="badge bg-info me-2">C-класс</span>
          <small>Слабые вспышки с минимальными последствиями для Земли.</small>
        </div>
        <div class="col-md-3">
          <span class="badge bg-secondary me-2">B/A-класс</span>
          <small>Фоновые вспышки без заметных последствий.</small>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
