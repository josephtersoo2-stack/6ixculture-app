<template>
    <div class="internal-note-composer p-3 bg-amber-50/50 dark:bg-amber-950/20 border-t border-amber-200 dark:border-amber-900/60">
        <!-- Private Notice Banner -->
        <div class="flex items-center gap-1.5 mb-2 text-[11px] font-bold text-amber-800 dark:text-amber-300">
            <i class="lab lab-lock text-xs"></i>
            <span>Private Staff Note — Visible only to internal team members (never sent to customer)</span>
        </div>

        <form @submit.prevent="handleSaveNote" class="flex flex-col gap-2">
            <textarea
                ref="noteTextarea"
                v-model="noteContent"
                rows="3"
                maxlength="4000"
                placeholder="Type internal investigation notes, supervisor instructions, or customer history notes..."
                class="w-full p-2.5 bg-white dark:bg-gray-900 border border-amber-300 dark:border-amber-800 rounded-xl text-xs text-gray-900 dark:text-gray-100 placeholder:text-gray-400 focus:outline-none focus:border-amber-600 focus:ring-1 focus:ring-amber-500 resize-none transition-all"
            ></textarea>

            <div class="flex items-center justify-between">
                <span class="text-[10px] text-amber-700/80 dark:text-amber-400/80">
                    {{ noteContent.length }}/4000
                </span>

                <button
                    type="submit"
                    :disabled="isSaving || noteContent.trim().length === 0"
                    class="px-4 py-1.5 bg-amber-600 hover:bg-amber-700 text-white font-semibold text-xs rounded-xl transition-colors disabled:opacity-40 disabled:cursor-not-allowed shadow-xs flex items-center gap-1.5"
                >
                    <span v-if="isSaving" class="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                    <i v-else class="lab lab-check text-xs"></i>
                    <span>Save Internal Note</span>
                </button>
            </div>
        </form>
    </div>
</template>

<script>
export default {
    name: 'InternalNoteComposer',
    props: {
        isSaving: {
            type: Boolean,
            default: false,
        },
    },
    emits: ['add-note'],
    data() {
        return {
            noteContent: '',
        };
    },
    methods: {
        handleSaveNote() {
            if (!this.noteContent.trim() || this.isSaving) return;
            this.$emit('add-note', this.noteContent.trim());
            this.noteContent = '';
        },
    },
};
</script>
