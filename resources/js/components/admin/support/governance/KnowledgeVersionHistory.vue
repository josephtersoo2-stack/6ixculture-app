<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 backdrop-blur-xs p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-2xl overflow-hidden flex flex-col max-h-[85vh]">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-slate-900"></span>
                    <h3 class="text-sm font-bold text-slate-900">
                        Version History: {{ article?.title }} (Current: v{{ article?.version }})
                    </h3>
                </div>
                <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600 text-base">
                    &times;
                </button>
            </div>

            <!-- Version List Body -->
            <div class="p-6 overflow-y-auto flex-1 space-y-4">
                <div v-if="isLoading" class="text-center py-6 text-xs text-slate-400">Loading version history...</div>
                <div v-else-if="versions.length === 0" class="text-center py-6 text-xs text-slate-400">No version history records found.</div>
                <div v-else class="space-y-3">
                    <div
                        v-for="ver in versions"
                        :key="ver.id"
                        class="p-4 rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-slate-50 transition-colors"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 bg-slate-900 text-white rounded-md text-[10px] font-mono font-bold">
                                    v{{ ver.version }}
                                </span>
                                <span class="text-xs font-bold text-slate-900">{{ ver.title }}</span>
                                <span v-if="ver.version === article?.version" class="px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded-md text-[10px] font-bold">
                                    Current
                                </span>
                            </div>

                            <!-- Rollback Action -->
                            <button
                                v-if="ver.version !== article?.version"
                                type="button"
                                @click="handleRollback(ver)"
                                class="px-2.5 py-1 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-[11px] font-bold transition-all"
                            >
                                Rollback to v{{ ver.version }}
                            </button>
                        </div>

                        <div class="mt-2 text-xs text-slate-600 font-mono bg-white p-3 rounded-lg border border-slate-200 max-h-32 overflow-y-auto whitespace-pre-wrap">
                            {{ ver.content }}
                        </div>

                        <div class="mt-2 flex items-center justify-between text-[10px] text-slate-400">
                            <span>Author: {{ ver.creator?.name || 'Admin' }}</span>
                            <span>Created: {{ formatDate(ver.created_at) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-end bg-slate-50">
                <button
                    type="button"
                    @click="$emit('close')"
                    class="px-4 py-2 bg-white border border-slate-300 hover:bg-slate-100 text-slate-700 rounded-xl text-xs font-bold transition-all"
                >
                    Close
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    name: 'KnowledgeVersionHistory',
    props: {
        article: {
            type: Object,
            required: true,
        },
    },
    emits: ['close', 'rolled-back'],
    data() {
        return {
            versions: [],
            isLoading: false,
        };
    },
    mounted() {
        this.fetchVersions();
    },
    methods: {
        fetchVersions() {
            this.isLoading = true;
            axios.get(`/api/v1/support/admin/knowledge/${this.article.id}/versions`)
                .then((res) => {
                    this.versions = res.data.data?.versions || [];
                    this.isLoading = false;
                }).catch(() => {
                    this.isLoading = false;
                });
        },
        handleRollback(targetVersion) {
            const confirmed = confirm(
                `Are you sure you want to rollback '${this.article.title}' to version ${targetVersion.version}? This will create a new version with the restored content without destroying history.`
            );
            if (!confirmed) return;

            this.$store.dispatch('adminGovernance/rollbackKnowledgeArticle', {
                id: this.article.id,
                target_version: targetVersion.version,
                reason: `Admin restored content from version ${targetVersion.version}`,
            }).then(() => {
                this.$emit('rolled-back');
                this.$emit('close');
            }).catch((err) => {
                alert(err?.response?.data?.error?.message || 'Failed to rollback version.');
            });
        },
        formatDate(dateStr) {
            if (!dateStr) return '—';
            return new Date(dateStr).toLocaleString('en-GB', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        },
    },
};
</script>
