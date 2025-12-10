@extends('layouts.app')

@section('content')
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">
      <i class="bi bi-database me-2"></i>NASA Open Science Data Repository
    </h3>
    <span class="badge bg-primary">{{ $total ?? count($items ?? []) }} датасетов</span>
  </div>

  {{-- Поиск и фильтры --}}
  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <form method="GET" action="/osdr" class="row g-3">
        <div class="col-md-6">
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" class="form-control" name="search" placeholder="Поиск по названию, организму..." value="{{ request('search') }}">
          </div>
        </div>
        <div class="col-md-3">
          <select name="limit" class="form-select">
            <option value="20" {{ request('limit', 20) == 20 ? 'selected' : '' }}>20 записей</option>
            <option value="50" {{ request('limit') == 50 ? 'selected' : '' }}>50 записей</option>
            <option value="100" {{ request('limit') == 100 ? 'selected' : '' }}>100 записей</option>
          </select>
        </div>
        <div class="col-md-3">
          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-funnel me-1"></i>Применить
          </button>
        </div>
      </form>
    </div>
  </div>

  {{-- Секция экспорта CSV --}}
  <div class="card shadow-sm mb-4 border-success">
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
      <span><i class="bi bi-file-earmark-spreadsheet me-2"></i>Экспорт данных в CSV</span>
      <a href="/osdr/download" class="btn btn-light btn-sm">
        <i class="bi bi-download me-1"></i>Скачать все
      </a>
    </div>
    @if(!empty($csvFiles))
    <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
      <table class="table table-hover mb-0">
        <thead class="table-light sticky-top">
          <tr>
            <th style="width:100px">Dataset ID</th>
            <th>Название</th>
            <th>Организм</th>
            <th style="width:150px">Обновлено</th>
            <th style="width:60px"></th>
          </tr>
        </thead>
        <tbody>
          @foreach($csvFiles as $csv)
          <tr>
            <td><code class="text-primary">{{ $csv['dataset_id'] ?? '—' }}</code></td>
            <td>
              <i class="bi bi-file-earmark-text text-success me-2"></i>
              <span title="{{ $csv['title'] }}">{{ Str::limit($csv['title'], 50) }}</span>
            </td>
            <td>
              @if($csv['organism'] ?? false)
                <span class="badge bg-info text-dark">{{ $csv['organism'] }}</span>
              @else
                <span class="text-muted">—</span>
              @endif
            </td>
            <td><small class="text-muted">{{ $csv['export_time'] ? \Carbon\Carbon::parse($csv['export_time'])->format('d.m.Y H:i') : '—' }}</small></td>
            <td>
              <a href="/osdr/download?id={{ $csv['id'] }}" class="btn btn-sm btn-outline-success" title="Скачать">
                <i class="bi bi-download"></i>
              </a>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @else
    <div class="card-body text-center text-muted">
      <i class="bi bi-inbox fs-3"></i>
      <p class="mb-0 mt-2">Нет доступных данных для экспорта</p>
    </div>
    @endif
  </div>

  {{-- Информация --}}
  <div class="mt-3 text-muted small">
    <i class="bi bi-info-circle me-1"></i>
    Данные из <a href="https://osdr.nasa.gov" target="_blank" rel="noopener">NASA OSDR</a>
    &middot; Всего датасетов: <strong>{{ $total ?? count($csvFiles ?? []) }}</strong>
  </div>
</div>
@endsection
