<?php

namespace App\Support\DTOs;

class ToolCallDTO
{
    public function __construct(
        public string $id,
        public string $name,
        public array $arguments = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? uniqid('call_'),
            name: $data['name'] ?? '',
            arguments: is_array($data['arguments'] ?? null) 
                ? $data['arguments'] 
                : (is_string($data['arguments'] ?? null) ? (json_decode($data['arguments'], true) ?: []) : []),
        );
    }
}
