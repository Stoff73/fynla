import { createStore } from 'vuex';
import auth from './modules/auth';
import user from './modules/user';
import dashboard from './modules/dashboard';
import protection from './modules/protection';
import savings from './modules/savings';
import investment from './modules/investment';
import retirement from './modules/retirement';
import estate from './modules/estate';
import holistic from './modules/holistic';
import userProfile from './modules/userProfile';
import netWorth from './modules/netWorth';
import trusts from './modules/trusts';
import businessInterests from './modules/businessInterests';
import recommendations from './modules/recommendations';
import spousePermission from './modules/spousePermission';
import onboarding from './modules/onboarding';
import preview from './modules/preview';
import guidance from './modules/guidance';
import infoGuide from './modules/infoGuide';

const store = createStore({
  modules: {
    auth,
    user,
    dashboard,
    protection,
    savings,
    investment,
    retirement,
    estate,
    holistic,
    userProfile,
    netWorth,
    trusts,
    businessInterests,
    recommendations,
    spousePermission,
    onboarding,
    preview,
    guidance,
    infoGuide,
  },
  strict: process.env.NODE_ENV !== 'production',
});

export default store;
