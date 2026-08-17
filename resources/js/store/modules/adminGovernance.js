import axios from 'axios';

const state = {
    // Knowledge
    articles: [],
    knowledgePagination: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
    activeArticle: null,
    categories: ['Products', 'Shipping', 'Returns', 'Refunds', 'Payments', 'Account', 'Orders', 'Warranty', 'Promotions', 'FAQ', 'Store Policies'],
    languages: ['en', 'yo', 'ig', 'ha'],
    
    // Policies
    policies: [],
    activePolicy: null,
    policyEffects: ['allow', 'deny', 'confirm', 'require_verification', 'require_human'],
    
    // Tools
    tools: [],
    riskLevels: ['low', 'normal', 'sensitive', 'critical'],
    
    // Audit Logs
    auditLogs: [],
    auditPagination: { current_page: 1, last_page: 1, per_page: 25, total: 0 },
    
    // Simulation
    simulationResult: null,

    isLoading: false,
    isSaving: false,
    error: null,
};

const getters = {
    articles: (state) => state.articles,
    knowledgePagination: (state) => state.knowledgePagination,
    activeArticle: (state) => state.activeArticle,
    categories: (state) => state.categories,
    languages: (state) => state.languages,
    policies: (state) => state.policies,
    activePolicy: (state) => state.activePolicy,
    policyEffects: (state) => state.policyEffects,
    tools: (state) => state.tools,
    riskLevels: (state) => state.riskLevels,
    auditLogs: (state) => state.auditLogs,
    auditPagination: (state) => state.auditPagination,
    simulationResult: (state) => state.simulationResult,
    isLoading: (state) => state.isLoading,
    isSaving: (state) => state.isSaving,
    error: (state) => state.error,
};

const mutations = {
    SET_ARTICLES(state, articles) { state.articles = articles; },
    SET_KNOWLEDGE_PAGINATION(state, meta) { state.knowledgePagination = meta; },
    SET_ACTIVE_ARTICLE(state, article) { state.activeArticle = article; },
    SET_POLICIES(state, policies) { state.policies = policies; },
    SET_ACTIVE_POLICY(state, policy) { state.activePolicy = policy; },
    SET_TOOLS(state, tools) { state.tools = tools; },
    SET_AUDIT_LOGS(state, logs) { state.auditLogs = logs; },
    SET_AUDIT_PAGINATION(state, meta) { state.auditPagination = meta; },
    SET_SIMULATION_RESULT(state, res) { state.simulationResult = res; },
    SET_LOADING(state, val) { state.isLoading = val; },
    SET_SAVING(state, val) { state.isSaving = val; },
    SET_ERROR(state, err) { state.error = err; },
};

const actions = {
    fetchKnowledgeArticles({ commit }, params = {}) {
        commit('SET_LOADING', true);
        return axios.get('/api/v1/support/admin/knowledge', { params })
            .then((res) => {
                commit('SET_ARTICLES', res.data.data || []);
                if (res.data.meta) commit('SET_KNOWLEDGE_PAGINATION', res.data.meta);
                commit('SET_LOADING', false);
                return res;
            }).catch((err) => {
                commit('SET_LOADING', false);
                commit('SET_ERROR', err?.response?.data?.error?.message || 'Failed to load knowledge articles.');
                throw err;
            });
    },

    fetchKnowledgeArticle({ commit }, id) {
        commit('SET_LOADING', true);
        return axios.get(`/api/v1/support/admin/knowledge/${id}`)
            .then((res) => {
                commit('SET_ACTIVE_ARTICLE', res.data.data);
                commit('SET_LOADING', false);
                return res;
            }).catch((err) => {
                commit('SET_LOADING', false);
                throw err;
            });
    },

    saveKnowledgeArticle({ commit, dispatch }, payload) {
        commit('SET_SAVING', true);
        const request = payload.id
            ? axios.put(`/api/v1/support/admin/knowledge/${payload.id}`, payload)
            : axios.post('/api/v1/support/admin/knowledge', payload);

        return request.then((res) => {
            commit('SET_SAVING', false);
            dispatch('fetchKnowledgeArticles');
            return res;
        }).catch((err) => {
            commit('SET_SAVING', false);
            throw err;
        });
    },

    publishKnowledgeArticle({ commit, dispatch }, id) {
        commit('SET_SAVING', true);
        return axios.post(`/api/v1/support/admin/knowledge/${id}/publish`)
            .then((res) => {
                commit('SET_SAVING', false);
                dispatch('fetchKnowledgeArticles');
                return res;
            }).catch((err) => {
                commit('SET_SAVING', false);
                throw err;
            });
    },

    archiveKnowledgeArticle({ commit, dispatch }, id) {
        commit('SET_SAVING', true);
        return axios.post(`/api/v1/support/admin/knowledge/${id}/archive`)
            .then((res) => {
                commit('SET_SAVING', false);
                dispatch('fetchKnowledgeArticles');
                return res;
            }).catch((err) => {
                commit('SET_SAVING', false);
                throw err;
            });
    },

    rollbackKnowledgeArticle({ commit, dispatch }, { id, target_version, reason }) {
        commit('SET_SAVING', true);
        return axios.post(`/api/v1/support/admin/knowledge/${id}/rollback`, { target_version, reason })
            .then((res) => {
                commit('SET_SAVING', false);
                dispatch('fetchKnowledgeArticles');
                return res;
            }).catch((err) => {
                commit('SET_SAVING', false);
                throw err;
            });
    },

    previewKnowledgeArticle({ commit }, payload) {
        return axios.post('/api/v1/support/admin/knowledge/preview', payload);
    },

    // Policies
    fetchPolicies({ commit }, params = {}) {
        commit('SET_LOADING', true);
        return axios.get('/api/v1/support/admin/policies', { params })
            .then((res) => {
                commit('SET_POLICIES', res.data.data || []);
                commit('SET_LOADING', false);
                return res;
            }).catch((err) => {
                commit('SET_LOADING', false);
                throw err;
            });
    },

    savePolicy({ commit, dispatch }, payload) {
        commit('SET_SAVING', true);
        const request = payload.id
            ? axios.put(`/api/v1/support/admin/policies/${payload.id}`, payload)
            : axios.post('/api/v1/support/admin/policies', payload);

        return request.then((res) => {
            commit('SET_SAVING', false);
            dispatch('fetchPolicies');
            return res;
        }).catch((err) => {
            commit('SET_SAVING', false);
            throw err;
        });
    },

    togglePolicy({ commit, dispatch }, { id, is_active }) {
        const url = is_active
            ? `/api/v1/support/admin/policies/${id}/activate`
            : `/api/v1/support/admin/policies/${id}/disable`;

        return axios.post(url).then((res) => {
            dispatch('fetchPolicies');
            return res;
        });
    },

    simulatePolicy({ commit }, payload) {
        commit('SET_LOADING', true);
        return axios.post('/api/v1/support/admin/policies/simulate', payload)
            .then((res) => {
                commit('SET_SIMULATION_RESULT', res.data.data);
                commit('SET_LOADING', false);
                return res;
            }).catch((err) => {
                commit('SET_LOADING', false);
                throw err;
            });
    },

    // Tools
    fetchTools({ commit }) {
        commit('SET_LOADING', true);
        return axios.get('/api/v1/support/admin/tools')
            .then((res) => {
                commit('SET_TOOLS', res.data.data || []);
                commit('SET_LOADING', false);
                return res;
            }).catch((err) => {
                commit('SET_LOADING', false);
                throw err;
            });
    },

    updateToolPermissions({ commit, dispatch }, { id, permissions }) {
        commit('SET_SAVING', true);
        return axios.patch(`/api/v1/support/admin/tools/${id}/permissions`, permissions)
            .then((res) => {
                commit('SET_SAVING', false);
                dispatch('fetchTools');
                return res;
            }).catch((err) => {
                commit('SET_SAVING', false);
                throw err;
            });
    },

    // Audit Logs
    fetchAuditLogs({ commit }, params = {}) {
        commit('SET_LOADING', true);
        return axios.get('/api/v1/support/admin/audit-logs', { params })
            .then((res) => {
                commit('SET_AUDIT_LOGS', res.data.data || []);
                if (res.data.meta) commit('SET_AUDIT_PAGINATION', res.data.meta);
                commit('SET_LOADING', false);
                return res;
            }).catch((err) => {
                commit('SET_LOADING', false);
                throw err;
            });
    },
};

export default {
    namespaced: true,
    state,
    getters,
    mutations,
    actions,
};
