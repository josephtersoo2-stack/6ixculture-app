<?php

namespace App\Support\Models;

use App\Support\Services\AuditRedactionService;
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
        foreach (['before_data', 'after_data', 'metadata'] as $jsonField) {
            if (!empty($attributes[$jsonField])) {
                $attributes[$jsonField] = AuditRedactionService::sanitize($attributes[$jsonField]);
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
}
