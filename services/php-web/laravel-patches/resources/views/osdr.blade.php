@extends('layouts.app')

@section('content')
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">
      <i class="bi bi-database me-2"></i>NASA Open Science Data Repository
    </h3>
    <span class="badge bg-primary">{{ count($items ?? []) }} датасетов</span>
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

  {{-- Таблица данных --}}
  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:60px">#</th>
            <th>Dataset ID</th>
            <th>Название</th>
            <th>Организм</th>
            <th>Тип исследования</th>
            <th>Обновлено</th>
            <th style="width:80px"></th>
          </tr>
        </thead>
        <tbody>
        @forelse($items as $row)
          <tr>
            <td><span class="badge bg-secondary">{{ $row['id'] }}</span></td>
            <td>
              <code class="text-primary">{{ $row['dataset_id'] ?? '—' }}</code>
            </td>
            <td style="max-width:300px">
              <div class="text-truncate" title="{{ $row['title'] ?? '' }}">
                {{ $row['title'] ?? '—' }}
              </div>
            </td>
            <td>
              @if($row['organism'] ?? false)
                <span class="badge bg-success">{{ $row['organism'] }}</span>
              @else
                <span class="text-muted">—</span>
              @endif
            </td>
            <td>
              @if($row['study_type'] ?? false)
                <span class="badge bg-info text-dark">{{ $row['study_type'] }}</span>
              @else
                <span class="text-muted">—</span>
              @endif
            </td>
            <td>
              <small class="text-muted">{{ $row['updated_at'] ?? $row['inserted_at'] ?? '—' }}</small>
            </td>
            <td>
              <button class="btn btn-sm btn-outline-secondary" 
                      data-bs-toggle="collapse" 
                      data-bs-target="#raw-{{ $row['id'] }}"
                      title="Показать JSON">
                <i class="bi bi-code-slash"></i>
              </button>
            </td>
          </tr>
          <tr class="collapse" id="raw-{{ $row['id'] }}">
            <td colspan="7" class="bg-light">
              <pre class="mb-0 p-3" style="max-height:300px;overflow:auto;font-size:0.8rem">{{ json_encode($row['raw'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="7" class="text-center py-5">
              <i class="bi bi-inbox fs-1 text-muted"></i>
              <p class="text-muted mt-2">Нет данных OSDR</p>
              <small class="text-muted">Данные синхронизируются автоматически каждые 10 минут</small>
            </td>
          </tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- Информация --}}
  <div class="mt-3 text-muted small">
    <i class="bi bi-info-circle me-1"></i>
    Данные из <a href="https://osdr.nasa.gov" target="_blank" rel="noopener">NASA OSDR</a>
    &middot; Источник: <code>{{ $src ?? 'API' }}</code>
  </div>
</div>
@endsection
