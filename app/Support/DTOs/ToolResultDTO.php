<?php

namespace App\Support\DTOs;

class ToolResultDTO
{
    public function __construct(
        public string $toolCallId,
        public string $toolName,
        public bool $success,
        public array $data = [],
        public ?string $errorMessage = null,
        public ?array $structuredPayload = null,
    ) {}

    public static function success(string $toolCallId, string $toolName, array $data, ?array $structuredPayload = null): self
    {
        return new self(
            toolCallId: $toolCallId,
            toolName: $toolName,
            success: true,
            data: $data,
            structuredPayload: $structuredPayload,
        );
    }

    public static function error(string $toolCallId, string $toolName, string $message): self
    {
        return new self(
            toolCallId: $toolCallId,
            toolName: $toolName,
            success: false,
            errorMessage: $message,
        );
    }
}
