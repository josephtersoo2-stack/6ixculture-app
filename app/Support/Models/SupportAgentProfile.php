<?php

namespace App\Support\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportAgentProfile extends Model
{
    use HasFactory;

    protected $table = 'support_agent_profiles';

    protected $fillable = [
        'user_id',
        'display_name',
        'status',
        'availability',
        'max_concurrent_conversations',
        'skills',
        'metadata',
    ];

    protected $casts = [
        'skills' => 'array',
        'metadata' => 'array',
        'max_concurrent_conversations' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(
            SupportDepartment::class,
            'support_agent_department',
            'agent_profile_id',
            'department_id'
        )->withPivot('is_primary')->withTimestamps();
    }

    public function primaryDepartment()
    {
        return $this->departments()->wherePivot('is_primary', true)->first();
    }

    public function isOnline(): bool
    {
        return $this->status === 'online' && $this->availability === 'available';
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'online')->where('availability', 'available');
    }
}
