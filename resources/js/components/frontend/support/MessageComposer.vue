<template>
    <div class="message-composer p-3 border-t border-slate-200 bg-white">
        <!-- Voice Speaking / Playback Active Bar -->
        <div 
            v-if="voiceState === 'speaking'" 
            class="mb-2 px-3 py-1.5 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center justify-between animate-pulse"
        >
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                <span class="text-[11px] font-semibold text-emerald-800">CultureAI is speaking...</span>
            </div>
            <button 
                type="button" 
                @click="handleInterrupt"
                class="px-2 py-0.5 bg-white border border-emerald-300 text-emerald-700 text-[10px] font-bold rounded-md hover:bg-emerald-100 transition-colors"
            >
                Stop Audio
            </button>
        </div>

        <!-- Live Audio Recording Active State -->
        <div v-if="voiceState === 'recording'" class="flex items-center gap-2">
            <div class="flex-1 px-3 py-2 bg-rose-50 border border-rose-200 rounded-xl flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-rose-600 animate-ping"></span>
                    <span class="text-xs font-semibold text-rose-800">Listening... {{ recordingSeconds }}s</span>
                </div>
                <div class="flex items-center gap-1">
                    <span class="w-1 h-3 bg-rose-400 rounded-full animate-bounce"></span>
                    <span class="w-1 h-5 bg-rose-500 rounded-full animate-bounce [animation-delay:0.1s]"></span>
                    <span class="w-1 h-4 bg-rose-400 rounded-full animate-bounce [animation-delay:0.2s]"></span>
                </div>
            </div>

            <!-- Cancel Recording -->
            <button
                type="button"
                @click="cancelRecording"
                class="p-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl transition-colors text-xs font-medium"
                title="Cancel"
            >
                Cancel
            </button>

            <!-- Send Voice Recording -->
            <button
                type="button"
                @click="stopRecording"
                class="p-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl transition-colors shadow-xs"
                title="Finish & Send Audio"
            >
                <i class="lab lab-send text-sm"></i>
            </button>
        </div>

        <!-- Normal Text Input Bar -->
        <form v-else @submit.prevent="handleSend" class="flex items-end gap-2">
            <div class="flex-1 relative">
                <textarea
                    ref="textarea"
                    v-model="inputContent"
                    @keydown.enter.exact.prevent="handleSend"
                    :placeholder="placeholderText"
                    :disabled="isSending || voiceState === 'processing'"
                    rows="1"
                    maxlength="1000"
                    class="w-full pl-3 pr-8 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 placeholder:text-slate-400 focus:outline-none focus:border-slate-900 focus:ring-1 focus:ring-slate-900 resize-none max-h-24 transition-all disabled:opacity-50"
                    @input="autoResize"
                ></textarea>
                <span v-if="inputContent.length > 800" class="absolute right-2 bottom-1.5 text-[10px] text-slate-400">
                    {{ 1000 - inputContent.length }}
                </span>
            </div>

            <!-- Voice Record Trigger Button -->
            <button
                v-if="!inputContent.trim()"
                type="button"
                @click="startRecording"
                :disabled="isSending || voiceState === 'processing'"
                class="p-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl transition-colors disabled:opacity-40 flex-shrink-0 flex items-center justify-center border border-slate-200"
                title="Speak to CultureAI"
            >
                <i v-if="voiceState !== 'processing'" class="lab lab-mic text-base"></i>
                <span v-else class="w-3.5 h-3.5 border-2 border-slate-700 border-t-transparent rounded-full animate-spin"></span>
            </button>

            <!-- Send Text Message Button -->
            <button
                v-else
                type="submit"
                :disabled="!canSend"
                class="p-2.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl transition-colors disabled:opacity-40 disabled:cursor-not-allowed flex-shrink-0 flex items-center justify-center shadow-xs"
                title="Send Message"
            >
                <i v-if="!isSending" class="lab lab-send text-sm"></i>
                <span v-else class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
            </button>
        </form>
    </div>
</template>

<script>
export default {
    name: 'MessageComposer',
    props: {
        isSending: {
            type: Boolean,
            default: false,
        },
    },
    emits: ['send', 'voice-send'],
    data() {
        return {
            inputContent: '',
            mediaRecorder: null,
            audioChunks: [],
            recordingSeconds: 0,
            recordingInterval: null,
        };
    },
    computed: {
        canSend() {
            return this.inputContent.trim().length > 0 && !this.isSending;
        },
        voiceState() {
            return this.$store.getters['frontendSupport/voiceState'];
        },
        placeholderText() {
            return 'Ask about products, orders, sizing, or store policy...';
        },
    },
    methods: {
        handleSend() {
            if (!this.canSend) return;
            const text = this.inputContent.trim();
            this.inputContent = '';
            this.$emit('send', text);
            this.$nextTick(() => {
                this.autoResize();
            });
        },
        setMessage(text) {
            this.inputContent = text;
            this.$nextTick(() => {
                this.autoResize();
                if (this.$refs.textarea) {
                    this.$refs.textarea.focus();
                }
            });
        },
        autoResize() {
            const el = this.$refs.textarea;
            if (!el) return;
            el.style.height = 'auto';
            el.style.height = Math.min(el.scrollHeight, 96) + 'px';
        },
        async startRecording() {
            try {
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    alert('Microphone access is not supported in this browser.');
                    return;
                }

                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                this.audioChunks = [];
                this.mediaRecorder = new MediaRecorder(stream);

                this.mediaRecorder.ondataavailable = (event) => {
                    if (event.data && event.data.size > 0) {
                        this.audioChunks.push(event.data);
                    }
                };

                this.mediaRecorder.onstop = () => {
                    stream.getTracks().forEach((track) => track.stop());
                };

                this.mediaRecorder.start(100);
                this.recordingSeconds = 0;
                this.recordingInterval = setInterval(() => {
                    this.recordingSeconds += 1;
                    if (this.recordingSeconds >= 60) {
                        this.stopRecording();
                    }
                }, 1000);

                this.$store.dispatch('frontendSupport/startVoiceSession');
            } catch (err) {
                console.warn('Microphone permission denied or unavailable:', err);
                alert('Microphone permission is required to speak with CultureAI.');
            }
        },
        stopRecording() {
            if (this.recordingInterval) {
                clearInterval(this.recordingInterval);
                this.recordingInterval = null;
            }

            if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
                this.mediaRecorder.stop();
                setTimeout(() => {
                    const audioBlob = new Blob(this.audioChunks, { type: 'audio/webm' });
                    const audioFile = new File([audioBlob], 'voice_query.webm', { type: 'audio/webm' });
                    this.$store.dispatch('frontendSupport/sendVoiceAudio', { audioFile });
                }, 200);
            }
        },
        cancelRecording() {
            if (this.recordingInterval) {
                clearInterval(this.recordingInterval);
                this.recordingInterval = null;
            }
            if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
                this.mediaRecorder.stop();
            }
            this.audioChunks = [];
            this.$store.dispatch('frontendSupport/interruptVoice');
        },
        handleInterrupt() {
            this.$store.dispatch('frontendSupport/interruptVoice');
        },
    },
};
</script>
