/**
 * Mobile Notifications Store Module
 *
 * Manages push notification permission state, unread counts,
 * in-app notification display, and @capacitor/push-notifications integration.
 */

import { platform } from '@/utils/platform';
import api from '@/services/api';

const state = {
    permissionStatus: 'unknown', // 'unknown', 'granted', 'denied', 'prompt'
    unreadCount: 0,
    inAppNotification: null,
    promptDismissals: {}, // { triggerType: dismissedAt }
    listenersRegistered: false,
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
        state.promptDismissals = { ...state.promptDismissals, [triggerType]: Date.now() };
    },
    SET_LISTENERS_REGISTERED(state) {
        state.listenersRegistered = true;
    },
};

const actions = {
    async requestPermission({ commit, dispatch }) {
        if (!platform.canUsePushNotifications()) {
            commit('SET_PERMISSION_STATUS', 'denied');
            return;
        }

        const { PushNotifications } = await import('@capacitor/push-notifications');
        const result = await PushNotifications.requestPermissions();

        if (result.receive === 'granted') {
            commit('SET_PERMISSION_STATUS', 'granted');
            await PushNotifications.register();
            await dispatch('initListeners');
        } else {
            commit('SET_PERMISSION_STATUS', 'denied');
        }
    },

    async checkPermission({ commit }) {
        if (!platform.canUsePushNotifications()) {
            commit('SET_PERMISSION_STATUS', 'denied');
            return;
        }

        const { PushNotifications } = await import('@capacitor/push-notifications');
        const result = await PushNotifications.checkPermissions();
        commit('SET_PERMISSION_STATUS', result.receive);
    },

    async initListeners({ commit, dispatch, state }) {
        if (!platform.canUsePushNotifications() || state.listenersRegistered) {
            return;
        }

        const { PushNotifications } = await import('@capacitor/push-notifications');

        PushNotifications.addListener('registration', async (token) => {
            await dispatch('registerToken', token.value);
        });

        PushNotifications.addListener('registrationError', (error) => {
            // eslint-disable-next-line no-console
            console.warn('Push registration failed:', error);
        });

        PushNotifications.addListener('pushNotificationReceived', (notification) => {
            dispatch('showInAppNotification', {
                title: notification.title || 'Fynla',
                body: notification.body || '',
                deepLink: notification.data?.deepLink || null,
            });
        });

        PushNotifications.addListener('pushNotificationActionPerformed', (action) => {
            const deepLink = action.notification?.data?.deepLink;
            if (deepLink) {
                // Navigation handled by the component watching inAppNotification
                dispatch('showInAppNotification', {
                    title: action.notification.title || 'Fynla',
                    body: action.notification.body || '',
                    deepLink,
                    navigate: true,
                });
            }
        });

        commit('SET_LISTENERS_REGISTERED');
    },

    async registerToken(_, token) {
        try {
            await api.post('/v1/mobile/devices', {
                device_token: token,
                device_id: `${platform.isIOS() ? 'ios' : 'android'}_${Date.now()}`,
                platform: platform.isIOS() ? 'ios' : 'android',
            });
        } catch {
            // Silent fail — token registration is best-effort
        }
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
