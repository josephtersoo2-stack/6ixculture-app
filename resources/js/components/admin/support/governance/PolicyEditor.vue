<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 backdrop-blur-xs p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-xl overflow-hidden flex flex-col max-h-[90vh]">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-slate-900"></span>
                    <h3 class="text-sm font-bold text-slate-900">
                        {{ isEdit ? `Edit Policy: ${form.name}` : 'Create Support Action Policy (Draft)' }}
                    </h3>
                </div>
                <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600 text-base">
                    &times;
                </button>
            </div>

            <!-- Body Form -->
            <div class="p-6 overflow-y-auto flex-1 space-y-4">
                <div v-if="!isEdit" class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-900">
                    <strong>Policy Governance Invariant:</strong> New policies are created in <strong>INACTIVE / DRAFT</strong> state. Once created and reviewed, you may explicitly activate the policy from the Action Policies list.
                </div>

                <!-- Name & Key -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Policy Name *</label>
                        <input
                            type="text"
                            v-model="form.name"
                            placeholder="e.g. Refund Requires Human Approval"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:outline-none focus:border-slate-900"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Policy Key (Unique Slug) *</label>
                        <input
                            type="text"
                            v-model="form.key"
                            :disabled="isEdit"
                            placeholder="e.g. refund_requires_approval"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-mono focus:outline-none focus:border-slate-900 disabled:opacity-50"
                        />
                    </div>
                </div>

                <!-- Category & Effect -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Category *</label>
                        <select
                            v-model="form.category"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:outline-none focus:border-slate-900"
                        >
                            <option value="orders">Orders</option>
                            <option value="returns">Returns</option>
                            <option value="payments">Payments</option>
                            <option value="security">Security</option>
                            <option value="general">General</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Policy Effect *</label>
                        <select
                            v-model="form.effect"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:outline-none focus:border-slate-900"
                        >
                            <option value="allow">ALLOW (Execute tool directly)</option>
                            <option value="deny">DENY (Block tool access)</option>
                            <option value="confirm">CONFIRM (Require customer UI confirmation)</option>
                            <option value="require_human">REQUIRE_HUMAN (Escalate to support agent)</option>
                            <option value="require_verification">REQUIRE_VERIFICATION (Authentication required)</option>
                        </select>
                    </div>
                </div>

                <!-- Priority -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Evaluation Priority (Higher = First)</label>
                    <input
                        type="number"
                        v-model.number="form.priority"
                        class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-mono focus:outline-none focus:border-slate-900"
                    />
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Description</label>
                    <textarea
                        v-model="form.description"
                        rows="3"
                        placeholder="Describe the governance reason for this policy..."
                        class="w-full p-3 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:outline-none focus:border-slate-900"
                    ></textarea>
                </div>
            </div>

            <!-- Footer -->
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
                    <span>{{ isEdit ? 'Update Policy' : 'Save Draft Policy' }}</span>
                </button>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'PolicyEditor',
    props: {
        policy: {
            type: Object,
            default: null,
        },
    },
    emits: ['close', 'saved'],
    data() {
        return {
            form: {
                id: this.policy?.id || null,
                name: this.policy?.name || '',
                key: this.policy?.key || '',
                category: this.policy?.category || 'orders',
                effect: this.policy?.effect?.value || this.policy?.effect || 'confirm',
                priority: this.policy?.priority || 0,
                description: this.policy?.description || '',
            },
        };
    },
    computed: {
        isEdit() {
            return !!this.form.id;
        },
        isSaving() {
            return this.$store.getters['adminGovernance/isSaving'];
        },
        canSave() {
            return this.form.name.trim().length > 0 && (this.isEdit || this.form.key.trim().length > 0);
        },
    },
    methods: {
        handleSave() {
            if (!this.canSave) return;
            this.$store.dispatch('adminGovernance/savePolicy', this.form)
                .then(() => {
                    this.$emit('saved');
                    this.$emit('close');
                })
                .catch((err) => {
                    alert(err?.response?.data?.error?.message || 'Failed to save policy.');
                });
        },
    },
};
</script>
