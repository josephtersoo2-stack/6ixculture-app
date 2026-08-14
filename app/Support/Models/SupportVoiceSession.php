<?php

namespace App\Support\Models;

use App\Models\User;
use App\Support\Enums\VoiceSessionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SupportVoiceSession extends Model
{
    use HasFactory;

    protected $table = 'support_voice_sessions';

    protected $fillable = [
        'public_id',
        'conversation_id',
        'customer_id',
        'language',
        'status',
        'started_at',
        'ended_at',
        'duration_seconds',
        'transcript_message_count',
        'provider',
        'provider_session_id',
        'audio_url',
        'metadata',
    ];

    protected $casts = [
        'status' => VoiceSessionStatus::class,
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_seconds' => 'integer',
        'transcript_message_count' => 'integer',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (SupportVoiceSession $session) {
            if (empty($session->public_id)) {
                $session->public_id = (string) Str::ulid();
            }
        });
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SupportConversation::class, 'conversation_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}
