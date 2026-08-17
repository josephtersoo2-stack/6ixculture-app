<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 backdrop-blur-xs p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-3xl overflow-hidden flex flex-col max-h-[90vh]">
            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-slate-900"></span>
                    <h3 class="text-sm font-bold text-slate-900">
                        {{ isEdit ? `Edit Article (v${form.version})` : 'Create Knowledge Article' }}
                    </h3>
                </div>
                <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600 text-base">
                    &times;
                </button>
            </div>

            <!-- Modal Form Body -->
            <div class="p-6 overflow-y-auto flex-1 space-y-4">
                <!-- Title & Slug -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Title *</label>
                        <input
                            type="text"
                            v-model="form.title"
                            placeholder="e.g. Return Policy for Nigeria & Africa"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:outline-none focus:border-slate-900"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Slug (URL key)</label>
                        <input
                            type="text"
                            v-model="form.slug"
                            placeholder="e.g. returns-and-refunds-policy"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-mono focus:outline-none focus:border-slate-900"
                        />
                    </div>
                </div>

                <!-- Category & Language -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Category *</label>
                        <select
                            v-model="form.category"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:outline-none focus:border-slate-900"
                        >
                            <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Language *</label>
                        <select
                            v-model="form.language"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:outline-none focus:border-slate-900"
                        >
                            <option value="en">English (en)</option>
                            <option value="yo">Yorùbá (yo)</option>
                            <option value="ig">Igbo (ig)</option>
                            <option value="ha">Hausa (ha)</option>
                        </select>
                    </div>
                </div>

                <!-- Status Select -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Publication State</label>
                    <div class="flex items-center gap-4">
                        <label class="flex items-center gap-1.5 text-xs text-slate-700 cursor-pointer">
                            <input type="radio" value="draft" v-model="form.status" class="accent-slate-900" />
                            <span>Draft (Private / Non-grounding)</span>
                        </label>
                        <label class="flex items-center gap-1.5 text-xs text-slate-700 cursor-pointer">
                            <input type="radio" value="published" v-model="form.status" class="accent-slate-900" />
                            <span>Published (Live AI Grounding)</span>
                        </label>
                    </div>
                </div>

                <!-- Content Markdown/Text Area -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-xs font-bold text-slate-700">Article Content (Markdown) *</label>
                        <button
                            type="button"
                            @click="previewArticle"
                            class="text-xs text-emerald-600 hover:text-emerald-700 font-bold flex items-center gap-1"
                        >
                            <i class="lab lab-eye text-xs"></i>
                            <span>Live Preview</span>
                        </button>
                    </div>
                    <textarea
                        v-model="form.content"
                        rows="10"
                        placeholder="Write clear, authoritative policy/product facts for AI customer grounding..."
                        class="w-full p-3 bg-slate-50 border border-slate-300 rounded-xl text-xs font-mono focus:outline-none focus:border-slate-900 leading-relaxed"
                    ></textarea>
                </div>

                <!-- Preview Box if active -->
                <div v-if="previewData" class="p-4 bg-emerald-50/50 border border-emerald-200 rounded-xl space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold uppercase text-emerald-800 tracking-wider">Validation Preview</span>
                        <span :class="['text-[11px] font-bold', previewData.is_publication_ready ? 'text-emerald-700' : 'text-rose-600']">
                            {{ previewData.is_publication_ready ? '✓ Publication Ready' : '⚠ Validation Notice' }}
                        </span>
                    </div>
                    <div class="text-xs text-slate-600 font-medium">
                        Words: {{ previewData.word_count }} | Chars: {{ previewData.character_count }}
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-end gap-2 bg-slate-50">
                <button
                    type="button"
                    @click="$emit('close')"
                    class="px-4 py-2 bg-white border border-slate-300 hover:bg-slate-100 text-slate-700 rounded-xl text-xs font-bold transition-all"
                >
                    Cancel
                </button>

                <button
                    type="button"
                    @click="handleSave"
                    :disabled="isSaving || !canSave"
                    class="px-5 py-2 bg-slate-950 hover:bg-slate-800 text-white rounded-xl text-xs font-bold transition-all shadow-xs disabled:opacity-50 flex items-center gap-1.5"
                >
                    <span v-if="isSaving" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                    <span>{{ isEdit ? 'Update Article' : 'Save Article' }}</span>
                </button>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'KnowledgeArticleEditor',
    props: {
        article: {
            type: Object,
            default: null,
        },
    },
    emits: ['close', 'saved'],
    data() {
        return {
            form: {
                id: this.article?.id || null,
                title: this.article?.title || '',
                slug: this.article?.slug || '',
                category: this.article?.category || 'Returns',
                language: this.article?.language || 'en',
                content: this.article?.content || '',
                status: this.article?.status || 'draft',
                version: this.article?.version || 1,
            },
            previewData: null,
        };
    },
    computed: {
        isEdit() {
            return !!this.form.id;
        },
        categories() {
            return this.$store.getters['adminGovernance/categories'];
        },
        isSaving() {
            return this.$store.getters['adminGovernance/isSaving'];
        },
        canSave() {
            return this.form.title.trim().length > 0 && this.form.content.trim().length > 0;
        },
    },
    methods: {
        previewArticle() {
            this.$store.dispatch('adminGovernance/previewKnowledgeArticle', {
                title: this.form.title,
                slug: this.form.slug,
                category: this.form.category,
                language: this.form.language,
                content: this.form.content,
                article_id: this.form.id,
            }).then((res) => {
                this.previewData = res.data.data;
            });
        },
        handleSave() {
            if (!this.canSave) return;
            this.$store.dispatch('adminGovernance/saveKnowledgeArticle', this.form)
                .then(() => {
                    this.$emit('saved');
                    this.$emit('close');
                })
                .catch((err) => {
                    alert(err?.response?.data?.error?.message || 'Failed to save knowledge article.');
                });
        },
    },
};
</script>
