<?php

namespace App\Support\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportKnowledgeArticle extends Model
{
    use HasFactory;

    protected $table = 'support_knowledge_articles';

    protected $fillable = [
        'title',
        'slug',
        'category',
        'language',
        'content',
        'status',
        'version',
        'published_at',
        'created_by',
        'updated_by',
        'view_count',
        'metadata',
    ];

    protected $casts = [
        'version' => 'integer',
        'view_count' => 'integer',
        'published_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(SupportKnowledgeArticleVersion::class, 'article_id')->orderBy('version', 'desc');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')->whereNotNull('published_at');
    }

    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeLanguage($query, string $language)
    {
        return $query->where('language', $language);
    }
}
