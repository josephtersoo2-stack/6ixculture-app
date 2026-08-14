<?php

namespace App\Support\Enums;

enum SupportChannel: string
{
    case WEB = 'web';
    case VOICE = 'voice';
    case WHATSAPP = 'whatsapp';
    case EMAIL = 'email';
    case PHONE = 'phone';

    public function label(): string
    {
        return match ($this) {
            self::WEB => 'Web Chat',
            self::VOICE => 'Voice Assistant',
            self::WHATSAPP => 'WhatsApp Support',
            self::EMAIL => 'Email Support',
            self::PHONE => 'Phone Support',
        };
    }
}
