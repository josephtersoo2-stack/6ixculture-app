<?php

namespace App\Support\Models;

use App\Models\User;
use App\Support\Enums\PolicyEffect;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportPolicy extends Model
{
    use HasFactory;

    protected $table = 'support_policies';

    protected $fillable = [
        'key',
        'name',
        'description',
        'category',
        'effect',
        'configuration',
        'is_active',
        'priority',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'effect' => PolicyEffect::class,
        'configuration' => 'array',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('priority', 'desc')->orderBy('id', 'asc');
    }
}
