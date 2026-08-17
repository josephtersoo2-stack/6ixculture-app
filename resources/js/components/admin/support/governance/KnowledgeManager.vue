<template>
    <div class="knowledge-manager space-y-4">
        <!-- Action & Filter Bar -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 bg-slate-50 p-4 rounded-xl border border-slate-200">
            <div class="flex flex-wrap items-center gap-2 flex-1">
                <!-- Search Input -->
                <div class="relative flex-1 min-w-[200px] max-w-xs">
                    <input
                        type="text"
                        v-model="filters.search"
                        @input="debounceSearch"
                        placeholder="Search knowledge..."
                        class="w-full pl-8 pr-3 py-1.5 bg-white border border-slate-300 rounded-lg text-xs focus:outline-none focus:border-slate-900"
                    />
                    <i class="lab lab-search absolute left-2.5 top-2 text-slate-400 text-xs"></i>
                </div>

                <!-- Category Filter -->
                <select
                    v-model="filters.category"
                    @change="loadArticles"
                    class="px-2.5 py-1.5 bg-white border border-slate-300 rounded-lg text-xs focus:outline-none focus:border-slate-900"
                >
                    <option value="">All Categories</option>
                    <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                </select>

                <!-- Language Filter -->
                <select
                    v-model="filters.language"
                    @change="loadArticles"
                    class="px-2.5 py-1.5 bg-white border border-slate-300 rounded-lg text-xs focus:outline-none focus:border-slate-900"
                >
                    <option value="">All Languages</option>
                    <option value="en">English (en)</option>
                    <option value="yo">Yorùbá (yo)</option>
                    <option value="ig">Igbo (ig)</option>
                    <option value="ha">Hausa (ha)</option>
                </select>

                <!-- Status Filter -->
                <select
                    v-model="filters.status"
                    @change="loadArticles"
                    class="px-2.5 py-1.5 bg-white border border-slate-300 rounded-lg text-xs focus:outline-none focus:border-slate-900"
                >
                    <option value="">All Statuses</option>
                    <option value="published">Published</option>
                    <option value="draft">Draft</option>
                    <option value="archived">Archived</option>
                </select>
            </div>

            <!-- New Draft Button -->
            <button
                type="button"
                @click="openEditor(null)"
                class="px-4 py-2 bg-slate-950 hover:bg-slate-800 text-white rounded-xl text-xs font-bold transition-all shadow-xs flex items-center justify-center gap-1.5"
            >
                <i class="lab lab-add text-sm"></i>
                <span>Create Draft Article</span>
            </button>
        </div>

        <!-- Articles Table -->
        <div class="overflow-x-auto border border-slate-200 rounded-xl">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-100/75 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider text-[10px]">
                    <tr>
                        <th class="px-4 py-3">Title / Slug</th>
                        <th class="px-4 py-3">Category</th>
                        <th class="px-4 py-3">Language</th>
                        <th class="px-4 py-3">Version</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Last Updated</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <tr v-if="isLoading" class="text-center py-8">
                        <td colspan="7" class="py-8 text-slate-400">Loading articles...</td>
                    </tr>
                    <tr v-else-if="articles.length === 0" class="text-center py-8">
                        <td colspan="7" class="py-8 text-slate-400">No knowledge articles found.</td>
                    </tr>
                    <tr
                        v-for="article in articles"
                        :key="article.id"
                        class="hover:bg-slate-50/75 transition-colors"
                    >
                        <td class="px-4 py-3">
                            <div class="font-bold text-slate-900">{{ article.title }}</div>
                            <div class="text-[10px] text-slate-400 font-mono">{{ article.slug }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 bg-slate-100 text-slate-700 rounded-md font-semibold text-[11px]">
                                {{ article.category }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 bg-slate-100 text-slate-700 rounded-md uppercase font-mono font-bold text-[10px]">
                                {{ article.language }}
                            </span>
                        </td>
                        <td class="px-4 py-3 font-mono font-bold text-slate-600">
                            v{{ article.version }}
                        </td>
                        <td class="px-4 py-3">
                            <span
                                :class="[
                                    'px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider',
                                    article.status === 'published' ? 'bg-emerald-100 text-emerald-800' : '',
                                    article.status === 'draft' ? 'bg-amber-100 text-amber-800' : '',
                                    article.status === 'archived' ? 'bg-slate-200 text-slate-600' : '',
                                ]"
                            >
                                {{ article.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-[11px] text-slate-500">
                            {{ formatDate(article.updated_at) }}
                        </td>
                        <td class="px-4 py-3 text-right space-x-1">
                            <!-- Edit -->
                            <button
                                type="button"
                                @click="openEditor(article)"
                                class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-md text-[11px] font-medium"
                                title="Edit"
                            >
                                Edit
                            </button>

                            <!-- Version History -->
                            <button
                                type="button"
                                @click="openVersions(article)"
                                class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-md text-[11px] font-medium"
                                title="Version History"
                            >
                                History
                            </button>

                            <!-- Publish -->
                            <button
                                v-if="article.status !== 'published'"
                                type="button"
                                @click="publish(article)"
                                class="px-2 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 rounded-md text-[11px] font-bold"
                                title="Publish"
                            >
                                Publish
                            </button>

                            <!-- Archive -->
                            <button
                                v-if="article.status !== 'archived'"
                                type="button"
                                @click="archive(article)"
                                class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-md text-[11px] font-medium"
                                title="Archive"
                            >
                                Archive
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Modals -->
        <KnowledgeArticleEditor
            v-if="isEditorOpen"
            :article="selectedArticle"
            @close="isEditorOpen = false"
            @saved="loadArticles"
        />

        <KnowledgeVersionHistory
            v-if="isVersionsOpen"
            :article="selectedArticle"
            @close="isVersionsOpen = false"
            @rolled-back="loadArticles"
        />
    </div>
</template>

<script>
import KnowledgeArticleEditor from './KnowledgeArticleEditor.vue';
import KnowledgeVersionHistory from './KnowledgeVersionHistory.vue';

export default {
    name: 'KnowledgeManager',
    components: {
        KnowledgeArticleEditor,
        KnowledgeVersionHistory,
    },
    data() {
        return {
            filters: {
                search: '',
                category: '',
                language: '',
                status: '',
            },
            searchTimeout: null,
            isEditorOpen: false,
            isVersionsOpen: false,
            selectedArticle: null,
        };
    },
    computed: {
        articles() {
            return this.$store.getters['adminGovernance/articles'];
        },
        categories() {
            return this.$store.getters['adminGovernance/categories'];
        },
        isLoading() {
            return this.$store.getters['adminGovernance/isLoading'];
        },
    },
    mounted() {
        this.loadArticles();
    },
    methods: {
        loadArticles() {
            this.$store.dispatch('adminGovernance/fetchKnowledgeArticles', this.filters);
        },
        debounceSearch() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.loadArticles();
            }, 300);
        },
        openEditor(article) {
            this.selectedArticle = article;
            this.isEditorOpen = true;
        },
        openVersions(article) {
            this.selectedArticle = article;
            this.isVersionsOpen = true;
        },
        publish(article) {
            if (confirm(`Publish '${article.title}'? This will make it immediately active for AI grounding.`)) {
                this.$store.dispatch('adminGovernance/publishKnowledgeArticle', article.id);
            }
        },
        archive(article) {
            if (confirm(`Archive '${article.title}'? It will be immediately excluded from AI grounding.`)) {
                this.$store.dispatch('adminGovernance/archiveKnowledgeArticle', article.id);
            }
        },
        formatDate(dateStr) {
            if (!dateStr) return '—';
            return new Date(dateStr).toLocaleDateString('en-GB', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
            });
        },
    },
};
</script>
