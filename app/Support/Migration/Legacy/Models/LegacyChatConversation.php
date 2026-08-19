<?php

namespace App\Support\Migration\Legacy\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegacyChatConversation extends Model
{
    protected $table = 'chat_conversations';

    protected $fillable = [
        'session_token',
        'user_id',
        'user_name',
        'user_email',
        'user_phone',
        'status',
        'ip_address',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(LegacyChatMessage::class, 'conversation_id')->orderBy('id', 'asc');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
