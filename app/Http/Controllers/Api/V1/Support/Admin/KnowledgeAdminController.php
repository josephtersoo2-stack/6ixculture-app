<?php

namespace App\Http\Controllers\Api\V1\Support\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Models\SupportAuditLog;
use App\Support\Models\SupportKnowledgeArticle;
use App\Support\Models\SupportKnowledgeArticleVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KnowledgeAdminController extends Controller
{
    /**
     * List knowledge base articles with search, category, language, and status filters.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->authorizeGovernance($request);
        if (!$user) {
            return $this->errorForbidden();
        }

        $query = SupportKnowledgeArticle::with(['creator:id,name,email', 'editor:id,name,email']);

        // Search filter (title, content, slug)
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        // Category filter
        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        // Language filter (en, yo, ig, ha)
        if ($request->filled('language')) {
            $query->where('language', $request->input('language'));
        }

        // Status filter (draft, published, archived)
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = max(5, min((int)$request->input('per_page', 15), 100));
        $articles = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $articles->items(),
            'meta' => [
                'current_page' => $articles->currentPage(),
                'last_page' => $articles->lastPage(),
                'per_page' => $articles->perPage(),
                'total' => $articles->total(),
            ],
            'categories' => [
                'Products', 'Shipping', 'Returns', 'Refunds', 'Payments',
                'Account', 'Orders', 'Warranty', 'Promotions', 'FAQ', 'Store Policies'
            ],
            'languages' => ['en', 'yo', 'ig', 'ha'],
        ], 200);
    }

    /**
     * Create a new draft knowledge base article.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $this->authorizeGovernance($request);
        if (!$user) {
            return $this->errorForbidden();
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'category' => 'required|string|max:50',
            'language' => 'required|string|in:en,yo,ig,ha',
            'content' => 'required|string',
            'status' => 'nullable|string|in:draft,published',
            'metadata' => 'nullable|array',
        ]);

        $slug = !empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['title']);
        $language = $validated['language'];

        // Check unique slug + language
        $exists = SupportKnowledgeArticle::where('slug', $slug)->where('language', $language)->exists();
        if ($exists) {
            return response()->json([
                'error' => [
                    'code' => 'DUPLICATE_KNOWLEDGE_SLUG',
                    'message' => "An article with slug '{$slug}' already exists for language '{$language}'.",
                ]
            ], 422);
        }

        $status = $validated['status'] ?? 'draft';

        return DB::transaction(function () use ($validated, $slug, $language, $status, $user) {
            $article = SupportKnowledgeArticle::create([
                'title' => $validated['title'],
                'slug' => $slug,
                'category' => $validated['category'],
                'language' => $language,
                'content' => $validated['content'],
                'status' => $status,
                'version' => 1,
                'published_at' => ($status === 'published') ? now() : null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'metadata' => $validated['metadata'] ?? null,
            ]);

            // Create initial version record
            SupportKnowledgeArticleVersion::create([
                'article_id' => $article->id,
                'version' => 1,
                'title' => $article->title,
                'content' => $article->content,
                'created_by' => $user->id,
            ]);

            SupportAuditLog::log([
                'actor_type' => 'admin',
                'actor_id' => $user->id,
                'action' => 'KNOWLEDGE_ARTICLE_CREATED',
                'resource_type' => 'support_knowledge_article',
                'resource_id' => (string)$article->id,
                'authorization_result' => 'ALLOWED',
                'after_data' => [
                    'title' => $article->title,
                    'slug' => $article->slug,
                    'category' => $article->category,
                    'language' => $article->language,
                    'status' => $article->status,
                    'version' => $article->version,
                ],
            ]);

            return response()->json([
                'data' => $article->load(['creator:id,name,email', 'editor:id,name,email', 'versions']),
                'message' => 'Knowledge article created successfully.',
            ], 201);
        });
    }

    /**
     * Get knowledge article details with current and historical version info.
     */
    public function show(Request $request, $id): JsonResponse
    {
        $user = $this->authorizeGovernance($request);
        if (!$user) {
            return $this->errorForbidden();
        }

        $article = SupportKnowledgeArticle::with(['creator:id,name,email', 'editor:id,name,email', 'versions.creator:id,name,email'])
            ->find($id);

        if (!$article) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Knowledge article not found.']
            ], 404);
        }

        return response()->json(['data' => $article], 200);
    }

    /**
     * Update knowledge article content and attributes.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = $this->authorizeGovernance($request);
        if (!$user) {
            return $this->errorForbidden();
        }

        $article = SupportKnowledgeArticle::find($id);
        if (!$article) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Knowledge article not found.']
            ], 404);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|nullable|string|max:255',
            'category' => 'sometimes|required|string|max:50',
            'language' => 'sometimes|required|string|in:en,yo,ig,ha',
            'content' => 'sometimes|required|string',
            'status' => 'sometimes|required|string|in:draft,published,archived',
            'metadata' => 'nullable|array',
        ]);

        $newSlug = isset($validated['slug']) ? Str::slug($validated['slug']) : (isset($validated['title']) ? Str::slug($validated['title']) : $article->slug);
        $newLang = $validated['language'] ?? $article->language;

        if ($newSlug !== $article->slug || $newLang !== $article->language) {
            $conflict = SupportKnowledgeArticle::where('slug', $newSlug)
                ->where('language', $newLang)
                ->where('id', '!=', $article->id)
                ->exists();

            if ($conflict) {
                return response()->json([
                    'error' => [
                        'code' => 'DUPLICATE_KNOWLEDGE_SLUG',
                        'message' => "Slug '{$newSlug}' already exists for language '{$newLang}'.",
                    ]
                ], 422);
            }
        }

        return DB::transaction(function () use ($article, $validated, $newSlug, $newLang, $user) {
            $beforeData = [
                'title' => $article->title,
                'content' => $article->content,
                'status' => $article->status,
                'version' => $article->version,
            ];

            $contentChanged = (isset($validated['content']) && $validated['content'] !== $article->content) ||
                              (isset($validated['title']) && $validated['title'] !== $article->title);

            $newVersion = $article->version;
            if ($contentChanged) {
                $newVersion = $article->version + 1;
            }

            $article->update([
                'title' => $validated['title'] ?? $article->title,
                'slug' => $newSlug,
                'category' => $validated['category'] ?? $article->category,
                'language' => $newLang,
                'content' => $validated['content'] ?? $article->content,
                'status' => $validated['status'] ?? $article->status,
                'version' => $newVersion,
                'updated_by' => $user->id,
                'metadata' => $validated['metadata'] ?? $article->metadata,
            ]);

            if ($contentChanged) {
                SupportKnowledgeArticleVersion::create([
                    'article_id' => $article->id,
                    'version' => $newVersion,
                    'title' => $article->title,
                    'content' => $article->content,
                    'created_by' => $user->id,
                ]);
            }

            SupportAuditLog::log([
                'actor_type' => 'admin',
                'actor_id' => $user->id,
                'action' => 'KNOWLEDGE_ARTICLE_UPDATED',
                'resource_type' => 'support_knowledge_article',
                'resource_id' => (string)$article->id,
                'authorization_result' => 'ALLOWED',
                'before_data' => $beforeData,
                'after_data' => [
                    'title' => $article->title,
                    'status' => $article->status,
                    'version' => $article->version,
                ],
            ]);

            return response()->json([
                'data' => $article->fresh(['creator:id,name,email', 'editor:id,name,email', 'versions']),
                'message' => 'Knowledge article updated successfully.',
            ], 200);
        });
    }

    /**
     * Publish a knowledge article so it becomes active in AI customer grounding.
     */
    public function publish(Request $request, $id): JsonResponse
    {
        $user = $this->authorizeGovernance($request);
        if (!$user) {
            return $this->errorForbidden();
        }

        $article = SupportKnowledgeArticle::find($id);
        if (!$article) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Knowledge article not found.']
            ], 404);
        }

        $beforeStatus = $article->status;
        $article->update([
            'status' => 'published',
            'published_at' => now(),
            'updated_by' => $user->id,
        ]);

        // Ensure current version record exists
        $versionExists = SupportKnowledgeArticleVersion::where('article_id', $article->id)
            ->where('version', $article->version)
            ->exists();

        if (!$versionExists) {
            SupportKnowledgeArticleVersion::create([
                'article_id' => $article->id,
                'version' => $article->version,
                'title' => $article->title,
                'content' => $article->content,
                'created_by' => $user->id,
            ]);
        }

        SupportAuditLog::log([
            'actor_type' => 'admin',
            'actor_id' => $user->id,
            'action' => 'KNOWLEDGE_ARTICLE_PUBLISHED',
            'resource_type' => 'support_knowledge_article',
            'resource_id' => (string)$article->id,
            'authorization_result' => 'ALLOWED',
            'before_data' => ['status' => $beforeStatus],
            'after_data' => ['status' => 'published', 'version' => $article->version],
        ]);

        return response()->json([
            'data' => $article->fresh(['creator:id,name,email', 'editor:id,name,email']),
            'message' => 'Knowledge article published. It is now eligible for AI runtime grounding.',
        ], 200);
    }

    /**
     * Archive a knowledge article (immediately excludes from AI runtime grounding).
     */
    public function archive(Request $request, $id): JsonResponse
    {
        $user = $this->authorizeGovernance($request);
        if (!$user) {
            return $this->errorForbidden();
        }

        $article = SupportKnowledgeArticle::find($id);
        if (!$article) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Knowledge article not found.']
            ], 404);
        }

        $beforeStatus = $article->status;
        $article->update([
            'status' => 'archived',
            'updated_by' => $user->id,
        ]);

        SupportAuditLog::log([
            'actor_type' => 'admin',
            'actor_id' => $user->id,
            'action' => 'KNOWLEDGE_ARTICLE_ARCHIVED',
            'resource_type' => 'support_knowledge_article',
            'resource_id' => (string)$article->id,
            'authorization_result' => 'ALLOWED',
            'before_data' => ['status' => $beforeStatus],
            'after_data' => ['status' => 'archived'],
        ]);

        return response()->json([
            'data' => $article->fresh(['creator:id,name,email', 'editor:id,name,email']),
            'message' => 'Knowledge article archived. Excluded from AI grounding.',
        ], 200);
    }

    /**
     * List historical versions for an article.
     */
    public function versions(Request $request, $id): JsonResponse
    {
        $user = $this->authorizeGovernance($request);
        if (!$user) {
            return $this->errorForbidden();
        }

        $article = SupportKnowledgeArticle::find($id);
        if (!$article) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Knowledge article not found.']
            ], 404);
        }

        $versions = SupportKnowledgeArticleVersion::with('creator:id,name,email')
            ->where('article_id', $article->id)
            ->orderBy('version', 'desc')
            ->get();

        return response()->json([
            'data' => [
                'current_version' => $article->version,
                'versions' => $versions,
            ]
        ], 200);
    }

    /**
     * Rollback non-destructively to an older version by snapshotting it as a new version.
     */
    public function rollback(Request $request, $id): JsonResponse
    {
        $user = $this->authorizeGovernance($request);
        if (!$user) {
            return $this->errorForbidden();
        }

        $validated = $request->validate([
            'target_version' => 'required|integer|min:1',
            'reason' => 'nullable|string|max:255',
        ]);

        $article = SupportKnowledgeArticle::find($id);
        if (!$article) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Knowledge article not found.']
            ], 404);
        }

        $targetVersion = SupportKnowledgeArticleVersion::where('article_id', $article->id)
            ->where('version', $validated['target_version'])
            ->first();

        if (!$targetVersion) {
            return response()->json([
                'error' => [
                    'code' => 'VERSION_NOT_FOUND',
                    'message' => "Historical version {$validated['target_version']} not found for this article.",
                ]
            ], 404);
        }

        return DB::transaction(function () use ($article, $targetVersion, $validated, $user) {
            $sourceVersion = $article->version;
            $newVersion = $article->version + 1;

            $article->update([
                'title' => $targetVersion->title,
                'content' => $targetVersion->content,
                'version' => $newVersion,
                'updated_by' => $user->id,
            ]);

            $newVersionRecord = SupportKnowledgeArticleVersion::create([
                'article_id' => $article->id,
                'version' => $newVersion,
                'title' => $targetVersion->title,
                'content' => $targetVersion->content,
                'created_by' => $user->id,
            ]);

            SupportAuditLog::log([
                'actor_type' => 'admin',
                'actor_id' => $user->id,
                'action' => 'KNOWLEDGE_ARTICLE_ROLLBACK',
                'resource_type' => 'support_knowledge_article',
                'resource_id' => (string)$article->id,
                'authorization_result' => 'ALLOWED',
                'before_data' => [
                    'version' => $sourceVersion,
                ],
                'after_data' => [
                    'restored_from_version' => $targetVersion->version,
                    'new_version' => $newVersion,
                    'reason' => $validated['reason'] ?? 'Admin rollback',
                ],
            ]);

            return response()->json([
                'data' => $article->fresh(['creator:id,name,email', 'editor:id,name,email', 'versions']),
                'message' => "Successfully rolled back from version {$sourceVersion} to content of version {$targetVersion->version}. Created new version {$newVersion}.",
            ], 200);
        });
    }

    /**
     * Preview article rendering and check validation / duplicate conflicts without executing AI turn.
     */
    public function preview(Request $request): JsonResponse
    {
        $user = $this->authorizeGovernance($request);
        if (!$user) {
            return $this->errorForbidden();
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'category' => 'required|string|max:50',
            'language' => 'required|string|in:en,yo,ig,ha',
            'content' => 'required|string',
            'article_id' => 'nullable|integer',
        ]);

        $slug = !empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['title']);
        $lang = $validated['language'];

        $conflictQuery = SupportKnowledgeArticle::where('slug', $slug)->where('language', $lang);
        if (!empty($validated['article_id'])) {
            $conflictQuery->where('id', '!=', $validated['article_id']);
        }
        $hasConflict = $conflictQuery->exists();

        $wordCount = str_word_count(strip_tags($validated['content']));
        $charCount = strlen($validated['content']);

        return response()->json([
            'data' => [
                'title' => $validated['title'],
                'slug' => $slug,
                'category' => $validated['category'],
                'language' => $lang,
                'rendered_html' => nl2br(e($validated['content'])),
                'word_count' => $wordCount,
                'character_count' => $charCount,
                'has_slug_conflict' => $hasConflict,
                'is_publication_ready' => !$hasConflict && $wordCount > 5,
            ]
        ], 200);
    }

    /**
     * Authorize that the authenticated user is an elevated administrator / governance user.
     */
    protected function authorizeGovernance(Request $request): ?User
    {
        $user = $request->user('sanctum') ?? Auth::guard('sanctum')->user() ?? Auth::user();
        if (!$user) {
            return null;
        }

        try {
            if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Admin', 'Manager'])) {
                return $user;
            }
            if (method_exists($user, 'can') && ($user->can('manage-support') || $user->can('all_support') || $user->can('support_governance'))) {
                return $user;
            }
        } catch (\Throwable $e) {}

        return null;
    }

    protected function errorForbidden(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'SUPPORT_GOVERNANCE_FORBIDDEN',
                'message' => 'You do not have administrative permissions to govern AI Support knowledge and policies.',
            ]
        ], 403);
    }
}
