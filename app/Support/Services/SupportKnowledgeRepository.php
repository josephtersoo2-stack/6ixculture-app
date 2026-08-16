<?php

namespace App\Support\Services;

use App\Support\Contracts\KnowledgeRepositoryInterface;
use App\Support\Models\SupportKnowledgeArticle;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SupportKnowledgeRepository implements KnowledgeRepositoryInterface
{
    /**
     * Search published knowledge base articles by query, category, and language.
     */
    public function search(string $query, ?string $category = null, string $language = 'en'): Collection
    {
        $normalizedQuery = trim($query);
        $lang = strtolower(trim($language)) ?: 'en';

        // 1. Try requested language first
        $results = $this->queryPublished($normalizedQuery, $category, $lang);

        // 2. If no results and requested language wasn't English, fallback to English
        if ($results->isEmpty() && $lang !== 'en') {
            $results = $this->queryPublished($normalizedQuery, $category, 'en');
        }

        return $results;
    }

    /**
     * Find a published knowledge article by slug and language with English fallback.
     */
    public function findPublishedBySlug(string $slug, string $language = 'en'): ?SupportKnowledgeArticle
    {
        $lang = strtolower(trim($language)) ?: 'en';

        // Try requested language
        $article = SupportKnowledgeArticle::published()
            ->where('slug', $slug)
            ->where('language', $lang)
            ->first();

        if ($article) {
            return $article;
        }

        // Fallback to English if not requested in English
        if ($lang !== 'en') {
            return SupportKnowledgeArticle::published()
                ->where('slug', $slug)
                ->where('language', 'en')
                ->first();
        }

        return null;
    }

    /**
     * Find top relevant published articles matching a customer query with language priority.
     */
    public function findRelevantPublished(string $query, string $language = 'en', int $limit = 3): Collection
    {
        $limit = max(1, min($limit, 5)); // Bound between 1 and 5
        $results = $this->search($query, null, $language);

        return $results->take($limit);
    }

    /**
     * Execute deterministic database query against published articles only.
     */
    protected function queryPublished(string $query, ?string $category, string $language): Collection
    {
        $builder = SupportKnowledgeArticle::published()
            ->where('language', $language);

        if (!empty($category)) {
            $builder->where('category', $category);
        }

        if (!empty($query)) {
            $terms = array_filter(explode(' ', Str::lower($query)), fn($t) => strlen($t) >= 2);
            $slugQuery = Str::slug($query);

            $builder->where(function ($q) use ($query, $terms, $slugQuery) {
                // Exact full-phrase match
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('content', 'like', "%{$query}%")
                  ->orWhere('category', 'like', "%{$query}%")
                  ->orWhere('slug', 'like', "%{$query}%")
                  ->orWhere('slug', 'like', "%{$slugQuery}%");

                // Individual keyword terms match
                foreach ($terms as $term) {
                    $q->orWhere('title', 'like', "%{$term}%")
                      ->orWhere('content', 'like', "%{$term}%")
                      ->orWhere('slug', 'like', "%{$term}%");
                }
            });
        }

        $articles = $builder->get();

        // Deterministic relevance scoring
        return $articles->sortByDesc(function ($article) use ($query) {
            $score = 0;
            $lowerQuery = Str::lower($query);
            $slugQuery = Str::slug($query);
            $lowerTitle = Str::lower($article->title);
            $lowerContent = Str::lower($article->content);
            $lowerCategory = Str::lower($article->category);

            if ($lowerTitle === $lowerQuery) {
                $score += 100;
            } elseif (str_contains($lowerTitle, $lowerQuery)) {
                $score += 50;
            }

            if (!empty($slugQuery) && str_contains($article->slug, $slugQuery)) {
                $score += 40;
            }

            if (str_contains($lowerCategory, $lowerQuery)) {
                $score += 30;
            }

            if (str_contains($lowerContent, $lowerQuery)) {
                $score += 10;
            }

            return $score;
        })->values();
    }
}
