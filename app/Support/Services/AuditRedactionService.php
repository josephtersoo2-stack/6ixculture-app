<?php

namespace App\Support\Services;

class AuditRedactionService
{
    public const SENSITIVE_KEYWORDS = [
        'password',
        'token',
        'access_token',
        'refresh_token',
        'api_key',
        'secret',
        'authorization',
        'cookie',
        'credential',
        'payment',
        'card',
        'cvv',
        'pin',
    ];

    public const MAX_STRING_LENGTH = 500;

    /**
     * Recursively sanitize payload arrays for audit logging and metadata.
     */
    public static function sanitize(mixed $data): mixed
    {
        if (!is_array($data)) {
            if (is_string($data) && strlen($data) > self::MAX_STRING_LENGTH) {
                return mb_substr($data, 0, self::MAX_STRING_LENGTH) . '... [TRUNCATED]';
            }
            return $data;
        }

        $sanitized = [];
        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string)$key);
            $isSensitive = false;

            foreach (self::SENSITIVE_KEYWORDS as $keyword) {
                if (str_contains($lowerKey, $keyword)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = self::sanitize($value);
            } elseif (is_string($value)) {
                if (strlen($value) > self::MAX_STRING_LENGTH) {
                    $sanitized[$key] = mb_substr($value, 0, self::MAX_STRING_LENGTH) . '... [TRUNCATED]';
                } else {
                    $sanitized[$key] = $value;
                }
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
