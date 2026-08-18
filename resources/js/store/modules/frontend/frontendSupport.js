import axios from 'axios';

export const frontendSupport = {
    namespaced: true,
    state: {
        isOpen: false,
        isMinimized: false,
        conversation: null,
        conversationsList: [],
        messages: [],
        isLoading: false,
        isSending: false,
        isTyping: false,
        error: null,
        language: 'en',
        guestToken: localStorage.getItem('6ix_support_guest_token') || null,
        unreadCount: 0,
        pollingTimer: null,
        capabilities: null,
        // Voice State (Phase 6)
        voiceState: 'idle', // idle, recording, processing, speaking, interrupted, error
        voiceSessionId: null,
        voiceAudioEl: null,
        isRealtimeConnected: false,
    },
    getters: {
        isOpen: (state) => state.isOpen,
        isMinimized: (state) => state.isMinimized,
        conversation: (state) => state.conversation,
        conversationsList: (state) => state.conversationsList,
        messages: (state) => state.messages,
        isLoading: (state) => state.isLoading,
        isSending: (state) => state.isSending,
        isTyping: (state) => state.isTyping,
        error: (state) => state.error,
        language: (state) => state.language,
        unreadCount: (state) => state.unreadCount,
        voiceState: (state) => state.voiceState,
        isRealtimeConnected: (state) => state.isRealtimeConnected,
        capabilities: (state) => state.capabilities,
    },
    actions: {
        toggleWidget({ commit, state, dispatch }) {
            commit('SET_IS_OPEN', !state.isOpen);
            if (state.isOpen) {
                dispatch('fetchCapabilities');
                if (!state.conversation) {
                    dispatch('initConversation');
                }
                commit('RESET_UNREAD');
            }
        },
        closeWidget({ commit, dispatch }) {
            commit('SET_IS_OPEN', false);
            dispatch('interruptVoice');
        },
        openWidget({ commit, dispatch, state }) {
            commit('SET_IS_OPEN', true);
            commit('RESET_UNREAD');
            dispatch('fetchCapabilities');
            if (!state.conversation) {
                dispatch('initConversation');
            }
        },
        fetchCapabilities({ commit }) {
            return axios.get('/v1/support/voice/capabilities').then((res) => {
                commit('SET_CAPABILITIES', res.data.data);
                return res.data.data;
            }).catch(() => {});
        },
        setLanguage({ commit, state }, language) {
            commit('SET_LANGUAGE', language);
            if (state.conversation) {
                state.conversation.language = language;
            }
        },
        switchLanguage({ commit, state }, language) {
            commit('SET_LANGUAGE', language);
            if (state.conversation) {
                state.conversation.language = language;
                return axios.post(`/v1/support/conversations/${state.conversation.id}/language`, {
                    language: language,
                    guest_token: state.guestToken,
                }, {
                    headers: state.guestToken ? { 'X-Guest-Token': state.guestToken } : {}
                }).catch(() => {});
            }
            return Promise.resolve();
        },
        updateVoicePreferences({ commit, state }, { voice, speakingRate, language }) {
            if (!state.conversation) return Promise.resolve();
            return axios.post(`/v1/support/conversations/${state.conversation.id}/voice/preferences`, {
                voice,
                speaking_rate: speakingRate,
                language: language || state.language,
                guest_token: state.guestToken,
            }, {
                headers: state.guestToken ? { 'X-Guest-Token': state.guestToken } : {}
            });
        },
        initConversation({ commit, state, dispatch }) {
            commit('SET_LOADING', true);
            commit('SET_ERROR', null);

            let payload = {
                language: state.language || 'en',
                guest_token: state.guestToken,
            };

            return new Promise((resolve, reject) => {
                axios.post('/v1/support/conversations', payload, {
                    headers: state.guestToken ? { 'X-Guest-Token': state.guestToken } : {}
                }).then((res) => {
                    const convData = res.data.data;
                    commit('SET_CONVERSATION', convData.conversation);
                    commit('SET_MESSAGES', convData.messages || []);

                    if (convData.conversation.guest_token) {
                        commit('SET_GUEST_TOKEN', convData.conversation.guest_token);
                    }

                    commit('SET_LOADING', false);
                    dispatch('initRealtime');
                    dispatch('startPolling');
                    resolve(res);
                }).catch((err) => {
                    commit('SET_LOADING', false);
                    commit('SET_ERROR', err?.response?.data?.error?.message || 'Failed to initialize support conversation.');
                    reject(err);
                });
            });
        },
        sendMessage({ commit, state }, messageText) {
            if (!messageText || !messageText.trim()) return Promise.resolve();

            const text = messageText.trim();
            const clientMsgId = 'client_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);

            // Optimistically append customer message to UI
            const optimisticMsg = {
                id: 'temp_' + Date.now(),
                sender: 'customer',
                type: 'text',
                content: text,
                payload: null,
                language: state.language,
                created_at: new Date().toISOString(),
            };

            commit('APPEND_MESSAGE', optimisticMsg);
            commit('SET_SENDING', true);
            commit('SET_TYPING', true);
            commit('SET_ERROR', null);

            if (!state.conversation) {
                return Promise.reject(new Error('No active conversation'));
            }

            const url = `/v1/support/conversations/${state.conversation.id}/messages`;
            const payload = {
                message: text,
                language: state.language,
                client_message_id: clientMsgId,
                guest_token: state.guestToken,
            };

            return new Promise((resolve, reject) => {
                axios.post(url, payload, {
                    headers: state.guestToken ? { 'X-Guest-Token': state.guestToken } : {}
                }).then((res) => {
                    const convData = res.data.data;
                    commit('SET_CONVERSATION', convData.conversation);
                    commit('SET_MESSAGES', convData.messages || []);
                    commit('SET_SENDING', false);
                    commit('SET_TYPING', false);
                    resolve(res);
                }).catch((err) => {
                    commit('SET_SENDING', false);
                    commit('SET_TYPING', false);
                    const errMsg = err?.response?.data?.error?.message || 'Failed to deliver message. Please try again.';
                    commit('SET_ERROR', errMsg);
                    commit('APPEND_MESSAGE', {
                        id: 'err_' + Date.now(),
                        sender: 'system',
                        type: 'error',
                        content: errMsg,
                        created_at: new Date().toISOString(),
                    });
                    reject(err);
                });
            });
        },
        requestHumanHandoff({ commit, state }) {
            if (!state.conversation) return Promise.resolve();

            commit('SET_LOADING', true);
            const url = `/v1/support/conversations/${state.conversation.id}/request-human`;

            return new Promise((resolve, reject) => {
                axios.post(url, { guest_token: state.guestToken }, {
                    headers: state.guestToken ? { 'X-Guest-Token': state.guestToken } : {}
                }).then((res) => {
                    const convData = res.data.data;
                    commit('SET_CONVERSATION', convData.conversation);
                    commit('SET_MESSAGES', convData.messages || []);
                    commit('SET_LOADING', false);
                    resolve(res);
                }).catch((err) => {
                    commit('SET_LOADING', false);
                    reject(err);
                });
            });
        },
        resolveConversation({ commit, state }) {
            if (!state.conversation) return Promise.resolve();

            const url = `/v1/support/conversations/${state.conversation.id}/resolve`;
            return axios.post(url, { guest_token: state.guestToken }, {
                headers: state.guestToken ? { 'X-Guest-Token': state.guestToken } : {}
            }).then((res) => {
                const convData = res.data.data;
                commit('SET_CONVERSATION', convData.conversation);
                commit('SET_MESSAGES', convData.messages || []);
            });
        },
        executeAction({ commit, state }, { toolName, args, confirmed }) {
            if (!state.conversation) return Promise.resolve();

            commit('SET_TYPING', true);
            const url = `/v1/support/conversations/${state.conversation.id}/actions/${toolName}`;
            const payload = {
                tool_name: toolName,
                arguments: args || {},
                confirmed: confirmed !== false,
                guest_token: state.guestToken,
            };

            return new Promise((resolve, reject) => {
                axios.post(url, payload, {
                    headers: state.guestToken ? { 'X-Guest-Token': state.guestToken } : {}
                }).then((res) => {
                    const convData = res.data.data;
                    commit('SET_CONVERSATION', convData.conversation);
                    commit('SET_MESSAGES', convData.messages || []);
                    commit('SET_TYPING', false);
                    resolve(res);
                }).catch((err) => {
                    commit('SET_TYPING', false);
                    reject(err);
                });
            });
        },

        /*
        |--------------------------------------------------------------------------
        | Voice Session & Turn Actions (Phase 6)
        |--------------------------------------------------------------------------
        */
        startVoiceSession({ commit, state }) {
            if (!state.conversation) return Promise.resolve();
            const url = `/v1/support/conversations/${state.conversation.id}/voice/sessions`;

            return axios.post(url, { language: state.language }, {
                headers: state.guestToken ? { 'X-Guest-Token': state.guestToken } : {}
            }).then((res) => {
                commit('SET_VOICE_SESSION_ID', res.data.data.session_id);
                commit('SET_VOICE_STATE', 'recording');
                return res;
            }).catch((err) => {
                commit('SET_VOICE_STATE', 'error');
                throw err;
            });
        },
        sendVoiceAudio({ commit, state, dispatch }, { audioFile, audioBase64, transcript }) {
            if (!state.conversation) return Promise.resolve();

            commit('SET_VOICE_STATE', 'processing');
            commit('SET_TYPING', true);

            const formData = new FormData();
            if (audioFile) formData.append('audio', audioFile);
            if (audioBase64) formData.append('audio_base64', audioBase64);
            if (transcript) formData.append('transcript', transcript);
            formData.append('language', state.language || 'en');
            if (state.voiceSessionId) formData.append('session_id', state.voiceSessionId);
            if (state.guestToken) formData.append('guest_token', state.guestToken);

            const url = `/v1/support/conversations/${state.conversation.id}/voice/process`;

            return axios.post(url, formData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                    ...(state.guestToken ? { 'X-Guest-Token': state.guestToken } : {})
                }
            }).then((res) => {
                const turnData = res.data.data;
                commit('SET_TYPING', false);

                // Optimistically show user transcript if returned
                if (turnData.user_transcript) {
                    commit('APPEND_MESSAGE', {
                        id: 'voice_' + Date.now(),
                        sender: 'customer',
                        type: 'voice_transcript',
                        content: turnData.user_transcript,
                        created_at: new Date().toISOString(),
                    });
                }

                // Show assistant message
                if (turnData.assistant_message) {
                    commit('APPEND_MESSAGE', {
                        id: turnData.assistant_message.id || ('ai_' + Date.now()),
                        sender: 'ai',
                        type: turnData.assistant_message.message_type || 'text',
                        content: turnData.assistant_message.content,
                        payload: turnData.assistant_message.structured_payload,
                        created_at: new Date().toISOString(),
                    });
                }

                // Play Audio Synthesis if present
                if (turnData.audio_content || turnData.audio_url) {
                    dispatch('playVoiceResponse', turnData.audio_content || turnData.audio_url);
                } else {
                    commit('SET_VOICE_STATE', 'idle');
                }

                return res;
            }).catch((err) => {
                commit('SET_TYPING', false);
                commit('SET_VOICE_STATE', 'error');
                const errMsg = err?.response?.data?.error?.message || 'Voice turn failed. Please try again.';
                commit('SET_ERROR', errMsg);
                setTimeout(() => commit('SET_VOICE_STATE', 'idle'), 4000);
                throw err;
            });
        },
        playVoiceResponse({ commit, state }, audioSrc) {
            if (state.voiceAudioEl) {
                state.voiceAudioEl.pause();
                state.voiceAudioEl.src = '';
            }

            const audio = new Audio(audioSrc);
            commit('SET_VOICE_AUDIO_EL', audio);
            commit('SET_VOICE_STATE', 'speaking');

            audio.onended = () => {
                commit('SET_VOICE_STATE', 'idle');
            };
            audio.onerror = () => {
                commit('SET_VOICE_STATE', 'idle');
            };

            audio.play().catch(() => {
                commit('SET_VOICE_STATE', 'idle');
            });
        },
        interruptVoice({ commit, state }) {
            if (state.voiceAudioEl) {
                state.voiceAudioEl.pause();
                state.voiceAudioEl.currentTime = 0;
            }

            if (state.conversation && state.voiceState === 'speaking') {
                const url = `/v1/support/conversations/${state.conversation.id}/voice/interrupt`;
                axios.post(url, {}, {
                    headers: state.guestToken ? { 'X-Guest-Token': state.guestToken } : {}
                }).catch(() => {});
            }

            commit('SET_VOICE_STATE', 'idle');
        },

        /*
        |--------------------------------------------------------------------------
        | Realtime Transport & Polling Fallback (Phase 6)
        |--------------------------------------------------------------------------
        */
        initRealtime({ commit, state }) {
            if (!state.conversation || typeof window === 'undefined' || !window.Echo) {
                return;
            }

            try {
                const channelName = `support.conversation.${state.conversation.id}`;
                window.Echo.private(channelName)
                    .listen('.support.message.created', (event) => {
                        if (event && !event.is_internal) {
                            commit('APPEND_MESSAGE', {
                                id: event.id,
                                sender: event.sender_type,
                                type: event.message_type,
                                content: event.content,
                                payload: event.structured_payload,
                                created_at: event.created_at,
                            });
                            if (!state.isOpen) {
                                commit('INCREMENT_UNREAD');
                            }
                        }
                    })
                    .listen('.support.conversation.updated', (event) => {
                        if (event && state.conversation) {
                            state.conversation.status = event.status;
                            state.conversation.mode = event.mode;
                            state.conversation.priority = event.priority;
                        }
                    });

                commit('SET_REALTIME_CONNECTED', true);
            } catch (e) {
                commit('SET_REALTIME_CONNECTED', false);
            }
        },
        startPolling({ commit, state }) {
            if (state.pollingTimer) {
                clearInterval(state.pollingTimer);
            }

            // Bounded transparent polling fallback every 6 seconds
            const timer = setInterval(() => {
                if (!state.isOpen || !state.conversation) return;

                const lastMsg = state.messages[state.messages.length - 1];
                const afterId = lastMsg ? (typeof lastMsg.id === 'number' ? lastMsg.id : 0) : 0;
                const url = `/v1/support/conversations/${state.conversation.id}/updates?after_id=${afterId}`;

                axios.get(url, {
                    headers: state.guestToken ? { 'X-Guest-Token': state.guestToken } : {}
                }).then((res) => {
                    const updates = res.data.data;
                    if (updates && updates.new_messages && updates.new_messages.length > 0) {
                        updates.new_messages.forEach((msg) => {
                            commit('APPEND_MESSAGE', msg);
                            if (!state.isOpen) {
                                commit('INCREMENT_UNREAD');
                            }
                        });
                    }
                    if (updates && updates.status && state.conversation) {
                        state.conversation.status = updates.status;
                        state.conversation.mode = updates.mode;
                    }
                }).catch(() => {});
            }, 6000);

            commit('SET_POLLING_TIMER', timer);
        },
        stopPolling({ commit, state }) {
            if (state.pollingTimer) {
                clearInterval(state.pollingTimer);
                commit('SET_POLLING_TIMER', null);
            }
        },
    },
    mutations: {
        SET_IS_OPEN: (state, payload) => { state.isOpen = payload; },
        SET_IS_MINIMIZED: (state, payload) => { state.isMinimized = payload; },
        SET_CONVERSATION: (state, payload) => { state.conversation = payload; },
        SET_CONVERSATIONS_LIST: (state, payload) => { state.conversationsList = payload; },
        SET_MESSAGES: (state, payload) => { state.messages = payload; },
        APPEND_MESSAGE: (state, payload) => {
            if (payload.id && !String(payload.id).startsWith('temp_') && !String(payload.id).startsWith('voice_')) {
                const tempIndex = state.messages.findIndex(m => (String(m.id).startsWith('temp_') || String(m.id).startsWith('voice_')) && m.content === payload.content);
                if (tempIndex !== -1) {
                    state.messages.splice(tempIndex, 1, payload);
                    return;
                }
            }
            if (!state.messages.some(m => m.id === payload.id)) {
                state.messages.push(payload);
            }
        },
        SET_LOADING: (state, payload) => { state.isLoading = payload; },
        SET_SENDING: (state, payload) => { state.isSending = payload; },
        SET_TYPING: (state, payload) => { state.isTyping = payload; },
        SET_ERROR: (state, payload) => { state.error = payload; },
        SET_LANGUAGE: (state, payload) => { state.language = payload; },
        SET_GUEST_TOKEN: (state, payload) => {
            state.guestToken = payload;
            localStorage.setItem('6ix_support_guest_token', payload);
        },
        INCREMENT_UNREAD: (state) => { state.unreadCount += 1; },
        RESET_UNREAD: (state) => { state.unreadCount = 0; },
        SET_POLLING_TIMER: (state, payload) => { state.pollingTimer = payload; },
        SET_VOICE_STATE: (state, payload) => { state.voiceState = payload; },
        SET_VOICE_SESSION_ID: (state, payload) => { state.voiceSessionId = payload; },
        SET_VOICE_AUDIO_EL: (state, payload) => { state.voiceAudioEl = payload; },
        SET_REALTIME_CONNECTED: (state, payload) => { state.isRealtimeConnected = payload; },
        SET_CAPABILITIES: (state, payload) => { state.capabilities = payload; },
    },
};
