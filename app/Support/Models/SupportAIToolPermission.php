<?php

namespace App\Support\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportAIToolPermission extends Model
{
    use HasFactory;

    protected $table = 'support_ai_tool_permissions';

    protected $fillable = [
        'tool_id',
        'permission_name',
        'customer_scope',
        'is_enabled',
        'configuration',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'configuration' => 'array',
    ];

    public function tool(): BelongsTo
    {
        return $this->belongsTo(SupportAITool::class, 'tool_id');
    }

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }
}
