<?php

namespace App\Support\Enums;

enum ConversationMode: string
{
    case AI = 'ai';
    case HUMAN = 'human';
    case HYBRID = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::AI => 'AI Automated',
            self::HUMAN => 'Human Agent',
            self::HYBRID => 'Hybrid (AI Assisted Agent)',
        };
    }
}
