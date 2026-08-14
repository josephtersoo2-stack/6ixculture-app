<?php

namespace App\Support\Enums;

enum VoiceSessionStatus: string
{
    case STARTING = 'starting';
    case ACTIVE = 'active';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    public function isFinished(): bool
    {
        return in_array($this, [self::COMPLETED, self::FAILED, self::CANCELLED]);
    }
}
