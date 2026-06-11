<template>
  <div class="phone-frame" ref="root">

    <!-- Fixed header — hamburger + greeting (mirrors the dashboard header). -->
    <header class="md-header" role="banner">
      <button type="button" class="md-hamburger" aria-label="Open menu" :aria-expanded="drawerOpen ? 'true' : 'false'" @click="openDrawer">
        <svg aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="22" height="22"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
      </button>
      <div class="md-header__greeting"><p class="md-header__hello">{{ greeting }}</p></div>
      <span class="md-header__spacer" aria-hidden="true"></span>
    </header>

    <main class="md-main">
      <!-- Gradient hero band at the top of every page (mirrors the dashboard). -->
      <div class="md-scroll-hero md-page-hero" v-if="title">
        <h1 class="md-page-hero__title">{{ title }}</h1>
        <p v-if="subtitle" class="md-page-hero__sub">{{ subtitle }}</p>
      </div>

      <slot />

      <div class="md-bottom-pad" aria-hidden="true"></div>
    </main>

    <!-- Docked Fyn bar -->
    <button type="button" class="md-fyn-dock md-fyn-dock--bar" aria-label="Chat with Fyn" @click="openFyn">
      <span class="md-fyn-dock__avatar" aria-hidden="true"><img :src="fynIcon" alt="" /></span>
      <span class="md-fyn-dock__text">
        <span class="md-fyn-dock__name">Fyn</span>
        <span class="md-fyn-dock__status">Your financial companion</span>
      </span>
      <span class="md-fyn-dock__action" aria-hidden="true">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="22" height="22"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7" /></svg>
      </span>
    </button>

    <!-- Drawer -->
    <div class="md-drawer-backdrop" :class="{ 'is-open': drawerOpen }" :hidden="!drawerMounted" @click="closeDrawer"></div>
    <aside class="md-drawer" :class="{ 'is-open': drawerOpen }" :hidden="!drawerMounted" aria-label="Menu">
      <div class="md-drawer__head">
        <div class="md-drawer__head-text">
          <p class="md-drawer__user">{{ userName }}</p>
          <p class="md-drawer__email">{{ userEmail }}</p>
        </div>
        <button type="button" class="md-drawer__close" aria-label="Close menu" @click="closeDrawer">
          <svg aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="22" height="22"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
      </div>
      <nav class="md-drawer__nav" aria-label="Primary navigation">
        <div class="md-drawer__section">
          <a href="#" class="md-drawer__link" :class="{ 'is-active': activePath === '/dashboard' }" @click.prevent="goto('/dashboard')">
            <span class="md-drawer__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg></span>
            <span class="md-drawer__label">Dashboard</span>
          </a>
        </div>
        <div v-for="section in navSections" :key="section.group" class="md-drawer__section">
          <p class="md-drawer__group">{{ section.group }}</p>
          <a v-for="link in section.links" :key="link.slug" href="#" class="md-drawer__link" :class="{ 'is-active': activePath === link.route }" @click.prevent="goto(link.route)">
            <span class="md-drawer__icon" aria-hidden="true" v-html="link.icon"></span>
            <span class="md-drawer__label">{{ link.label }}</span>
          </a>
        </div>
        <div v-if="isAdmin" class="md-drawer__section">
          <p class="md-drawer__group">Admin</p>
          <a href="#" class="md-drawer__link" @click.prevent="gotoAdmin">
            <span class="md-drawer__icon" aria-hidden="true" v-html="NAV_ICON.admin"></span>
            <span class="md-drawer__label">Admin Panel</span>
          </a>
        </div>
        <div class="md-drawer__section md-drawer__section--account">
          <a href="#" class="md-drawer__link" @click.prevent="shareReferral">
            <span class="md-drawer__icon" aria-hidden="true" v-html="NAV_ICON.share"></span>
            <span class="md-drawer__label">Share Fynla</span>
          </a>
          <a href="#" class="md-drawer__link md-drawer__link--signout" @click.prevent="signOut">
            <span class="md-drawer__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg></span>
            <span class="md-drawer__label">Sign out</span>
          </a>
        </div>
      </nav>
    </aside>

    <!-- Fyn overlay (advice chat) -->
    <section class="md-fyn" :class="{ 'is-open': fynOpen }" :hidden="!fynMounted" aria-label="Chat with Fyn">
      <header class="md-fyn__head">
        <div class="md-fyn__title">
          <span class="md-fyn__avatar" aria-hidden="true"><img :src="fynIcon" alt="" /></span>
          <div>
            <p class="md-fyn__name">Fyn</p>
            <p class="md-fyn__status">Your financial companion</p>
          </div>
        </div>
        <div class="md-fyn__head-actions">
          <button type="button" class="md-fyn__report" @click="reportFynProblem">Report a problem</button>
          <button type="button" class="md-fyn__close" aria-label="Close Fyn chat" @click="closeFyn">
            <svg aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="22" height="22"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" /></svg>
          </button>
        </div>
      </header>

      <div class="md-fyn__messages" ref="fynBody" aria-live="polite">
        <div v-for="(m, i) in messages" :key="i" class="md-fyn__msg" :class="m.role === 'user' ? 'md-fyn__msg--user' : 'md-fyn__msg--fyn'">
          <p>{{ m.text || (sending && i === messages.length - 1 ? '…' : '') }}</p>
        </div>
      </div>

      <form class="md-fyn__compose" @submit.prevent="send()">
        <span class="md-fyn-dock__avatar" aria-hidden="true"><img :src="fynIcon" alt="" /></span>
        <label for="mc-fyn-input" class="visually-hidden">Ask Fyn a question</label>
        <input id="mc-fyn-input" v-model="draft" type="text" class="md-fyn-dock__input md-fyn__input" placeholder="Ask Fyn anything..." autocomplete="off" />
        <button type="submit" class="md-fyn-dock__send md-fyn__send" aria-label="Send to Fyn" :disabled="sending">
          <svg aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M5 12h14M13 5l7 7-7 7" /></svg>
        </button>
      </form>
    </section>

  </div>
</template>

<script>
import { apiGet, apiPost, apiStream } from '../api.js';
import { store } from '../store.js';

const NAV_ICON = {
  net_worth: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>',
  protection: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>',
  savings: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>',
  investment: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/></svg>',
  retirement: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
  estate: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M3 10h18M5 6l7-3 7 3M4 10v11m16-11v11M8 14v3m4-3v3m4-3v3"/></svg>',
  goals: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118L2.36 10.8c-.783-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.518-4.674z"/></svg>',
  share: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>',
  tax: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
  admin: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
};

export default {
  name: 'MobileChrome',
  props: {
    // Optional page title shown on the gradient hero band at the top of the page.
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
  },
  data() {
    return {
      NAV_ICON,
      drawerOpen: false,
      drawerMounted: false,
      fynOpen: false,
      fynMounted: false,
      fynStarted: false,
      conversationId: null,
      messages: [],
      draft: '',
      sending: false,
    };
  },
  computed: {
    activePath() {
      return this.$route ? this.$route.path : '';
    },
    fynIcon() {
      return (import.meta.env.VITE_ROUTER_BASE || '/') + 'images/Fyn/Fynla-Fyn-Icon.png';
    },
    greeting() {
      const h = new Date().getHours();
      const part = h < 12 ? 'morning' : h < 18 ? 'afternoon' : 'evening';
      return `Good ${part}, ${this.firstName}`;
    },
    firstName() {
      return store.user?.first_name || store.user?.name?.split(' ')[0] || 'there';
    },
    userName() {
      return store.user?.name || store.user?.first_name || 'Your account';
    },
    userEmail() {
      return store.user?.email || '';
    },
    isAdmin() {
      return store.user?.is_admin === true;
    },
    navSections() {
      return [
        { group: 'Finances', links: [
          { slug: 'net_worth', label: 'Net Worth', icon: NAV_ICON.net_worth, route: '/net-worth' },
          { slug: 'savings', label: 'Savings', icon: NAV_ICON.savings, route: '/savings' },
          { slug: 'investment', label: 'Investments', icon: NAV_ICON.investment, route: '/investment' },
          { slug: 'retirement', label: 'Retirement', icon: NAV_ICON.retirement, route: '/retirement' },
        ] },
        { group: 'Family', links: [
          { slug: 'protection', label: 'Protection', icon: NAV_ICON.protection, route: '/protection' },
          { slug: 'estate', label: 'Estate Planning', icon: NAV_ICON.estate, route: '/estate' },
        ] },
        { group: 'Planning', links: [
          { slug: 'goals', label: 'Goals', icon: NAV_ICON.goals, route: '/goals' },
          { slug: 'tax', label: 'Tax Strategy', icon: NAV_ICON.tax, route: '/tax-strategy' },
        ] },
      ];
    },
  },
  methods: {
    openDrawer() {
      this.drawerMounted = true;
      this.$nextTick(() => { this.drawerOpen = true; });
    },
    closeDrawer() {
      this.drawerOpen = false;
      window.setTimeout(() => { this.drawerMounted = false; }, 300);
    },
    goto(route) {
      this.closeDrawer();
      if (this.$route.path !== route) this.$router.push(route);
    },
    gotoAdmin() {
      this.closeDrawer();
      const url = (import.meta.env.VITE_ROUTER_BASE || '/') + 'admin';
      try {
        const token = store.token || localStorage.getItem('m_scaffold_token');
        if (token && window.top && window.top !== window) {
          window.top.sessionStorage.setItem('auth_token', token);
        }
      } catch (e) { /* iOS partitioned storage — desktop boot bridge covers it */ }
      (window.top || window).location.href = url;
    },
    async doShare(shareType) {
      try {
        const { ok, data } = await apiGet(`/api/v1/mobile/share/${shareType}`, store.token);
        if (!ok) return;
        const c = data?.data || data || {};
        const payload = { title: c.title || 'Fynla', text: c.text || '', url: c.url || 'https://fynla.org' };
        if (navigator.share) await navigator.share(payload);
        else if (navigator.clipboard) await navigator.clipboard.writeText(`${payload.text} ${payload.url}`.trim());
      } catch (e) { /* cancelled / unsupported — no-op */ }
    },
    shareReferral() {
      this.closeDrawer();
      this.doShare('app_referral');
    },
    async signOut() {
      try { if (store.token) await apiPost('/api/auth/logout', {}, store.token); } catch (e) { /* best-effort */ }
      store.logout();
      this.closeDrawer();
      this.$router.push('/login');
    },
    openFyn() {
      this.fynMounted = true;
      this.$nextTick(() => {
        this.fynOpen = true;
        this.scrollFyn();
        this.initFyn();
      });
    },
    closeFyn() {
      this.fynOpen = false;
      window.setTimeout(() => { this.fynMounted = false; }, 320);
    },
    reportFynProblem() {
      store.openBugReport(this.conversationId);
    },
    initFyn() {
      if (this.fynStarted) return;
      this.fynStarted = true;
      if (!this.messages.length) {
        this.messages.push({ role: 'fyn', text: `Hi ${this.firstName}. What would you like to look at?` });
      }
    },
    scrollFyn() {
      const b = this.$refs.fynBody;
      if (b) b.scrollTop = b.scrollHeight;
    },
    async ensureConversation() {
      if (this.conversationId) return this.conversationId;
      const res = await apiPost('/api/ai-chat/conversations', {}, store.token);
      this.conversationId = res.data?.data?.id ?? res.data?.id ?? res.data?.conversation?.id ?? null;
      return this.conversationId;
    },
    async send(preset) {
      const text = (preset || this.draft || '').trim();
      if (!text || this.sending) return;
      this.sending = true;
      this.draft = '';
      this.messages.push({ role: 'user', text });
      const cursor = { reply: { role: 'fyn', text: '' }, got: false };
      this.messages.push(cursor.reply);
      this.$nextTick(this.scrollFyn);
      try {
        const cid = await this.ensureConversation();
        if (!cid) { cursor.reply.text = 'Sorry, I could not start a conversation just now.'; return; }
        await apiStream(
          `/api/ai-chat/conversations/${cid}/messages`,
          { message: text, current_route: this.activePath || '/dashboard' },
          store.token,
          (piece) => { if (piece) cursor.got = true; cursor.reply.text += piece; this.$nextTick(this.scrollFyn); },
        );
        if (!cursor.got && !cursor.reply.text) cursor.reply.text = 'Sorry, I had trouble responding just now.';
      } catch (e) {
        cursor.reply.text = 'Sorry, something went wrong. Please try again.';
      } finally {
        this.sending = false;
        this.$nextTick(this.scrollFyn);
      }
    },
    async loadUser() {
      if (store.user || !store.token) return;
      try {
        const res = await apiGet('/api/auth/user', store.token);
        if (res.ok) store.user = res.data?.data?.user || res.data?.user || res.data?.data || null;
      } catch (e) { /* non-fatal */ }
    },
  },
  mounted() {
    this.loadUser();
  },
};
</script>

<style src="../views/dashboard.css"></style>
<style scoped>
/* Page-title gradient hero band (module pages). The chrome reuses dashboard.css
   for all md-* classes; this only adds the page-title treatment. */
.md-page-hero { text-align: left; }
.md-page-hero__title { font-size: 1.6rem; font-weight: 900; color: #fff; margin: 0; line-height: 1.15; }
.md-page-hero__sub { font-size: 0.875rem; color: rgba(255, 255, 255, 0.82); margin: 0.375rem 0 0; line-height: 1.4; }
</style>
