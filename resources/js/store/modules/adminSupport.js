import axios from 'axios';

const state = {
    conversations: [],
    pagination: {
        current_page: 1,
        last_page: 1,
        per_page: 20,
        total: 0,
    },
    activeConversation: null,
    customer360: null,
    orders: [],
    ticket: null,
    departments: [],
    agents: [],
    filters: {
        status: 'all',
        department_id: '',
        priority: '',
        assigned_to: '',
        unassigned: false,
        search: '',
        language: '',
    },
    isLoading: false,
    isDetailLoading: false,
    isActionLoading: false,
    isCopilotLoading: false,
    pollingTimer: null,
};

const getters = {
    conversations: (state) => state.conversations,
    pagination: (state) => state.pagination,
    activeConversation: (state) => state.activeConversation,
    customer360: (state) => state.customer360,
    orders: (state) => state.orders,
    ticket: (state) => state.ticket,
    departments: (state) => state.departments,
    agents: (state) => state.agents,
    filters: (state) => state.filters,
    isLoading: (state) => state.isLoading,
    isDetailLoading: (state) => state.isDetailLoading,
    isActionLoading: (state) => state.isActionLoading,
    isCopilotLoading: (state) => state.isCopilotLoading,
};

const mutations = {
    SET_CONVERSATIONS(state, list) {
        state.conversations = list;
    },
    SET_PAGINATION(state, meta) {
        state.pagination = meta;
    },
    SET_ACTIVE_CONVERSATION(state, conv) {
        state.activeConversation = conv;
    },
    UPDATE_ACTIVE_CONVERSATION(state, payload) {
        if (state.activeConversation) {
            state.activeConversation = { ...state.activeConversation, ...payload };
        }
    },
    APPEND_MESSAGE(state, msg) {
        if (state.activeConversation && state.activeConversation.messages) {
            state.activeConversation.messages.push(msg);
        }
    },
    SET_CUSTOMER_360(state, data) {
        state.customer360 = data;
    },
    SET_ORDERS(state, orders) {
        state.orders = orders;
    },
    SET_TICKET(state, ticket) {
        state.ticket = ticket;
    },
    SET_DEPARTMENTS(state, list) {
        state.departments = list;
    },
    SET_AGENTS(state, list) {
        state.agents = list;
    },
    SET_FILTERS(state, filters) {
        state.filters = { ...state.filters, ...filters };
    },
    SET_LOADING(state, val) {
        state.isLoading = val;
    },
    SET_DETAIL_LOADING(state, val) {
        state.isDetailLoading = val;
    },
    SET_ACTION_LOADING(state, val) {
        state.isActionLoading = val;
    },
    SET_COPILOT_LOADING(state, val) {
        state.isCopilotLoading = val;
    },
    SET_POLLING_TIMER(state, timer) {
        state.pollingTimer = timer;
    },
};

const actions = {
    fetchQueue({ commit, state }, page = 1) {
        commit('SET_LOADING', true);
        const params = {
            page,
            ...state.filters,
        };

        return axios.get('/api/v1/support/agent/conversations', { params })
            .then((res) => {
                commit('SET_CONVERSATIONS', res.data.data || []);
                commit('SET_PAGINATION', res.data.meta || {});
                commit('SET_LOADING', false);
                return res;
            })
            .catch((err) => {
                commit('SET_LOADING', false);
                throw err;
            });
    },

    fetchConversation({ commit, dispatch }, publicId) {
        commit('SET_DETAIL_LOADING', true);
        return axios.get(`/api/v1/support/agent/conversations/${publicId}`)
            .then((res) => {
                const conv = res.data.data;
                commit('SET_ACTIVE_CONVERSATION', conv);
                commit('SET_DETAIL_LOADING', false);

                // Fetch linked Customer 360 and Orders in parallel
                dispatch('fetchCustomer360', publicId);
                dispatch('fetchOrders', publicId);

                return res;
            })
            .catch((err) => {
                commit('SET_DETAIL_LOADING', false);
                throw err;
            });
    },

    assignConversation({ commit }, { publicId, agentId, departmentId, reason }) {
        commit('SET_ACTION_LOADING', true);
        return axios.post(`/api/v1/support/agent/conversations/${publicId}/assign`, {
            agent_id: agentId,
            department_id: departmentId,
            reason,
        }).then((res) => {
            commit('SET_ACTIVE_CONVERSATION', res.data.data);
            commit('SET_ACTION_LOADING', false);
            return res;
        }).catch((err) => {
            commit('SET_ACTION_LOADING', false);
            throw err;
        });
    },

    sendReply({ commit }, { publicId, message, resolveAfterReply }) {
        commit('SET_ACTION_LOADING', true);
        return axios.post(`/api/v1/support/agent/conversations/${publicId}/reply`, {
            message,
            resolve_after_reply: resolveAfterReply || false,
        }).then((res) => {
            commit('SET_ACTIVE_CONVERSATION', res.data.data);
            commit('SET_ACTION_LOADING', false);
            return res;
        }).catch((err) => {
            commit('SET_ACTION_LOADING', false);
            throw err;
        });
    },

    addInternalNote({ commit }, { publicId, content }) {
        commit('SET_ACTION_LOADING', true);
        return axios.post(`/api/v1/support/agent/conversations/${publicId}/notes`, {
            content,
        }).then((res) => {
            commit('SET_ACTIVE_CONVERSATION', res.data.data);
            commit('SET_ACTION_LOADING', false);
            return res;
        }).catch((err) => {
            commit('SET_ACTION_LOADING', false);
            throw err;
        });
    },

    updateStatus({ commit }, { publicId, status, reason }) {
        commit('SET_ACTION_LOADING', true);
        return axios.patch(`/api/v1/support/agent/conversations/${publicId}/status`, {
            status,
            reason,
        }).then((res) => {
            commit('SET_ACTIVE_CONVERSATION', res.data.data);
            commit('SET_ACTION_LOADING', false);
            return res;
        }).catch((err) => {
            commit('SET_ACTION_LOADING', false);
            throw err;
        });
    },

    updatePriority({ commit }, { publicId, priority }) {
        commit('SET_ACTION_LOADING', true);
        return axios.patch(`/api/v1/support/agent/conversations/${publicId}/priority`, {
            priority,
        }).then((res) => {
            commit('SET_ACTIVE_CONVERSATION', res.data.data);
            commit('SET_ACTION_LOADING', false);
            return res;
        }).catch((err) => {
            commit('SET_ACTION_LOADING', false);
            throw err;
        });
    },

    updateDepartment({ commit }, { publicId, departmentId }) {
        commit('SET_ACTION_LOADING', true);
        return axios.patch(`/api/v1/support/agent/conversations/${publicId}/department`, {
            department_id: departmentId,
        }).then((res) => {
            commit('SET_ACTIVE_CONVERSATION', res.data.data);
            commit('SET_ACTION_LOADING', false);
            return res;
        }).catch((err) => {
            commit('SET_ACTION_LOADING', false);
            throw err;
        });
    },

    fetchCustomer360({ commit }, publicId) {
        return axios.get(`/api/v1/support/agent/conversations/${publicId}/customer`)
            .then((res) => {
                commit('SET_CUSTOMER_360', res.data.data);
                return res;
            });
    },

    fetchOrders({ commit }, publicId) {
        return axios.get(`/api/v1/support/agent/conversations/${publicId}/orders`)
            .then((res) => {
                commit('SET_ORDERS', res.data.data || []);
                return res;
            });
    },

    generateAiSummary({ commit }, publicId) {
        commit('SET_COPILOT_LOADING', true);
        return axios.post(`/api/v1/support/agent/conversations/${publicId}/summarize`)
            .then((res) => {
                commit('UPDATE_ACTIVE_CONVERSATION', { ai_summary: res.data.data.ai_summary });
                commit('SET_COPILOT_LOADING', false);
                return res;
            }).catch((err) => {
                commit('SET_COPILOT_LOADING', false);
                throw err;
            });
    },

    fetchDepartments({ commit }) {
        return axios.get('/api/v1/support/agent/departments')
            .then((res) => {
                commit('SET_DEPARTMENTS', res.data.data || []);
                return res;
            });
    },

    fetchAgents({ commit }) {
        return axios.get('/api/v1/support/agent/agents')
            .then((res) => {
                commit('SET_AGENTS', res.data.data || []);
                return res;
            });
    },

    updatePresence({ commit }, { status, availability }) {
        return axios.post('/api/v1/support/agent/presence', { status, availability })
            .then((res) => {
                return res;
            });
    },

    initRealtimeQueue({ state, dispatch, commit }) {
        if (typeof window === 'undefined' || !window.Echo) return;

        try {
            window.Echo.private('support.agent.queue')
                .listen('.support.queue.updated', () => {
                    dispatch('fetchQueue', state.pagination.current_page);
                })
                .listen('.support.agent.presence.changed', (event) => {
                    if (event && event.agent_id) {
                        const agentIndex = state.agents.findIndex(a => a.id === event.agent_id);
                        if (agentIndex !== -1) {
                            state.agents[agentIndex].status = event.status;
                            state.agents[agentIndex].availability = event.availability;
                        }
                    }
                });
        } catch (e) {}
    },

    startQueuePolling({ state, dispatch, commit }) {
        dispatch('initRealtimeQueue');
        if (state.pollingTimer) clearInterval(state.pollingTimer);

        const timer = setInterval(() => {
            dispatch('fetchQueue', state.pagination.current_page);
            if (state.activeConversation) {
                dispatch('fetchConversation', state.activeConversation.id);
            }
        }, 8000);

        commit('SET_POLLING_TIMER', timer);
    },

    stopQueuePolling({ state, commit }) {
        if (state.pollingTimer) {
            clearInterval(state.pollingTimer);
            commit('SET_POLLING_TIMER', null);
        }
    },
};

export default {
    namespaced: true,
    state,
    getters,
    mutations,
    actions,
};
