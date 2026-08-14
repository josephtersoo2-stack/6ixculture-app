<?php

namespace App\Support\Enums;

enum ToolRiskLevel: string
{
    case LOW = 'low';
    case NORMAL = 'normal';
    case SENSITIVE = 'sensitive';
    case CRITICAL = 'critical';

    public function requiresConfirmation(): bool
    {
        return in_array($this, [self::SENSITIVE, self::CRITICAL]);
    }
}
