@extends('layouts.app')

@section('content')
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">
      <i class="bi bi-image me-2"></i>Astronomy Picture of the Day
    </h3>
    <span class="badge bg-primary">NASA APOD</span>
  </div>

  {{-- Gallery --}}
  @foreach($items as $item)
  <div class="card shadow-sm mb-4">
    @if(($item['media_type'] ?? 'image') === 'image')
      <img src="{{ $item['url'] ?? $item['hdurl'] ?? '' }}" class="card-img-top" alt="{{ $item['title'] ?? 'APOD' }}" style="max-height: 600px; object-fit: cover;">
    @elseif(($item['media_type'] ?? '') === 'video')
      <div class="ratio ratio-16x9">
        <iframe src="{{ $item['url'] ?? '' }}" allowfullscreen></iframe>
      </div>
    @endif
    
    <div class="card-body">
      <h4 class="card-title">{{ $item['title'] ?? 'Unknown Title' }}</h4>
      <p class="text-muted">
        <i class="bi bi-calendar me-1"></i>{{ $item['date'] ?? 'Unknown Date' }}
        @if(isset($item['copyright']))
          <span class="ms-3"><i class="bi bi-camera me-1"></i>{{ $item['copyright'] }}</span>
        @endif
      </p>
      <p class="card-text">{{ $item['explanation'] ?? 'No description available.' }}</p>
      
      @if(isset($item['hdurl']))
        <a href="{{ $item['hdurl'] }}" target="_blank" class="btn btn-primary">
          <i class="bi bi-download me-1"></i>Download HD
        </a>
      @endif
    </div>
  </div>
  @endforeach

  {{-- Load More --}}
  <div class="text-center mt-4">
    <a href="/apod?count=10" class="btn btn-outline-primary">
      <i class="bi bi-images me-1"></i>Load Random 10 Images
    </a>
  </div>
</div>
@endsection
