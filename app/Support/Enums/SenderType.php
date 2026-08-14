<?php

namespace App\Support\Enums;

enum SenderType: string
{
    case CUSTOMER = 'customer';
    case AI = 'ai';
    case AGENT = 'agent';
    case SYSTEM = 'system';

    public function label(): string
    {
        return match ($this) {
            self::CUSTOMER => 'Customer',
            self::AI => 'AI Assistant',
            self::AGENT => 'Human Support Agent',
            self::SYSTEM => 'System Event',
        };
    }
}
