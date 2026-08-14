<?php

namespace App\Support\Models;

use Illuminate\Database\Eloquent\Model;

class SupportAuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'support_audit_logs';

    protected $fillable = [
        'actor_type',
        'actor_id',
        'customer_id',
        'conversation_id',
        'ticket_id',
        'action',
        'resource_type',
        'resource_id',
        'tool_name',
        'authorization_result',
        'before_data',
        'after_data',
        'metadata',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'before_data' => 'array',
        'after_data' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Sanitizes sensitive fields like passwords, tokens, API keys before saving.
     */
    public static function log(array $attributes): self
    {
        $sensitiveKeys = ['password', 'token', 'secret', 'api_key', 'authorization', 'cookie'];

        foreach (['before_data', 'after_data', 'metadata'] as $jsonField) {
            if (!empty($attributes[$jsonField]) && is_array($attributes[$jsonField])) {
                $attributes[$jsonField] = self::sanitizePayload($attributes[$jsonField], $sensitiveKeys);
            }
        }

        if (empty($attributes['created_at'])) {
            $attributes['created_at'] = now();
        }

        if (empty($attributes['ip_address']) && request()) {
            $attributes['ip_address'] = request()->ip();
            $attributes['user_agent'] = request()->userAgent();
        }

        return self::create($attributes);
    }

    private static function sanitizePayload(array $data, array $sensitiveKeys): array
    {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $data[$k] = self::sanitizePayload($v, $sensitiveKeys);
            } else {
                foreach ($sensitiveKeys as $sensitive) {
                    if (str_contains(strtolower((string)$k), $sensitive)) {
                        $data[$k] = '[REDACTED]';
                        break;
                    }
                }
            }
        }
        return $data;
    }
}
