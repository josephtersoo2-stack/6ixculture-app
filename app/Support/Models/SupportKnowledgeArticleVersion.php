<?php

namespace App\Support\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportKnowledgeArticleVersion extends Model
{
    use HasFactory;

    protected $table = 'support_knowledge_article_versions';

    protected $fillable = [
        'article_id',
        'version',
        'title',
        'content',
        'created_by',
    ];

    protected $casts = [
        'version' => 'integer',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(SupportKnowledgeArticle::class, 'article_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
