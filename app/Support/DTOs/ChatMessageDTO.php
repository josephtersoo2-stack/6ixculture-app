<?php

namespace App\Support\DTOs;

use App\Support\Enums\MessageType;
use App\Support\Enums\SenderType;

class ChatMessageDTO
{
    public function __construct(
        public SenderType $senderType,
        public MessageType $messageType,
        public ?string $content = null,
        public ?array $structuredPayload = null,
        public ?int $senderId = null,
        public bool $isInternal = false,
        public ?string $language = 'en',
        public ?string $toolCallId = null,
        public ?int $replyToId = null,
        public ?array $metadata = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            senderType: is_string($data['sender_type']) ? SenderType::from($data['sender_type']) : $data['sender_type'],
            messageType: is_string($data['message_type']) ? MessageType::from($data['message_type']) : $data['message_type'],
            content: $data['content'] ?? null,
            structuredPayload: $data['structured_payload'] ?? null,
            senderId: $data['sender_id'] ?? null,
            isInternal: (bool) ($data['is_internal'] ?? false),
            language: $data['language'] ?? 'en',
            toolCallId: $data['tool_call_id'] ?? null,
            replyToId: $data['reply_to_id'] ?? null,
            metadata: $data['metadata'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'sender_type' => $this->senderType->value,
            'message_type' => $this->messageType->value,
            'content' => $this->content,
            'structured_payload' => $this->structuredPayload,
            'sender_id' => $this->senderId,
            'is_internal' => $this->isInternal,
            'language' => $this->language,
            'tool_call_id' => $this->toolCallId,
            'reply_to_id' => $this->replyToId,
            'metadata' => $this->metadata,
        ];
    }
}
