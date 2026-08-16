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
    },
    actions: {
        toggleWidget({ commit, state, dispatch }) {
            commit('SET_IS_OPEN', !state.isOpen);
            if (state.isOpen && !state.conversation) {
                dispatch('initConversation');
            }
            if (state.isOpen) {
                commit('RESET_UNREAD');
            }
        },
        closeWidget({ commit }) {
            commit('SET_IS_OPEN', false);
        },
        openWidget({ commit, dispatch, state }) {
            commit('SET_IS_OPEN', true);
            commit('RESET_UNREAD');
            if (!state.conversation) {
                dispatch('initConversation');
            }
        },
        setLanguage({ commit, state }, language) {
            commit('SET_LANGUAGE', language);
            if (state.conversation) {
                state.conversation.language = language;
            }
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
                    // Append local error card
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
        startPolling({ commit, state, dispatch }) {
            if (state.pollingTimer) {
                clearInterval(state.pollingTimer);
            }

            // Bounded poll every 6 seconds when conversation is open
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
            // Remove optimistic temp message if matching content or replace
            if (payload.id && !String(payload.id).startsWith('temp_')) {
                const tempIndex = state.messages.findIndex(m => String(m.id).startsWith('temp_') && m.content === payload.content);
                if (tempIndex !== -1) {
                    state.messages.splice(tempIndex, 1, payload);
                    return;
                }
            }
            // Avoid duplicate by id
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
    },
};
