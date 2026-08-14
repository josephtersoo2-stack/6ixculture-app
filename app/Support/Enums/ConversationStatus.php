<?php

namespace App\Support\Enums;

enum ConversationStatus: string
{
    case NEW = 'new';
    case AI_ACTIVE = 'ai_active';
    case AWAITING_CUSTOMER = 'awaiting_customer';
    case AWAITING_AGENT = 'awaiting_agent';
    case QUEUED = 'queued';
    case HUMAN_ACTIVE = 'human_active';
    case RESOLVED = 'resolved';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::NEW => 'New',
            self::AI_ACTIVE => 'AI Active',
            self::AWAITING_CUSTOMER => 'Awaiting Customer',
            self::AWAITING_AGENT => 'Awaiting Agent',
            self::QUEUED => 'Queued for Human Agent',
            self::HUMAN_ACTIVE => 'Human Agent Active',
            self::RESOLVED => 'Resolved',
            self::CLOSED => 'Closed',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::RESOLVED, self::CLOSED]);
    }
}
