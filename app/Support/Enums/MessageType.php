<?php

namespace App\Support\Enums;

enum MessageType: string
{
    case TEXT = 'text';
    case PRODUCT = 'product';
    case PRODUCT_LIST = 'product_list';
    case PRODUCT_COMPARISON = 'product_comparison';
    case ORDER = 'order';
    case ORDER_STATUS = 'order_status';
    case CART = 'cart';
    case ACTION_CONFIRMATION = 'action_confirmation';
    case SYSTEM = 'system';
    case ESCALATION = 'escalation';
    case INTERNAL_NOTE = 'internal_note';
    case VOICE_TRANSCRIPT = 'voice_transcript';
    case AUDIO = 'audio';
    case IMAGE = 'image';
    case FILE = 'file';
    case ERROR = 'error';

    public function isCustomerVisible(): bool
    {
        return $this !== self::INTERNAL_NOTE;
    }

    public function isStructured(): bool
    {
        return in_array($this, [
            self::PRODUCT,
            self::PRODUCT_LIST,
            self::PRODUCT_COMPARISON,
            self::ORDER,
            self::ORDER_STATUS,
            self::CART,
            self::ACTION_CONFIRMATION,
        ]);
    }
}
