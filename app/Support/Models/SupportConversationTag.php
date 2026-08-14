<?php

namespace App\Support\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SupportConversationTag extends Model
{
    use HasFactory;

    protected $table = 'support_conversation_tags';

    protected $fillable = [
        'name',
        'slug',
        'color',
        'type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(
            SupportConversation::class,
            'support_conversation_tag_pivot',
            'tag_id',
            'conversation_id'
        )->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
