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

    public const MAX_STRING_LENGTH = 1000;

    /**
     * Dedicated string redaction method that masks API keys, tokens, bearer headers,
     * passwords, and credentials embedded inside arbitrary text.
     */
    public static function sanitizeString(string $value): string
    {
        if (empty($value)) {
            return $value;
        }

        // 1. Redact Bearer tokens and JWTs
        $value = preg_replace('/Bearer\s+[A-Za-z0-9\-_=\.]+/i', 'Bearer [REDACTED]', $value);
        $value = preg_replace('/eyJ[A-Za-z0-9\-_]{10,}\.[A-Za-z0-9\-_]{10,}\.[A-Za-z0-9\-_]+/i', '[REDACTED]', $value);

        // 2. Redact OpenAI-style API keys (sk-...)
        $value = preg_replace('/sk-[A-Za-z0-9\-_]{6,}/i', '[REDACTED]', $value);

        // 3. Redact Google API keys (AIza...)
        $value = preg_replace('/AIza[0-9A-Za-z\-_]{10,}/', '[REDACTED]', $value);

        // 4. Redact key=value or key: value credential assignments
        $pattern = '/\b(api[-_]?key|token|access[-_]?token|refresh[-_]?token|secret|password|authorization|credential|cvv|pin)\s*([:=])\s*([^\s,;&"\'\(\)\{\}\[\]]+)/i';
        $value = preg_replace($pattern, '$1$2[REDACTED]', $value);

        // 5. Enforce max string length for logs & audit
        if (strlen($value) > self::MAX_STRING_LENGTH) {
            $value = mb_substr($value, 0, self::MAX_STRING_LENGTH) . '... [TRUNCATED]';
        }

        return $value;
    }

    /**
     * Recursively sanitize payload arrays, strings, and scalars for audit logging and metadata.
     */
    public static function sanitize(mixed $data): mixed
    {
        if (is_null($data) || is_bool($data) || is_int($data) || is_float($data)) {
            return $data;
        }

        if (is_string($data)) {
            return self::sanitizeString($data);
        }

        if (!is_array($data)) {
            if (is_object($data)) {
                if (method_exists($data, 'toArray')) {
                    return self::sanitize($data->toArray());
                }
                return '[OBJECT: ' . get_class($data) . ']';
            }
            return (string)$data;
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
                $sanitized[$key] = self::sanitizeString($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
