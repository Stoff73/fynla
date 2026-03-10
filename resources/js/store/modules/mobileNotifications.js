/**
 * Mobile Notifications Store Module
 *
 * Manages push notification permission state, unread counts,
 * and in-app notification display. Full implementation in Task 17.
 */

const state = {
    permissionStatus: 'unknown', // 'unknown', 'granted', 'denied', 'prompt'
    unreadCount: 0,
    inAppNotification: null,
    promptDismissals: {}, // { triggerType: dismissedAt }
};

const getters = {
    permissionStatus: (state) => state.permissionStatus,
    unreadCount: (state) => state.unreadCount,
    inAppNotification: (state) => state.inAppNotification,
    hasPermission: (state) => state.permissionStatus === 'granted',
    shouldPrompt: (state) => state.permissionStatus === 'unknown' || state.permissionStatus === 'prompt',
};

const mutations = {
    SET_PERMISSION_STATUS(state, status) {
        state.permissionStatus = status;
    },
    SET_UNREAD_COUNT(state, count) {
        state.unreadCount = count;
    },
    SET_IN_APP_NOTIFICATION(state, notification) {
        state.inAppNotification = notification;
    },
    CLEAR_IN_APP_NOTIFICATION(state) {
        state.inAppNotification = null;
    },
    SET_PROMPT_DISMISSAL(state, triggerType) {
        state.promptDismissals[triggerType] = Date.now();
    },
};

const actions = {
    async requestPermission({ commit }) {
        // Full implementation in Task 17
        commit('SET_PERMISSION_STATUS', 'unknown');
    },

    async registerToken() {
        // Full implementation in Task 17
    },

    showInAppNotification({ commit }, notification) {
        commit('SET_IN_APP_NOTIFICATION', notification);
        setTimeout(() => {
            commit('CLEAR_IN_APP_NOTIFICATION');
        }, 4000);
    },

    clearUnread({ commit }) {
        commit('SET_UNREAD_COUNT', 0);
    },

    dismissPrompt({ commit }, triggerType) {
        commit('SET_PROMPT_DISMISSAL', triggerType);
    },

    shouldShowPrompt({ state }, triggerType) {
        const dismissal = state.promptDismissals[triggerType];
        if (!dismissal) return true;
        const sevenDays = 7 * 24 * 60 * 60 * 1000;
        return Date.now() - dismissal > sevenDays;
    },
};

export default {
    namespaced: true,
    state,
    getters,
    mutations,
    actions,
};
