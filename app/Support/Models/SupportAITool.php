<?php

namespace App\Support\Models;

use App\Support\Enums\ToolRiskLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportAITool extends Model
{
    use HasFactory;

    protected $table = 'support_ai_tools';

    protected $fillable = [
        'key',
        'name',
        'description',
        'category',
        'risk_level',
        'input_schema',
        'output_schema',
        'is_active',
        'requires_authentication',
        'requires_confirmation',
        'requires_human',
    ];

    protected $casts = [
        'risk_level' => ToolRiskLevel::class,
        'input_schema' => 'array',
        'output_schema' => 'array',
        'is_active' => 'boolean',
        'requires_authentication' => 'boolean',
        'requires_confirmation' => 'boolean',
        'requires_human' => 'boolean',
    ];

    public function permissions(): HasMany
    {
        return $this->hasMany(SupportAIToolPermission::class, 'tool_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
