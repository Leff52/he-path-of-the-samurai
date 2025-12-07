<?php

namespace App\DTOs;

use DateTime;

class IssDataDto
{
    public function __construct(
        public readonly int $id,
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly float $velocity,
        public readonly float $altitude,
        public readonly DateTime $fetchedAt,
        public readonly string $sourceUrl,
    ) {}
    
    public static function fromArray(array $data): self
    {
        $payload = $data['payload'] ?? [];
        
        return new self(
            id: $data['id'] ?? 0,
            latitude: (float) ($payload['latitude'] ?? 0.0),
            longitude: (float) ($payload['longitude'] ?? 0.0),
            velocity: (float) ($payload['velocity'] ?? 0.0),
            altitude: (float) ($payload['altitude'] ?? 0.0),
            fetchedAt: new DateTime($data['fetched_at'] ?? 'now'),
            sourceUrl: $data['source_url'] ?? '',
        );
    }
    
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'velocity' => $this->velocity,
            'altitude' => $this->altitude,
            'fetched_at' => $this->fetchedAt->format('Y-m-d H:i:s'),
            'source_url' => $this->sourceUrl,
        ];
    }
    
    public function isValid(): bool
    {
        return abs($this->latitude) <= 90.0 
            && abs($this->longitude) <= 180.0
            && $this->velocity > 0
            && $this->altitude > 0;
    }
}
