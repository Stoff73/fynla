import documentArticleService from '@/services/documentArticleService';

const state = () => ({
    items: [],
    current: null,
    loading: false,
    error: null,
});

const mutations = {
    SET_ITEMS(state, items) { state.items = items; },
    SET_CURRENT(state, item) { state.current = item; },
    UPSERT_ITEM(state, item) {
        const idx = state.items.findIndex(i => i.id === item.id);
        if (idx === -1) state.items.unshift(item);
        else state.items.splice(idx, 1, item);
    },
    REMOVE_ITEM(state, id) {
        state.items = state.items.filter(i => i.id !== id);
    },
    SET_LOADING(state, v) { state.loading = v; },
    SET_ERROR(state, e) { state.error = e; },
};

const actions = {
    async list({ commit }) {
        commit('SET_LOADING', true);
        try {
            const { data } = await documentArticleService.list();
            commit('SET_ITEMS', data.data);
        } catch (e) {
            commit('SET_ERROR', e.message || 'Failed to load');
            throw e;
        } finally {
            commit('SET_LOADING', false);
        }
    },

    async get({ commit }, id) {
        const { data } = await documentArticleService.get(id);
        commit('SET_CURRENT', data.data);
        return data.data;
    },

    async import({ commit }, payload) {
        const { data } = await documentArticleService.import(payload);
        commit('UPSERT_ITEM', data.data);
        commit('SET_CURRENT', data.data);
        return data.data;
    },

    async update({ commit }, { id, ...payload }) {
        const { data } = await documentArticleService.update(id, payload);
        commit('UPSERT_ITEM', data.data);
        commit('SET_CURRENT', data.data);
        return data.data;
    },

    async destroy({ commit }, id) {
        await documentArticleService.destroy(id);
        commit('REMOVE_ITEM', id);
    },

    async publish({ commit }, id) {
        const { data } = await documentArticleService.publish(id);
        commit('UPSERT_ITEM', data.data);
        commit('SET_CURRENT', data.data);
        return data.data;
    },

    async unpublish({ commit }, id) {
        const { data } = await documentArticleService.unpublish(id);
        commit('UPSERT_ITEM', data.data);
        commit('SET_CURRENT', data.data);
        return data.data;
    },

    async previewUrl(_, id) {
        const { data } = await documentArticleService.previewUrl(id);
        return data.url;
    },
};

const getters = {
    drafts: (state) => state.items.filter(i => i.status === 'draft'),
    published: (state) => state.items.filter(i => i.status === 'published'),
};

export default {
    namespaced: true,
    state,
    mutations,
    actions,
    getters,
};
