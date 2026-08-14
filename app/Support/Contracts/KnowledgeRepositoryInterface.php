<?php

namespace App\Support\Contracts;

use Illuminate\Support\Collection;

interface KnowledgeRepositoryInterface
{
    /**
     * Search published knowledge base articles by query and category.
     */
    public function search(string $query, ?string $category = null, string $language = 'en'): Collection;
}
