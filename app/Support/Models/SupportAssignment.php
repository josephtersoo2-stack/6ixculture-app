<?php

namespace App\Support\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportAssignment extends Model
{
    use HasFactory;

    protected $table = 'support_assignments';

    protected $fillable = [
        'conversation_id',
        'agent_id',
        'department_id',
        'assigned_by',
        'assigned_at',
        'unassigned_at',
        'reason',
        'metadata',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'unassigned_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SupportConversation::class, 'conversation_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(SupportDepartment::class, 'department_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('unassigned_at');
    }
}
