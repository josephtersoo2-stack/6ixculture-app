<template>
    <LoadingComponent :props="loading" />
    <div class="mb-6">
        <h2 class="capitalize text-2xl font-bold mb-1 text-primary">My Wallet</h2>
        <p class="font-medium text-text text-sm">Manage your wallet balance and instantly fund your account using your Monnify virtual account.</p>
    </div>

    <!-- Top Summary Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- Balance Card -->
        <div class="p-6 rounded-2xl shadow-card bg-white border border-gray-100 flex items-center gap-5">
            <div class="w-14 h-14 rounded-2xl bg-primary/10 text-primary flex items-center justify-center text-2xl shrink-0">
                <i class="lab-fill-wallet"></i>
            </div>
            <div>
                <span class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Available Balance</span>
                <h3 class="text-3xl font-extrabold text-primary">{{ wallet_balance }}</h3>
            </div>
        </div>

        <!-- Monnify Status Card -->
        <div class="p-6 rounded-2xl shadow-card bg-white border border-gray-100 flex items-center gap-5 col-span-1 md:col-span-2">
            <div class="w-14 h-14 rounded-2xl bg-purple-500/10 text-purple-600 flex items-center justify-center text-2xl shrink-0">
                <i class="lab-fill-card"></i>
            </div>
            <div>
                <span class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Monnify Automatic Funding</span>
                <p class="text-xs text-gray-600 font-medium">Bank transfers to your dedicated virtual account number below are credited to your wallet in real time.</p>
            </div>
        </div>
    </div>

    <!-- Monnify Virtual Account Display Card -->
    <div class="mb-8 p-6 lg:p-8 rounded-3xl shadow-xl bg-gradient-to-br from-slate-900 via-purple-950 to-slate-900 text-white relative overflow-hidden">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-8 relative z-10">
            <div class="max-w-md">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-purple-500/20 text-purple-300 text-xs font-semibold uppercase tracking-wider mb-4 border border-purple-500/30">
                    <i class="lab-fill-wallet text-sm"></i> Monnify Reserved Account
                </div>
                <h3 class="text-2xl font-bold mb-2">Instant Bank Transfer Top-Up</h3>
                <p class="text-sm text-gray-300 leading-relaxed">
                    Transfer money from any bank app in Nigeria directly to this dedicated virtual account number. Your wallet will be credited automatically!
                </p>
            </div>

            <!-- Virtual Account Details Box (If Generated) -->
            <div v-if="virtual_account && virtual_account.account_number" class="bg-white/10 backdrop-blur-xl border border-white/20 p-6 rounded-2xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-6 min-w-[320px]">
                <div>
                    <span class="block text-xs uppercase tracking-widest text-purple-300 font-bold mb-1">{{ virtual_account.bank_name || 'Wema Bank / Monnify' }}</span>
                    <span class="block text-3xl font-mono font-extrabold tracking-wider text-white mb-1">{{ virtual_account.account_number }}</span>
                    <span class="block text-xs text-gray-300 font-medium truncate max-w-[220px]">{{ virtual_account.account_name || profile.name }}</span>
                </div>
                <button type="button" @click="copyAccountNumber" class="w-full sm:w-auto px-5 py-3 rounded-xl bg-primary hover:bg-primary/90 transition-all text-white font-bold text-sm flex items-center justify-center gap-2 shadow-lg active:scale-95">
                    <i :class="copied ? 'lab-line-check text-lg' : 'lab-line-copy text-lg'"></i>
                    <span>{{ copied ? 'Copied!' : 'Copy Number' }}</span>
                </button>
            </div>

            <!-- Generate Account Button (If Not Yet Generated) -->
            <div v-else class="bg-white/10 backdrop-blur-xl border border-white/20 p-6 rounded-2xl flex flex-col items-center justify-center text-center gap-4 min-w-[300px]">
                <p class="text-xs text-gray-300">You don't have a Monnify virtual account assigned yet.</p>
                <button type="button" @click="generateVirtualAccount" class="px-6 py-3 rounded-xl bg-primary hover:bg-primary/90 transition-all text-white font-bold text-sm flex items-center justify-center gap-2 shadow-lg active:scale-95">
                    <i class="lab-line-circle-plus text-lg"></i>
                    <span>Generate Monnify Virtual Account</span>
                </button>
            </div>
        </div>

        <!-- How it works steps -->
        <div class="mt-8 pt-6 border-t border-white/10 grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs text-gray-300">
            <div class="flex items-center gap-2.5">
                <span class="w-6 h-6 rounded-full bg-purple-500/30 text-purple-300 font-bold flex items-center justify-center text-xs">1</span>
                <span>Open your bank app</span>
            </div>
            <div class="flex items-center gap-2.5">
                <span class="w-6 h-6 rounded-full bg-purple-500/30 text-purple-300 font-bold flex items-center justify-center text-xs">2</span>
                <span>Transfer to <b>{{ virtual_account.bank_name || 'Wema Bank' }}</b></span>
            </div>
            <div class="flex items-center gap-2.5">
                <span class="w-6 h-6 rounded-full bg-purple-500/30 text-purple-300 font-bold flex items-center justify-center text-xs">3</span>
                <span>Wallet credited instantly</span>
            </div>
        </div>
    </div>

    <!-- Wallet Transaction History -->
    <div class="flex items-center justify-between mb-5">
        <h4 class="text-xl font-bold capitalize">Wallet History</h4>
    </div>
    <div class="rounded-2xl shadow-card bg-white mobile:mb-20">
        <div class="max-md:overflow-x-auto">
            <table class="w-full text-left text-sm capitalize">
                <thead class="font-semibold border-b-2 border-gray-200">
                    <tr>
                        <th class="p-4">Transaction No</th>
                        <th class="p-4">Type</th>
                        <th class="p-4">Payment Method</th>
                        <th class="p-4">Amount</th>
                        <th class="p-4">Date</th>
                    </tr>
                </thead>
                <tbody class="font-medium" v-if="transactions.length > 0">
                    <tr v-for="trans in transactions" :key="trans.id">
                        <td class="p-4 border-t border-gray-100 font-mono text-xs">{{ trans.transaction_no }}</td>
                        <td class="p-4 border-t border-gray-100">
                            <span class="px-2.5 py-1 rounded-full text-xs font-bold"
                                  :class="trans.sign === '+' ? 'text-green-700 bg-green-100' : 'text-red-700 bg-red-100'">
                                {{ trans.sign === '+' ? 'CREDIT (+)' : 'DEBIT (-)' }}
                            </span>
                        </td>
                        <td class="p-4 border-t border-gray-100 uppercase text-xs font-semibold">{{ trans.payment_method }}</td>
                        <td class="p-4 border-t border-gray-100 font-bold" :class="trans.sign === '+' ? 'text-green-600' : 'text-red-600'">
                            {{ trans.sign }}{{ trans.amount }}
                        </td>
                        <td class="p-4 border-t border-gray-100 text-xs text-gray-500">{{ trans.created_at }}</td>
                    </tr>
                </tbody>
                <tbody class="db-table-body" v-else>
                    <tr class="db-table-body-tr">
                        <td class="db-table-body-td text-center p-8" colspan="5">
                            <div class="max-w-[180px] mx-auto opacity-70 mb-3">
                                <i class="lab-fill-wallet text-5xl text-gray-300"></i>
                            </div>
                            <span class="block text-gray-500 font-medium">No wallet transactions found yet.</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script>
import LoadingComponent from "../../components/LoadingComponent";
import alertService from "../../../../services/alertService";

export default {
    name: "WalletComponent",
    components: { LoadingComponent },
    data() {
        return {
            loading: {
                isActive: false,
            },
            wallet_balance: "$0.00",
            virtual_account: {},
            copied: false,
            transactions: [],
        };
    },
    mounted() {
        this.fetchWalletData();
        this.fetchVirtualAccount();
    },
    computed: {
        profile: function () {
            return this.$store.getters.authInfo;
        },
    },
    methods: {
        fetchWalletData: function () {
            this.loading.isActive = true;
            this.$store.dispatch("frontendOverview/walletBalance").then((res) => {
                this.wallet_balance = res.data.data.wallet_balance;
                this.loading.isActive = false;
            }).catch(() => {
                this.loading.isActive = false;
            });
        },
        fetchVirtualAccount: function () {
            this.$store.dispatch("frontendOverview/virtualAccount").then((res) => {
                if (res.data && res.data.data) {
                    this.virtual_account = res.data.data;
                }
            }).catch();
        },
        generateVirtualAccount: function () {
            this.loading.isActive = true;
            axios.post("frontend/overview/generate-virtual-account").then((res) => {
                this.loading.isActive = false;
                if (res.data && res.data.data) {
                    this.virtual_account = res.data.data;
                    alertService.success("Monnify Virtual Account generated successfully!");
                }
            }).catch((err) => {
                this.loading.isActive = false;
                alertService.error(err.response?.data?.message || "Failed to generate account");
            });
        },
        copyAccountNumber: function () {
            if (this.virtual_account && this.virtual_account.account_number) {
                navigator.clipboard.writeText(this.virtual_account.account_number);
                this.copied = true;
                alertService.success("Virtual account number copied to clipboard!");
                setTimeout(() => {
                    this.copied = false;
                }, 3000);
            }
        },
    }
};
</script>
