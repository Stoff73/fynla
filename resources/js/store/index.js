import { createStore } from 'vuex';
import createPersistedState from 'vuex-persistedstate';
import { Capacitor } from '@capacitor/core';
import { Preferences } from '@capacitor/preferences';
import auth from './modules/auth';
import dashboard from './modules/dashboard';
import protection from './modules/protection';
import savings from './modules/savings';
import investment from './modules/investment';
import retirement from './modules/retirement';
import goals from './modules/goals';
import estate from './modules/estate';
import userProfile from './modules/userProfile';
import netWorth from './modules/netWorth';
import trusts from './modules/trusts';
import businessInterests from './modules/businessInterests';
import chattels from './modules/chattels';
import recommendations from './modules/recommendations';
import spousePermission from './modules/spousePermission';
import onboarding from './modules/onboarding';
import preview from './modules/preview';
import infoGuide from './modules/infoGuide';
import aiChat from './modules/aiChat';
import plans from './modules/plans';
import taxConfig from './modules/taxConfig';
import household from './modules/household';
import journeys from './modules/journeys';
import mobileDashboard from './modules/mobileDashboard';
import mobileNotifications from './modules/mobileNotifications';
import advisor from './modules/advisor';
import lifeStage from './modules/lifeStage';
import completeness from './modules/completeness';
import subNav from './modules/subNav';
import whatIf from './modules/whatIf';
import aiFormFill from './modules/aiFormFill';
import toast from './modules/toast';

/**
 * Create a storage backend that uses Capacitor Preferences on native
 * and localStorage on web. vuex-persistedstate requires sync getItem/setItem,
 * so on native we use a sync in-memory cache that's hydrated on app start.
 */
const nativeCache = {};

const storageBackend = Capacitor.isNativePlatform()
  ? {
      getItem: (key) => nativeCache[key] || null,
      setItem: (key, value) => {
        nativeCache[key] = value;
        // Async persist to native storage (fire-and-forget)
        Preferences.set({ key, value });
      },
      removeItem: (key) => {
        delete nativeCache[key];
        Preferences.remove({ key });
      },
    }
  : window.localStorage;

const store = createStore({
  modules: {
    auth,
    dashboard,
    protection,
    savings,
    investment,
    retirement,
    goals,
    estate,
    userProfile,
    netWorth,
    trusts,
    businessInterests,
    chattels,
    recommendations,
    spousePermission,
    onboarding,
    preview,
    infoGuide,
    aiChat,
    plans,
    taxConfig,
    household,
    journeys,
    mobileDashboard,
    mobileNotifications,
    advisor,
    lifeStage,
    completeness,
    subNav,
    whatIf,
    aiFormFill,
    toast,
  },
  plugins: [
    createPersistedState({
      key: 'fynla-state',
      paths: [
        'auth.user',
        'dashboard',
        'aiChat.conversations',
        'goals.goals',
        'mobileDashboard',
        'mobileNotifications.permissionStatus',
      ],
      storage: storageBackend,
    }),
  ],
  strict: process.env.NODE_ENV !== 'production',
});

export default store;
