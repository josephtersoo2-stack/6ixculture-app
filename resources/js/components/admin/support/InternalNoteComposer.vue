<template>
    <div class="internal-note-composer p-3 bg-[#0E1424] border-t border-[#1F293D]">
        <div class="flex items-center gap-1.5 mb-2 text-amber-400 text-xs font-bold uppercase tracking-wider">
            <i class="lab lab-lock text-xs"></i>
            <span>Internal Staff Note — Invisible to Customer</span>
        </div>

        <form @submit.prevent="handleSave" class="flex flex-col gap-2">
            <textarea
                ref="noteTextarea"
                v-model="noteText"
                rows="3"
                maxlength="2000"
                placeholder="Type confidential internal note for staff review..."
                @keydown.enter.exact.prevent="handleSave"
                class="w-full p-3 bg-amber-950/20 border border-amber-800/40 rounded-xl text-xs text-amber-100 placeholder:text-amber-500/50 focus:outline-none focus:border-amber-500 resize-none transition-all"
            ></textarea>

            <div class="flex items-center justify-between">
                <span class="text-[10px] text-amber-500/70">
                    {{ noteText.length }}/2000 characters
                </span>

                <button
                    type="submit"
                    :disabled="isSaving || noteText.trim().length === 0"
                    class="px-4 py-1.5 bg-amber-600 hover:bg-amber-500 text-slate-950 font-bold text-xs rounded-xl transition-colors disabled:opacity-40 disabled:cursor-not-allowed shadow-xs flex items-center gap-1.5"
                >
                    <span v-if="isSaving" class="w-3 h-3 border-2 border-slate-950 border-t-transparent rounded-full animate-spin"></span>
                    <i v-else class="lab lab-lock text-xs"></i>
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
            noteText: '',
        };
    },
    methods: {
        handleSave() {
            if (!this.noteText.trim() || this.isSaving) return;
            this.$emit('add-note', this.noteText.trim());
            this.noteText = '';
        },
    },
};
</script>
