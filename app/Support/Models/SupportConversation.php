<?php

namespace App\Support\Models;

use App\Models\User;
use App\Support\Enums\ConversationMode;
use App\Support\Enums\ConversationStatus;
use App\Support\Enums\SupportChannel;
use App\Support\Enums\SupportPriority;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class SupportConversation extends Model
{
    use HasFactory;

    protected $table = 'support_conversations';

    protected $fillable = [
        'public_id',
        'customer_id',
        'guest_session_id',
        'status',
        'mode',
        'priority',
        'language',
        'channel',
        'department_id',
        'assigned_agent_id',
        'assigned_at',
        'first_response_at',
        'resolved_at',
        'closed_at',
        'last_message_at',
        'last_customer_message_at',
        'last_agent_message_at',
        'ai_active',
        'human_requested',
        'escalation_reason',
        'ai_summary',
        'sentiment',
        'metadata',
    ];

    protected $casts = [
        'status' => ConversationStatus::class,
        'mode' => ConversationMode::class,
        'priority' => SupportPriority::class,
        'channel' => SupportChannel::class,
        'ai_active' => 'boolean',
        'human_requested' => 'boolean',
        'assigned_at' => 'datetime',
        'first_response_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'last_message_at' => 'datetime',
        'last_customer_message_at' => 'datetime',
        'last_agent_message_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (SupportConversation $conversation) {
            if (empty($conversation->public_id)) {
                $conversation->public_id = (string) Str::ulid();
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(SupportDepartment::class, 'department_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'conversation_id')->orderBy('id', 'asc');
    }

    public function customerMessages(): HasMany
    {
        return $this->messages()->where('is_internal', false);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'conversation_id');
    }

    public function ticket(): HasOne
    {
        return $this->hasOne(SupportTicket::class, 'conversation_id')->latestOfMany();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(SupportAssignment::class, 'conversation_id')->orderBy('id', 'desc');
    }

    public function voiceSessions(): HasMany
    {
        return $this->hasMany(SupportVoiceSession::class, 'conversation_id');
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(SupportFeedback::class, 'conversation_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            SupportConversationTag::class,
            'support_conversation_tag_pivot',
            'conversation_id',
            'tag_id'
        )->withTimestamps();
    }

    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeForAgent($query, int $agentId)
    {
        return $query->where('assigned_agent_id', $agentId);
    }

    public function scopeForDepartment($query, int $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    public function scopeNeedsHumanAgent($query)
    {
        return $query->where('human_requested', true)
                     ->whereIn('status', [ConversationStatus::QUEUED, ConversationStatus::AWAITING_AGENT]);
    }

    public function scopeOpen($query)
    {
        return $query->whereNotIn('status', [ConversationStatus::RESOLVED, ConversationStatus::CLOSED]);
    }
}
