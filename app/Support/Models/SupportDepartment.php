<?php

namespace App\Support\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SupportDepartment extends Model
{
    use HasFactory;

    protected $table = 'support_departments';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function agentProfiles(): BelongsToMany
    {
        return $this->belongsToMany(
            SupportAgentProfile::class,
            'support_agent_department',
            'department_id',
            'agent_profile_id'
        )->withPivot('is_primary')->withTimestamps();
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(SupportConversation::class, 'department_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'department_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
