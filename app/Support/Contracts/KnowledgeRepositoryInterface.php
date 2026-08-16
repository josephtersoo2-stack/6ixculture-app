<?php

namespace App\Support\Contracts;

use App\Support\Models\SupportKnowledgeArticle;
use Illuminate\Support\Collection;

interface KnowledgeRepositoryInterface
{
    /**
     * Search published knowledge base articles by query and optional category.
     */
    public function search(string $query, ?string $category = null, string $language = 'en'): Collection;

    /**
     * Find a published knowledge article by slug and language with fallback.
     */
    public function findPublishedBySlug(string $slug, string $language = 'en'): ?SupportKnowledgeArticle;

    /**
     * Find relevant published articles matching a customer query with language priority.
     */
    public function findRelevantPublished(string $query, string $language = 'en', int $limit = 3): Collection;
}
