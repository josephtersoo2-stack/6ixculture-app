<?php

namespace App\Support\Enums;

enum PolicyEffect: string
{
    case ALLOW = 'allow';
    case DENY = 'deny';
    case CONFIRM = 'confirm';
    case REQUIRE_VERIFICATION = 'require_verification';
    case REQUIRE_HUMAN = 'require_human';

    public function label(): string
    {
        return match ($this) {
            self::ALLOW => 'Allow Execution',
            self::DENY => 'Deny Execution',
            self::CONFIRM => 'Require Customer Confirmation',
            self::REQUIRE_VERIFICATION => 'Require Identity Verification',
            self::REQUIRE_HUMAN => 'Require Human Staff Approval',
        };
    }
}
