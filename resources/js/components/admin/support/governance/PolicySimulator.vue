<template>
    <div class="policy-simulator space-y-6">
        <!-- Banner -->
        <div class="p-4 bg-amber-50 border border-amber-200 rounded-xl flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <span class="px-2 py-0.5 bg-amber-500 text-white font-extrabold text-[10px] rounded-md tracking-wider">
                    SIMULATION ONLY
                </span>
                <p class="text-xs font-semibold text-amber-900">
                    Test and dry-run policy evaluation on simulated tools. Zero business side effects or database changes occur.
                </p>
            </div>
        </div>

        <!-- Simulation Input Form -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 bg-slate-50 p-5 rounded-2xl border border-slate-200">
            <!-- Actor Type -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Actor Type *</label>
                <select
                    v-model="simForm.actor_type"
                    class="w-full px-3 py-2 bg-white border border-slate-300 rounded-xl text-xs focus:outline-none focus:border-slate-900"
                >
                    <option value="customer">Authenticated Customer</option>
                    <option value="guest">Guest User (Unauthenticated)</option>
                    <option value="agent">Support Agent</option>
                </select>
            </div>

            <!-- Tool to Simulate -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Tool / Action to Test *</label>
                <select
                    v-model="simForm.tool_name"
                    class="w-full px-3 py-2 bg-white border border-slate-300 rounded-xl text-xs focus:outline-none focus:border-slate-900"
                >
                    <option value="lookup_order">lookup_order (Read Order Status)</option>
                    <option value="request_refund">request_refund (Refund Application)</option>
                    <option value="cancel_order">cancel_order (Order Cancellation)</option>
                    <option value="change_address">change_address (Shipping Address Change)</option>
                    <option value="query_knowledge_base">query_knowledge_base (Read Knowledge)</option>
                </select>
            </div>

            <!-- Run Button -->
            <div class="flex items-end">
                <button
                    type="button"
                    @click="runSimulation"
                    :disabled="isLoading"
                    class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-all shadow-xs flex items-center justify-center gap-2"
                >
                    <span v-if="isLoading" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                    <i v-else class="lab lab-play text-sm"></i>
                    <span>Simulate Policy Evaluation</span>
                </button>
            </div>
        </div>

        <!-- Simulation Result Display Card -->
        <div v-if="result" class="p-6 bg-white border border-slate-200 rounded-2xl shadow-xs space-y-4 animate-in fade-in">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                    <h4 class="text-sm font-black text-slate-900">Simulation Outcome</h4>
                </div>
                <span class="px-2.5 py-0.5 bg-amber-100 text-amber-900 text-[10px] font-extrabold rounded-full">
                    {{ result.badge }}
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <!-- Policy Effect Box -->
                <div class="p-4 rounded-xl border border-slate-200 bg-slate-50 space-y-1">
                    <span class="text-[10px] uppercase font-bold text-slate-400">Resulting Policy Effect</span>
                    <div
                        :class="[
                            'text-base font-black uppercase tracking-wider',
                            result.policy_effect === 'allow' ? 'text-emerald-600' : '',
                            result.policy_effect === 'deny' ? 'text-rose-600' : '',
                            result.policy_effect === 'confirm' ? 'text-amber-600' : '',
                            result.policy_effect === 'require_human' ? 'text-purple-600' : '',
                            result.policy_effect === 'require_verification' ? 'text-blue-600' : '',
                        ]"
                    >
                        {{ result.policy_effect }}
                    </div>
                </div>

                <!-- Safeguard Checks -->
                <div class="p-4 rounded-xl border border-slate-200 bg-slate-50 space-y-1">
                    <span class="text-[10px] uppercase font-bold text-slate-400">Required Safeguards</span>
                    <div class="text-xs font-semibold text-slate-700">
                        <div v-if="result.requires_human" class="text-purple-700 font-bold">• Requires Human Escalation</div>
                        <div v-if="result.requires_confirmation" class="text-amber-700 font-bold">• Requires UI Confirmation</div>
                        <div v-if="result.requires_verification" class="text-blue-700 font-bold">• Requires Customer Login</div>
                        <div v-if="result.is_allowed" class="text-emerald-700 font-bold">• Direct Execution Permitted</div>
                    </div>
                </div>

                <!-- Tool Risk Rating -->
                <div class="p-4 rounded-xl border border-slate-200 bg-slate-50 space-y-1">
                    <span class="text-[10px] uppercase font-bold text-slate-400">Catalog Risk Rating</span>
                    <div class="text-xs font-bold uppercase text-slate-800">
                        {{ result.tool_risk_level }}
                    </div>
                </div>
            </div>

            <!-- Warnings if any -->
            <div v-if="result.warnings && result.warnings.length > 0" class="p-3 bg-rose-50 border border-rose-200 rounded-xl space-y-1">
                <span class="text-[11px] font-bold text-rose-800">Policy Invariant Notes:</span>
                <ul class="text-xs text-rose-700 list-disc list-inside">
                    <li v-for="w in result.warnings" :key="w">{{ w }}</li>
                </ul>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'PolicySimulator',
    data() {
        return {
            simForm: {
                actor_type: 'customer',
                tool_name: 'request_refund',
                arguments: {},
            },
        };
    },
    computed: {
        result() {
            return this.$store.getters['adminGovernance/simulationResult'];
        },
        isLoading() {
            return this.$store.getters['adminGovernance/isLoading'];
        },
    },
    methods: {
        runSimulation() {
            this.$store.dispatch('adminGovernance/simulatePolicy', this.simForm);
        },
    },
};
</script>
