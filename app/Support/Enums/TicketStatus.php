<?php

namespace App\Support\Enums;

enum TicketStatus: string
{
    case OPEN = 'open';
    case ASSIGNED = 'assigned';
    case IN_PROGRESS = 'in_progress';
    case WAITING_CUSTOMER = 'waiting_customer';
    case WAITING_INTERNAL = 'waiting_internal';
    case ESCALATED = 'escalated';
    case RESOLVED = 'resolved';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Open',
            self::ASSIGNED => 'Assigned',
            self::IN_PROGRESS => 'In Progress',
            self::WAITING_CUSTOMER => 'Waiting for Customer',
            self::WAITING_INTERNAL => 'Waiting Internal',
            self::ESCALATED => 'Escalated',
            self::RESOLVED => 'Resolved',
            self::CLOSED => 'Closed',
        };
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::RESOLVED, self::CLOSED]);
    }
}
