<?php

namespace App\Support\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportCustomerPreference extends Model
{
    use HasFactory;

    protected $table = 'support_customer_preferences';

    protected $fillable = [
        'user_id',
        'preferred_language',
        'preferred_voice',
        'preferred_speaking_rate',
        'metadata',
    ];

    protected $casts = [
        'preferred_speaking_rate' => 'float',
        'metadata' => 'array',
    ];

    /**
     * Customer user relation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
