<template>
    <div class="customer-support-component space-y-8">
        <!-- 1. VIP Concierge Header Banner -->
        <div class="p-6 md:p-8 rounded-3xl bg-gradient-to-br from-slate-950 via-slate-900 to-indigo-950 text-white shadow-xl relative overflow-hidden">
            <div class="absolute -right-12 -top-12 w-64 h-64 bg-primary/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute right-1/4 -bottom-12 w-48 h-48 bg-indigo-500/10 rounded-full blur-2xl pointer-events-none"></div>

            <div class="relative z-10 flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6">
                <div>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 border border-white/20 text-emerald-400 text-xs font-bold uppercase tracking-wider mb-3">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span>24/7 Intelligent Concierge Active</span>
                    </div>
                    <h1 class="text-2xl md:text-3xl font-black tracking-tight text-white">
                        Customer Care & Shopping Assistant
                    </h1>
                    <p class="text-sm md:text-base text-slate-300 max-w-2xl mt-1 leading-relaxed">
                        Multilingual, voice-enabled VIP concierge. Track orders, explore curated products, or connect with our human specialist desk anytime.
                    </p>
                </div>

                <!-- Language Selector -->
                <div class="flex flex-wrap items-center gap-2 p-1.5 rounded-2xl bg-white/5 border border-white/10 backdrop-blur-md">
                    <button
                        v-for="lang in availableLanguages"
                        :key="lang.code"
                        @click="selectedLanguage = lang.code"
                        class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                        :class="selectedLanguage === lang.code ? 'bg-primary text-white shadow-md' : 'text-slate-300 hover:text-white hover:bg-white/10'"
                    >
                        <span>{{ lang.flag }}</span>
                        <span>{{ lang.name }}</span>
                    </button>
                </div>
            </div>

            <!-- 3 VIP Priority Contact Channels -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6 pt-6 border-t border-white/10">
                <div class="flex items-center gap-3 p-3 rounded-2xl bg-white/5 border border-white/10">
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-lg shrink-0">
                        <i class="lab lab-line-messages-2"></i>
                    </div>
                    <div>
                        <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Live AI Concierge</div>
                        <div class="text-xs font-bold text-white">Instant Response (0s wait)</div>
                    </div>
                </div>

                <div class="flex items-center gap-3 p-3 rounded-2xl bg-white/5 border border-white/10">
                    <div class="w-10 h-10 rounded-xl bg-indigo-500/20 text-indigo-400 flex items-center justify-center text-lg shrink-0">
                        <i class="lab lab-line-call-calling"></i>
                    </div>
                    <div>
                        <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">VIP Phone Priority</div>
                        <div class="text-xs font-bold text-white">+234 (800) 6IX-CULTURE</div>
                    </div>
                </div>

                <div class="flex items-center gap-3 p-3 rounded-2xl bg-white/5 border border-white/10">
                    <div class="w-10 h-10 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center text-lg shrink-0">
                        <i class="lab lab-line-mail"></i>
                    </div>
                    <div>
                        <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Priority Email Desk</div>
                        <div class="text-xs font-bold text-white">support@6ixculture.com.ng</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Assistant Modes Navigation -->
        <div class="flex flex-wrap items-center gap-3">
            <button
                v-for="mode in assistantModes"
                :key="mode.id"
                @click="activeMode = mode.id"
                class="px-5 py-3 rounded-2xl text-xs md:text-sm font-bold transition-all flex items-center gap-2.5 border"
                :class="activeMode === mode.id
                    ? 'bg-slate-900 text-white border-slate-900 shadow-md dark:bg-white dark:text-slate-950 dark:border-white'
                    : 'bg-white text-slate-600 border-gray-200 hover:border-slate-400 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-800'"
            >
                <span class="text-base">{{ mode.icon }}</span>
                <span>{{ mode.title }}</span>
            </button>
        </div>

        <!-- 3. Main Live Concierge Chat Box -->
        <div class="rounded-3xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden flex flex-col h-[620px]">
            <!-- Chat Box Header -->
            <div class="p-4 md:px-6 md:py-4 bg-slate-50 dark:bg-gray-950 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-primary to-indigo-600 flex items-center justify-center text-white font-bold text-sm shadow-xs">
                            6C
                        </div>
                        <span class="absolute bottom-0 right-0 w-3 h-3 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-gray-950"></span>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-bold text-gray-900 dark:text-white">6ixCulture Concierge</h3>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-primary/10 text-primary">
                                {{ currentModeLabel }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Speaks English, Yorùbá, Igbo & Hausa • Secure Session
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        @click="requestHumanAgent"
                        :disabled="isEscalating || isHumanAssigned"
                        class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all border flex items-center gap-1.5 shadow-2xs"
                        :class="isHumanAssigned
                            ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800'
                            : 'bg-white hover:bg-gray-50 text-gray-700 border-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700'"
                    >
                        <i class="lab lab-line-user"></i>
                        <span>{{ isHumanAssigned ? 'Human Specialist Connected' : 'Talk to Human Specialist' }}</span>
                    </button>

                    <button
                        @click="resetChat"
                        title="New Chat Session"
                        class="p-2 rounded-xl text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                    >
                        <i class="lab lab-line-reset text-base"></i>
                    </button>
                </div>
            </div>

            <!-- Chat Message Stream -->
            <div ref="chatStream" class="flex-1 overflow-y-auto p-4 md:p-6 space-y-4 bg-[#F8FAFC] dark:bg-gray-950/60 thin-scrolling">
                <!-- Welcome Greeting -->
                <div class="flex items-start gap-3 max-w-2xl">
                    <div class="w-8 h-8 rounded-full bg-slate-900 text-white flex items-center justify-center text-xs font-bold shrink-0 mt-1">
                        6C
                    </div>
                    <div class="p-4 rounded-2xl rounded-tl-none bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-2xs space-y-2 text-xs md:text-sm text-gray-800 dark:text-gray-200 leading-relaxed">
                        <p class="font-bold text-gray-900 dark:text-white">
                            {{ welcomeMessage }}
                        </p>
                        <p class="text-gray-600 dark:text-gray-400 text-xs">
                            You can ask me anything about tracking your recent order, returns, garment sizing, promotions, or speak directly to our concierge team.
                        </p>
                    </div>
                </div>

                <!-- Messages -->
                <template v-for="(msg, idx) in messages" :key="idx">
                    <!-- Customer Message (Right) -->
                    <div v-if="msg.sender_type === 'customer' || msg.sender_type === 'user'" class="flex justify-end">
                        <div class="max-w-xl p-3.5 md:p-4 rounded-2xl rounded-tr-none bg-primary text-white text-xs md:text-sm shadow-xs leading-relaxed">
                            <p class="whitespace-pre-wrap">{{ msg.content }}</p>
                            <span class="block text-[10px] text-white/70 text-right mt-1">{{ formatTime(msg.created_at) }}</span>
                        </div>
                    </div>

                    <!-- AI / Agent Message (Left) -->
                    <div v-else class="flex items-start gap-3 max-w-2xl">
                        <div
                            class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0 mt-1"
                            :class="msg.sender_type === 'agent' ? 'bg-indigo-600 text-white' : 'bg-slate-900 text-white'"
                        >
                            {{ msg.sender_type === 'agent' ? 'SP' : '6C' }}
                        </div>
                        <div class="p-4 rounded-2xl rounded-tl-none bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-2xs space-y-3 text-xs md:text-sm text-gray-800 dark:text-gray-200 leading-relaxed">
                            <div class="flex items-center justify-between gap-4 text-[11px] text-gray-400">
                                <span class="font-bold text-gray-900 dark:text-white">
                                    {{ msg.sender_type === 'agent' ? 'Human Specialist' : '6ixCulture AI' }}
                                </span>
                                <span>{{ formatTime(msg.created_at) }}</span>
                            </div>

                            <p class="whitespace-pre-wrap">{{ msg.content }}</p>

                            <!-- Embedded Order Card if payload has order data -->
                            <div v-if="msg.payload && msg.payload.order" class="p-3.5 rounded-xl bg-slate-50 dark:bg-gray-800/80 border border-gray-200 dark:border-gray-700 space-y-2">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-bold text-gray-900 dark:text-white">Order #{{ msg.payload.order.order_number }}</span>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                                        {{ msg.payload.order.status_name || 'In Transit' }}
                                    </span>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 flex items-center justify-between">
                                    <span>Courier: {{ msg.payload.order.courier || 'DHL Express' }}</span>
                                    <span>Tracking: {{ msg.payload.order.tracking_id || '6IX-892401' }}</span>
                                </div>
                                <RouterLink
                                    v-if="msg.payload.order.id"
                                    :to="{ name: 'frontend.account.orderDetails', params: { id: msg.payload.order.id } }"
                                    class="mt-2 inline-flex items-center justify-center w-full py-1.5 rounded-lg bg-primary text-white text-xs font-bold hover:bg-primary/90 transition-colors"
                                >
                                    View Full Order Details &rarr;
                                </RouterLink>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Typing / Processing Indicator -->
                <div v-if="isTyping" class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-slate-900 text-white flex items-center justify-center text-xs font-bold shrink-0">
                        6C
                    </div>
                    <div class="px-4 py-3 rounded-2xl rounded-tl-none bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-2xs">
                        <div class="flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-primary animate-bounce"></span>
                            <span class="w-2 h-2 rounded-full bg-primary animate-bounce [animation-delay:0.2s]"></span>
                            <span class="w-2 h-2 rounded-full bg-primary animate-bounce [animation-delay:0.4s]"></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Action Suggestion Chips -->
            <div class="px-4 py-2.5 bg-white dark:bg-gray-900 border-t border-gray-100 dark:border-gray-800 flex items-center gap-2 overflow-x-auto thin-scrolling">
                <button
                    v-for="(chip, cIdx) in quickChips"
                    :key="cIdx"
                    @click="sendQuickPrompt(chip.prompt)"
                    class="px-3 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap bg-slate-100 text-slate-700 hover:bg-primary hover:text-white dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-primary dark:hover:text-white transition-all shrink-0"
                >
                    {{ chip.label }}
                </button>
            </div>

            <!-- Voice Recording Status Banner -->
            <div v-if="isVoiceRecording" class="px-4 py-2 bg-rose-500 text-white text-xs font-bold flex items-center justify-between animate-pulse">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-white animate-ping"></span>
                    <span>Listening... Speak in English, Yorùbá, Igbo or Hausa</span>
                </div>
                <button @click="stopVoiceRecording" class="px-2.5 py-0.5 rounded bg-white text-rose-600 text-xs font-bold">
                    Stop
                </button>
            </div>

            <!-- Message Input Composer -->
            <div class="p-3 md:p-4 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-800">
                <form @submit.prevent="handleSendMessage" class="flex items-center gap-2">
                    <!-- Voice Input Button -->
                    <button
                        type="button"
                        @click="toggleVoiceRecording"
                        :class="isVoiceRecording ? 'bg-rose-500 text-white' : 'bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-gray-800 dark:text-gray-300'"
                        class="w-11 h-11 rounded-2xl flex items-center justify-center text-lg transition-all shrink-0"
                        title="Speak to Assistant"
                    >
                        <i class="lab lab-line-call-calling"></i>
                    </button>

                    <!-- Text Field -->
                    <div class="flex-1 relative">
                        <input
                            v-model="inputMessage"
                            type="text"
                            :placeholder="inputPlaceholder"
                            :disabled="isTyping || isVoiceRecording"
                            class="w-full h-11 px-4 pr-10 rounded-2xl bg-slate-50 dark:bg-gray-800/80 border border-gray-200 dark:border-gray-700 text-xs md:text-sm text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all"
                        />
                    </div>

                    <!-- Send Button -->
                    <button
                        type="submit"
                        :disabled="!inputMessage.trim() || isTyping"
                        class="h-11 px-5 rounded-2xl bg-primary text-white text-xs md:text-sm font-bold shadow-md hover:bg-primary/90 transition-all flex items-center justify-center gap-1.5 shrink-0 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span>Send</span>
                        <i class="lab lab-fill-send text-sm"></i>
                    </button>
                </form>
            </div>
        </div>

        <!-- 4. Support Tickets Tracker & Modal Trigger -->
        <div class="rounded-3xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 p-6 shadow-sm space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">Active Support Tickets</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Track formal tickets escalated to human specialist departments</p>
                </div>
                <button
                    @click="isTicketModalOpen = true"
                    class="px-5 py-2.5 rounded-xl bg-slate-900 text-white dark:bg-white dark:text-slate-900 text-xs font-bold shadow-sm hover:opacity-90 transition-all flex items-center gap-2 w-fit"
                >
                    <i class="lab lab-line-add-circle text-base"></i>
                    <span>Open New Support Ticket</span>
                </button>
            </div>

            <!-- Tickets List -->
            <div v-if="tickets.length > 0" class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-gray-800 text-gray-400 font-bold uppercase tracking-wider">
                            <th class="pb-3">Ticket ID</th>
                            <th class="pb-3">Subject</th>
                            <th class="pb-3">Department</th>
                            <th class="pb-3">Status</th>
                            <th class="pb-3">Created</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="t in tickets" :key="t.id" class="text-gray-700 dark:text-gray-300">
                            <td class="py-3.5 font-bold text-gray-900 dark:text-white">#{{ t.ticket_number || t.id }}</td>
                            <td class="py-3.5 font-semibold">{{ t.subject }}</td>
                            <td class="py-3.5 capitalize">{{ t.department || 'General Support' }}</td>
                            <td class="py-3.5">
                                <span
                                    class="px-2.5 py-1 rounded-full text-[10px] font-bold"
                                    :class="t.status === 'resolved' || t.status === 'closed' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300'"
                                >
                                    {{ t.status || 'In Progress' }}
                                </span>
                            </td>
                            <td class="py-3.5 text-gray-400">{{ formatTime(t.created_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else class="p-8 text-center rounded-2xl bg-gray-50 dark:bg-gray-800/50 text-xs text-gray-400 space-y-1">
                <i class="lab lab-line-ticket-discount text-2xl text-gray-300 block mb-1"></i>
                <p class="font-bold text-gray-600 dark:text-gray-300">No active support tickets</p>
                <p>All inquiries resolved seamlessly via live concierge.</p>
            </div>
        </div>

        <!-- 5. Self-Service Knowledge Base & FAQs -->
        <div class="rounded-3xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 p-6 md:p-8 shadow-sm space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">Frequently Asked Questions</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Instant answers verified against our official store policies</p>
                </div>

                <!-- FAQ Category Tabs -->
                <div class="flex items-center gap-1.5 p-1 rounded-xl bg-gray-100 dark:bg-gray-800 overflow-x-auto">
                    <button
                        v-for="cat in faqCategories"
                        :key="cat"
                        @click="selectedFaqCategory = cat"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all capitalize whitespace-nowrap"
                        :class="selectedFaqCategory === cat ? 'bg-white text-gray-900 shadow-xs dark:bg-gray-700 dark:text-white' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400'"
                    >
                        {{ cat }}
                    </button>
                </div>
            </div>

            <!-- FAQ Accordion -->
            <div class="space-y-3">
                <div
                    v-for="(faq, fIdx) in filteredFaqs"
                    :key="fIdx"
                    class="rounded-2xl border border-gray-200 dark:border-gray-800 overflow-hidden transition-all"
                >
                    <button
                        @click="toggleFaq(fIdx)"
                        class="w-full p-4 text-left font-bold text-xs md:text-sm text-gray-900 dark:text-white flex items-center justify-between gap-4 bg-gray-50/50 dark:bg-gray-800/40 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
                    >
                        <span>{{ faq.q }}</span>
                        <i class="lab lab-arrow-down text-xs transition-transform duration-300" :class="{ 'rotate-180': openFaqs.includes(fIdx) }"></i>
                    </button>
                    <div
                        v-show="openFaqs.includes(fIdx)"
                        class="p-4 bg-white dark:bg-gray-900 text-xs md:text-sm text-gray-600 dark:text-gray-400 leading-relaxed border-t border-gray-100 dark:border-gray-800"
                    >
                        {{ faq.a }}
                    </div>
                </div>
            </div>
        </div>

        <!-- 6. Create Support Ticket Modal -->
        <div v-if="isTicketModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="w-full max-w-lg rounded-3xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
                <div class="p-6 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">Open Support Ticket</h3>
                        <p class="text-xs text-gray-500">Our specialist department will review and follow up promptly</p>
                    </div>
                    <button @click="isTicketModalOpen = false" class="p-2 text-gray-400 hover:text-gray-600 rounded-xl">
                        <i class="lab lab-line-cross text-lg"></i>
                    </button>
                </div>

                <form @submit.prevent="submitTicket" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1.5">Department</label>
                        <select v-model="ticketForm.department" class="w-full h-11 px-3 rounded-xl bg-slate-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-xs font-medium text-gray-900 dark:text-white focus:outline-none focus:border-primary">
                            <option value="orders">Orders & Delivery</option>
                            <option value="returns">Returns & Refunds</option>
                            <option value="billing">Billing & Payment</option>
                            <option value="general">General Inquiries</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1.5">Subject</label>
                        <input v-model="ticketForm.subject" required type="text" placeholder="e.g. Inquiring about delivery delay on #ORD1234" class="w-full h-11 px-4 rounded-xl bg-slate-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-xs text-gray-900 dark:text-white focus:outline-none focus:border-primary" />
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1.5">Detailed Description</label>
                        <textarea v-model="ticketForm.description" required rows="4" placeholder="Please provide order number and details..." class="w-full p-4 rounded-xl bg-slate-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-xs text-gray-900 dark:text-white focus:outline-none focus:border-primary resize-none"></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button type="button" @click="isTicketModalOpen = false" class="px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 text-xs font-bold text-gray-600 hover:bg-gray-50">Cancel</button>
                        <button type="submit" :disabled="isSubmittingTicket" class="px-6 py-2.5 rounded-xl bg-primary text-white text-xs font-bold hover:bg-primary/90 shadow-md transition-all">Submit Ticket</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    name: 'CustomerSupportComponent',
    data() {
        return {
            selectedLanguage: 'en',
            availableLanguages: [
                { code: 'en', name: 'English', flag: '🇬🇧' },
                { code: 'yo', name: 'Yorùbá', flag: '🇳🇬' },
                { code: 'ig', name: 'Igbo', flag: '🇳🇬' },
                { code: 'ha', name: 'Hausa', flag: '🇳🇬' },
            ],
            activeMode: 'shopper',
            assistantModes: [
                { id: 'shopper', icon: '🛍️', title: 'Shopper Mode' },
                { id: 'account', icon: '📦', title: 'Account & Orders' },
                { id: 'support', icon: '🛡️', title: 'Support & Policies' },
            ],
            conversationPublicId: null,
            messages: [],
            inputMessage: '',
            isTyping: false,
            isVoiceRecording: false,
            isEscalating: false,
            isHumanAssigned: false,
            tickets: [],
            isTicketModalOpen: false,
            isSubmittingTicket: false,
            ticketForm: {
                department: 'orders',
                subject: '',
                description: '',
            },
            selectedFaqCategory: 'all',
            faqCategories: ['all', 'orders', 'returns', 'shipping', 'sizing'],
            openFaqs: [0],
            faqs: [
                {
                    cat: 'orders',
                    q: 'How do I track my order delivery in real time?',
                    a: 'Simply ask the concierge "Where is my order #ID" or enter your tracking ID on the Order Details page. We partner with DHL and local couriers for doorstep tracking.'
                },
                {
                    cat: 'returns',
                    q: 'What is the 6ixCulture return and exchange window?',
                    a: 'We offer a 7-day complimentary return policy on unworn garments with original tags intact. You can initiate a return directly from your customer dashboard.'
                },
                {
                    cat: 'shipping',
                    q: 'How fast is express delivery across Nigeria and international destinations?',
                    a: 'Same-day and 24-hour delivery within Lagos. Interstate delivery takes 2-3 business days. International DHL Express shipping delivers within 3-5 business days.'
                },
                {
                    cat: 'sizing',
                    q: 'How do I ensure perfect sizing for luxury streetwear cuts?',
                    a: 'Our pieces feature contemporary tailored streetwear fits. Consult our Sizing Guide or ask the AI Concierge for personalized sizing recommendations based on your height and build.'
                },
            ],
            recognition: null,
        };
    },
    computed: {
        welcomeMessage() {
            if (this.selectedLanguage === 'yo') {
                return 'Ẹ n lẹ́ o! Mo wà níbí láti ràn yín lọ́wọ́ pẹ̀lú àwọn aṣọ 6ixCulture àti àwọn àṣẹ yín.';
            }
            if (this.selectedLanguage === 'ig') {
                return 'Ndewo! Enwere m ike inyere gị aka na ngwaahịa 6ixCulture na usoro nnyefe gị.';
            }
            if (this.selectedLanguage === 'ha') {
                return 'Sannu! Zan iya taimaka muku game da kayayyakin 6ixCulture da umarnin ku.';
            }
            return 'Welcome to 6ixCulture VIP Concierge. How may I assist you with your luxury wardrobe and orders today?';
        },
        currentModeLabel() {
            const found = this.assistantModes.find(m => m.id === this.activeMode);
            return found ? found.title : 'Concierge';
        },
        inputPlaceholder() {
            if (this.activeMode === 'shopper') {
                return 'Ask about new drops, styling advice, or sizing...';
            }
            if (this.activeMode === 'account') {
                return 'Enter order #ORD... or ask for delivery status...';
            }
            return 'Ask about returns, policies, or request human support...';
        },
        quickChips() {
            if (this.activeMode === 'shopper') {
                return [
                    { label: '✨ Trending Drops', prompt: 'What are the most popular trending pieces right now?' },
                    { label: '📏 Sizing Guide', prompt: 'How does your oversized hoodie fit?' },
                    { label: '🏷️ Exclusive Offers', prompt: 'Are there any active discount coupons available?' },
                ];
            }
            if (this.activeMode === 'account') {
                return [
                    { label: '🚚 Track Latest Order', prompt: 'Can you check the live status of my recent order?' },
                    { label: '🔄 Initiate Return', prompt: 'How do I start a return request?' },
                    { label: '🧾 Download Invoice', prompt: 'Where can I download my order receipt?' },
                ];
            }
            return [
                { label: '🛡️ Return Policy', prompt: 'What is your return and exchange policy?' },
                { label: '💳 Payment Options', prompt: 'What payment methods do you accept?' },
                { label: '👤 Connect to Human Agent', prompt: 'I would like to speak with a human support agent.' },
            ];
        },
        filteredFaqs() {
            if (this.selectedFaqCategory === 'all') {
                return this.faqs;
            }
            return this.faqs.filter(f => f.cat === this.selectedFaqCategory);
        },
    },
    mounted() {
        this.initChatSession();
        this.fetchCustomerTickets();
        this.setupSpeechRecognition();
    },
    methods: {
        async initChatSession() {
            try {
                const res = await axios.post('/v1/support/conversations', {
                    channel: 'web',
                    language: this.selectedLanguage,
                });
                if (res.data && res.data.data) {
                    this.conversationPublicId = res.data.data.public_id;
                    if (res.data.data.messages) {
                        this.messages = res.data.data.messages;
                    }
                }
            } catch (err) {
                console.error('Support session init error:', err);
            }
        },
        async handleSendMessage() {
            const text = this.inputMessage.trim();
            if (!text || this.isTyping) return;

            this.messages.push({
                id: 'tmp_' + Date.now(),
                sender_type: 'customer',
                content: text,
                created_at: new Date().toISOString(),
            });
            this.inputMessage = '';
            this.isTyping = true;
            this.scrollToBottom();

            try {
                const res = await axios.post(`/v1/support/conversations/${this.conversationPublicId}/messages`, {
                    content: text,
                    language: this.selectedLanguage,
                    mode: this.activeMode,
                });

                if (res.data && res.data.data) {
                    this.messages.push(res.data.data);
                }
            } catch (err) {
                this.messages.push({
                    id: 'err_' + Date.now(),
                    sender_type: 'system',
                    content: 'We received your message. A concierge agent will respond shortly.',
                    created_at: new Date().toISOString(),
                });
            } finally {
                this.isTyping = false;
                this.scrollToBottom();
            }
        },
        sendQuickPrompt(promptText) {
            this.inputMessage = promptText;
            this.handleSendMessage();
        },
        async requestHumanAgent() {
            this.isEscalating = true;
            try {
                await axios.post(`/v1/support/conversations/${this.conversationPublicId}/escalate`, {
                    reason: 'Customer requested human assistance via concierge portal',
                });
                this.isHumanAssigned = true;
                this.messages.push({
                    id: 'sys_' + Date.now(),
                    sender_type: 'system',
                    content: 'Your request has been routed to our senior support desk. A human specialist is joining the chat.',
                    created_at: new Date().toISOString(),
                });
            } catch (err) {
                console.error('Escalation error:', err);
            } finally {
                this.isEscalating = false;
                this.scrollToBottom();
            }
        },
        resetChat() {
            this.messages = [];
            this.initChatSession();
        },
        async fetchCustomerTickets() {
            try {
                const res = await axios.get('/v1/support/tickets');
                if (res.data && res.data.data) {
                    this.tickets = res.data.data;
                }
            } catch (err) {
                // Fallback demo tickets
                this.tickets = [];
            }
        },
        async submitTicket() {
            this.isSubmittingTicket = true;
            try {
                const res = await axios.post('/v1/support/tickets', this.ticketForm);
                if (res.data && res.data.data) {
                    this.tickets.unshift(res.data.data);
                }
                this.isTicketModalOpen = false;
                this.ticketForm = { department: 'orders', subject: '', description: '' };
            } catch (err) {
                alert('Could not submit ticket: ' + (err.response?.data?.message || err.message));
            } finally {
                this.isSubmittingTicket = false;
            }
        },
        toggleFaq(idx) {
            if (this.openFaqs.includes(idx)) {
                this.openFaqs = this.openFaqs.filter(i => i !== idx);
            } else {
                this.openFaqs.push(idx);
            }
        },
        setupSpeechRecognition() {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (SpeechRecognition) {
                this.recognition = new SpeechRecognition();
                this.recognition.continuous = false;
                this.recognition.interimResults = false;
                this.recognition.onresult = (event) => {
                    const transcript = event.results[0][0].transcript;
                    this.inputMessage = transcript;
                    this.isVoiceRecording = false;
                    this.handleSendMessage();
                };
                this.recognition.onerror = () => {
                    this.isVoiceRecording = false;
                };
                this.recognition.onend = () => {
                    this.isVoiceRecording = false;
                };
            }
        },
        toggleVoiceRecording() {
            if (this.isVoiceRecording) {
                this.stopVoiceRecording();
            } else {
                this.startVoiceRecording();
            }
        },
        startVoiceRecording() {
            if (this.recognition) {
                try {
                    this.recognition.lang = this.selectedLanguage === 'yo' ? 'yo-NG' : (this.selectedLanguage === 'ha' ? 'ha-NG' : 'en-NG');
                    this.recognition.start();
                    this.isVoiceRecording = true;
                } catch (e) {
                    this.isVoiceRecording = false;
                }
            } else {
                alert('Speech recognition is not supported in this browser. Please type your message.');
            }
        },
        stopVoiceRecording() {
            if (this.recognition) {
                this.recognition.stop();
            }
            this.isVoiceRecording = false;
        },
        formatTime(isoString) {
            if (!isoString) return '';
            const d = new Date(isoString);
            return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        },
        scrollToBottom() {
            this.$nextTick(() => {
                const el = this.$refs.chatStream;
                if (el) {
                    el.scrollTop = el.scrollHeight;
                }
            });
        },
    },
};
</script>
