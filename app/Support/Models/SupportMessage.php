<?php

namespace App\Support\Models;

use App\Models\User;
use App\Support\Enums\MessageType;
use App\Support\Enums\SenderType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportMessage extends Model
{
    use HasFactory;

    protected $table = 'support_messages';

    protected $fillable = [
        'conversation_id',
        'sender_type',
        'sender_id',
        'message_type',
        'content',
        'structured_payload',
        'language',
        'is_internal',
        'is_read',
        'tool_call_id',
        'reply_to_id',
        'tokens_used',
        'latency_ms',
        'metadata',
    ];

    protected $casts = [
        'sender_type' => SenderType::class,
        'message_type' => MessageType::class,
        'structured_payload' => 'array',
        'is_internal' => 'boolean',
        'is_read' => 'boolean',
        'tokens_used' => 'integer',
        'latency_ms' => 'integer',
        'metadata' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SupportConversation::class, 'conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(SupportMessage::class, 'reply_to_id');
    }

    /**
     * Scope for customer-facing queries.
     * Ensures internal staff notes are never exposed to customers.
     */
    public function scopeCustomerVisible($query)
    {
        return $query->where('is_internal', false);
    }

    /**
     * Scope for staff-only internal notes.
     */
    public function scopeInternalOnly($query)
    {
        return $query->where('is_internal', true);
    }
}
