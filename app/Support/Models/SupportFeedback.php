<?php

namespace App\Support\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportFeedback extends Model
{
    use HasFactory;

    protected $table = 'support_feedback';

    protected $fillable = [
        'conversation_id',
        'ticket_id',
        'customer_id',
        'rating',
        'comment',
        'language',
        'metadata',
    ];

    protected $casts = [
        'rating' => 'integer',
        'metadata' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SupportConversation::class, 'conversation_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}
