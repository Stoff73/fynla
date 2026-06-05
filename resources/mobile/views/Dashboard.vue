<template>
  <div class="phone-frame" ref="root">

    <!-- Fixed header — hamburger + greeting -->
    <header class="md-header" role="banner">
      <button type="button" class="md-hamburger" aria-label="Open menu" :aria-expanded="drawerOpen ? 'true' : 'false'" @click="openDrawer">
        <svg aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="22" height="22"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
      </button>
      <div class="md-header__greeting"><p class="md-header__hello">{{ greeting }}</p></div>
      <span class="md-header__spacer" aria-hidden="true"></span>
    </header>

    <main class="md-main">

      <div v-if="loading" class="md-loading">Loading your dashboard…</div>
      <div v-else-if="error" class="md-loading">
        <p>{{ error }}</p>
        <button class="md-retry" @click="load">Try again</button>
      </div>

      <template v-else>
        <!-- Gradient hero + level wheel -->
        <div class="md-scroll-hero">
          <section class="md-level" aria-labelledby="md-level-heading">
            <div class="md-level__pie" :class="{ 'is-levelup': pulsing }" role="img" :aria-label="`Level ${level}, ${progressPercent} percent complete`">
              <svg class="md-level__pie-svg" viewBox="0 0 100 100" aria-hidden="true">
                <circle class="md-level__pie-track" cx="50" cy="50" r="44" />
                <circle class="md-level__pie-arc" cx="50" cy="50" r="44" :style="{ '--progress': progressPercent }" />
              </svg>
              <div class="md-level__pie-inner">
                <p class="md-level__pie-label">Level</p>
                <p class="md-level__pie-num">{{ level }}</p>
              </div>
            </div>
            <div class="md-level__copy">
              <h2 class="md-level__heading" id="md-level-heading">{{ actionsInLevel }} of {{ actionsForNext }} actions complete</h2>
              <p class="md-level__sub">Complete actions to reach <strong>Level {{ level + 1 }}</strong>.</p>
            </div>
          </section>
        </div>

        <!-- Callout: rank statement + accordion + recommendations -->
        <div class="md-callout" role="note">
          <div class="md-callout__top">
            <p class="md-callout__levelup">LEVEL<br>UP</p>
            <div class="md-callout__top-copy">
              <p class="md-callout__lead">You're ahead of <strong>{{ percentile }}%</strong> of people</p>
              <p class="md-callout__sub">Complete your actions to get further ahead, level up and change your financial future</p>
            </div>
          </div>

          <div class="md-callout__carousel">
            <!-- Horizontal accordion -->
            <div class="md-accordion" role="tablist" aria-label="Focus areas">
              <button
                v-for="(cat, ci) in cats"
                :key="cat.key"
                type="button"
                class="md-accordion__card"
                :class="{ 'is-active': activeCat === ci }"
                role="tab"
                :aria-selected="activeCat === ci ? 'true' : 'false'"
                :aria-label="cat.label"
                @click="selectCard(ci)"
              >
                <span class="md-accordion__icon" aria-hidden="true" v-html="cat.icon"></span>
                <span class="md-accordion__content">
                  <span class="md-callout__slide-stat">{{ statFor(ci) }}</span>
                  <span class="md-callout__slide-area">{{ cat.label }}</span>
                  <span class="md-callout__slide-info">{{ cat.info }}</span>
                </span>
              </button>
            </div>

            <!-- Dots -->
            <div class="md-callout__dots" role="tablist" aria-label="Focus area navigation">
              <button
                v-for="(cat, ci) in cats"
                :key="cat.key"
                type="button"
                class="md-callout__dot"
                :class="{ 'is-active': activeCat === ci }"
                :aria-label="cat.label"
                :aria-selected="activeCat === ci ? 'true' : 'false'"
                @click="selectCard(ci)"
              ></button>
            </div>

            <!-- Recommendations -->
            <section class="md-recs is-open" aria-labelledby="md-recs-heading">
              <div class="md-section-head md-section-head--toggle">
                <h3 class="md-section-head__title" id="md-recs-heading">Recommendations</h3>
                <span class="md-section-head__right">
                  <span class="md-section-head__count">{{ doneCount }} / {{ totalCount }}</span>
                </span>
              </div>

              <div class="md-recs__body">
                <ul class="md-recs__list" aria-live="polite">
                  <li v-if="!orderedRecs.length" class="md-rec md-rec--empty">
                    <span class="md-rec__action"><span class="md-rec__text"><span class="md-rec__title">No recommendations here right now.</span></span></span>
                  </li>
                  <li
                    v-for="item in orderedRecs"
                    :key="item.rec.id"
                    class="md-rec"
                    :class="{ 'is-done': item.rec.done, 'is-completing': item.rec.completing, 'is-skipping': item.rec.skipping, 'is-entering': item.rec.entering }"
                  >
                    <button
                      type="button"
                      class="md-rec__check-btn"
                      :aria-pressed="item.rec.done ? 'true' : 'false'"
                      :aria-label="item.rec.done ? 'Mark as not done' : 'Mark complete'"
                      @click="toggleRec(item.idx)"
                    >
                      <span class="md-rec__check" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" width="14" height="14"><path stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg></span>
                    </button>
                    <a href="#" class="md-rec__action" @click.prevent="openRecChat(item.rec)">
                      <span class="md-rec__text">
                        <span class="md-rec__title">{{ item.rec.title }}</span>
                        <span class="md-rec__meta">{{ item.rec.meta }}</span>
                      </span>
                      <svg class="md-rec__chevron" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </a>
                    <button
                      v-if="item.rec.done"
                      type="button"
                      class="md-rec__skip md-rec__skip--refresh"
                      aria-label="Replace with a new recommendation"
                      @click="skipRec(item.idx)"
                    >
                      <svg aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                    </button>
                    <button
                      v-else
                      type="button"
                      class="md-rec__skip"
                      aria-label="Skip this recommendation"
                      @click="skipRec(item.idx)"
                    >Skip</button>
                  </li>
                </ul>
                <a href="#" class="md-recs__view-all" @click.prevent="goto(cats[activeCat].route)">View all recommendations</a>
              </div>
            </section>
          </div>
        </div>

        <!-- 4-panel finance grid -->
        <section class="md-panels" aria-labelledby="md-panels-heading">
          <div class="md-section-head">
            <h3 class="md-section-head__title" id="md-panels-heading">Your finances</h3>
          </div>
          <div class="md-panels__list">
            <a
              v-for="p in finances"
              :key="p.key"
              href="#"
              class="md-panel"
              :class="`md-panel--${p.tone}`"
              @click.prevent="goto(p.route)"
            >
              <div v-if="p.viz === 'bar'" class="md-panel__viz md-panel__viz--bar" aria-hidden="true">
                <div class="md-panel__bar">
                  <div class="md-panel__bar-fill" :style="{ width: p.barFill + '%' }"></div>
                </div>
                <p class="md-panel__bar-label"><strong>{{ p.barValue }}</strong> {{ p.barUnit }}</p>
              </div>
              <div v-else class="md-panel__viz" :style="{ '--progress': p.progress }" aria-hidden="true">
                <div class="md-panel__viz-inner">
                  <span class="md-panel__viz-num">{{ p.vizNum }}</span>
                  <span class="md-panel__viz-cap">{{ p.vizCap }}</span>
                </div>
              </div>
              <div class="md-panel__body">
                <p class="md-panel__label"><span class="md-panel__title-icon" aria-hidden="true" v-html="p.icon"></span>{{ p.label }}</p>
                <p class="md-panel__value">{{ p.value }}</p>
                <p class="md-panel__caption">{{ p.caption }}</p>
              </div>
            </a>
          </div>
        </section>

        <div class="md-bottom-pad" aria-hidden="true"></div>
      </template>
    </main>

    <!-- Onboarding nudge — gently points a funnel/incomplete user to finish
         their personalised tax plan with Fyn. Tapping opens Fyn; "Later"
         dismisses it for the session. Hidden once Fyn is open or onboarded. -->
    <div v-if="showFynNudge" class="md-fyn-nudge">
      <button type="button" class="md-fyn-nudge__cta" @click="openFyn">Finish your personalised tax plan with Fyn</button>
      <button type="button" class="md-fyn-nudge__later" aria-label="Dismiss" @click="nudgeDismissed = true">Later</button>
    </div>

    <!-- Docked Fyn bar -->
    <button type="button" class="md-fyn-dock md-fyn-dock--bar" aria-label="Chat with Fyn" @click="openFyn">
      <span class="md-fyn-dock__avatar" aria-hidden="true">F</span>
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
          <a href="#" class="md-drawer__link is-active" @click.prevent="goto('/dashboard')">
            <span class="md-drawer__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg></span>
            <span class="md-drawer__label">Dashboard</span>
          </a>
        </div>
        <div class="md-drawer__section">
          <p class="md-drawer__group">Finances</p>
          <a v-for="link in navLinks" :key="link.slug" href="#" class="md-drawer__link" @click.prevent="goto(link.route)">
            <span class="md-drawer__icon" aria-hidden="true" v-html="link.icon"></span>
            <span class="md-drawer__label">{{ link.label }}</span>
          </a>
        </div>
        <div class="md-drawer__section md-drawer__section--account">
          <a href="#" class="md-drawer__link md-drawer__link--signout" @click.prevent="signOut">
            <span class="md-drawer__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg></span>
            <span class="md-drawer__label">Sign out</span>
          </a>
        </div>
      </nav>
    </aside>

    <!-- Fyn overlay -->
    <section class="md-fyn" :class="{ 'is-open': fynOpen }" :hidden="!fynMounted" aria-label="Chat with Fyn">
      <header class="md-fyn__head">
        <div class="md-fyn__title">
          <span class="md-fyn__avatar" aria-hidden="true">F</span>
          <div>
            <p class="md-fyn__name">Fyn</p>
            <p class="md-fyn__status">Your financial companion</p>
          </div>
        </div>
        <button type="button" class="md-fyn__close" aria-label="Close Fyn chat" @click="closeFyn">
          <svg aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="22" height="22"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
      </header>

      <div class="md-fyn__messages" ref="fynBody" aria-live="polite">
        <div v-for="(m, i) in messages" :key="i" class="md-fyn__msg" :class="m.role === 'user' ? 'md-fyn__msg--user' : 'md-fyn__msg--fyn'">
          <p>{{ m.text || (sending && i === messages.length - 1 ? '…' : '') }}</p>
          <!-- Onboarding bubble choices (quick_replies). Tapping sends the
               label, which the director matches back to the bubble. -->
          <div v-if="m.bubbles && m.bubbles.length" class="md-fyn__bubbles">
            <button
              v-for="b in m.bubbles"
              :key="b.id"
              type="button"
              class="md-fyn__bubble"
              :disabled="sending"
              @click="chooseBubble(b)"
            >{{ b.label }}</button>
          </div>
        </div>
      </div>

      <!-- Advice prompt chips — only outside onboarding (onboarding uses bubbles). -->
      <div v-if="suggestions.length && !onboardingActive" class="md-fyn__prompts" aria-label="Suggested questions">
        <button v-for="s in suggestions" :key="s" type="button" class="md-fyn__prompt" @click="send(s)">{{ s }}</button>
      </div>

      <form class="md-fyn__compose" @submit.prevent="send()">
        <span class="md-fyn-dock__avatar" aria-hidden="true">F</span>
        <label for="md-fyn-input" class="visually-hidden">Ask Fyn a question</label>
        <input id="md-fyn-input" v-model="draft" type="text" class="md-fyn-dock__input md-fyn__input" placeholder="Ask Fyn anything..." autocomplete="off" />
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

const MAX_VISIBLE = 5;

const ICON = {
  saveTax: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
  retirement: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
  savings: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>',
  netWorth: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>',
  shield: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>',
  card: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>',
  clock: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
};

const NAV_ICON = {
  net_worth: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>',
  protection: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>',
  savings: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>',
  investment: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/></svg>',
  retirement: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
  estate: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M3 10h18M5 6l7-3 7 3M4 10v11m16-11v11M8 14v3m4-3v3m4-3v3"/></svg>',
  goals: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118L2.36 10.8c-.783-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.518-4.674z"/></svg>',
};

const CONFETTI_COLORS = ['#E83E6D', '#F472B6', '#20B486', '#5854E6', '#E6C9A8', '#FBBF24'];

export default {
  name: 'MobileDashboard',
  data() {
    return {
      loading: true,
      error: '',
      data: null,
      // recommendations
      pools: [[], [], []],        // full lists per category
      visible: [[], [], []],      // visible (up to MAX_VISIBLE) per category
      poolCursor: [0, 0, 0],
      activeCat: 0,
      doneCounter: 0,
      // level (client-side, driven by completed actions per the goal)
      baseLevel: 1,
      basePercentile: 57,
      pulsing: false,
      // drawer / fyn
      drawerOpen: false,
      drawerMounted: false,
      fynOpen: false,
      fynMounted: false,
      fynStarted: false,
      nudgeDismissed: false,
      resumeId: null,
      conversationId: null,
      messages: [],
      draft: '',
      sending: false,
      cats: [
        { key: 'save_tax', slug: 'investment', route: '/investment', label: 'Save tax', icon: ICON.saveTax, info: 'Use your full ISA and pension allowances to keep more of what you earn.' },
        { key: 'retirement', slug: 'retirement', route: '/retirement', label: 'Retirement', icon: ICON.retirement, info: 'Close your projected income gap — small increases now compound.' },
        { key: 'savings', slug: 'savings', route: '/savings', label: 'Savings', icon: ICON.savings, info: 'Build your emergency fund and earn more on your cash.' },
      ],
    };
  },
  computed: {
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
    // Onboarding is "active" only when explicitly not completed (null/undefined
    // — e.g. user not yet loaded — must not trigger the onboarding chat).
    onboardingActive() {
      return store.user?.onboarding_completed === false;
    },
    showFynNudge() {
      return this.onboardingActive && !this.fynOpen && !this.nudgeDismissed;
    },
    navLinks() {
      // Every module now routes to its dedicated mobile view.
      return [
        { slug: 'net_worth', label: 'Net Worth', icon: NAV_ICON.net_worth, route: '/net-worth' },
        { slug: 'protection', label: 'Protection', icon: NAV_ICON.protection, route: '/protection' },
        { slug: 'savings', label: 'Savings', icon: NAV_ICON.savings, route: '/savings' },
        { slug: 'investment', label: 'Investment', icon: NAV_ICON.investment, route: '/investment' },
        { slug: 'retirement', label: 'Retirement', icon: NAV_ICON.retirement, route: '/retirement' },
        { slug: 'estate', label: 'Estate', icon: NAV_ICON.estate, route: '/estate' },
        { slug: 'goals', label: 'Goals', icon: NAV_ICON.goals, route: '/goals' },
      ];
    },
    // Total completed actions across all categories drive the level wheel.
    totalDone() {
      return this.visible.reduce((sum, list) => sum + list.filter((r) => r.done).length, 0);
    },
    level() {
      let remaining = this.totalDone;
      let level = 1;
      while (remaining >= this.actionsForLevel(level)) {
        remaining -= this.actionsForLevel(level);
        level += 1;
      }
      return level;
    },
    actionsInLevel() {
      let remaining = this.totalDone;
      let level = 1;
      while (remaining >= this.actionsForLevel(level)) {
        remaining -= this.actionsForLevel(level);
        level += 1;
      }
      return remaining;
    },
    actionsForNext() {
      return this.actionsForLevel(this.level);
    },
    progressPercent() {
      return Math.round((this.actionsInLevel / this.actionsForNext) * 100);
    },
    percentile() {
      // Server-derived baseline (from the customer-base level distribution),
      // climbing as the user gains levels in-session.
      return Math.min(99, this.basePercentile + (this.level - this.baseLevel) * 4);
    },
    orderedRecs() {
      const list = this.visible[this.activeCat] || [];
      return list
        .map((rec, idx) => ({ rec, idx }))
        .sort((a, b) => {
          if (a.rec.done !== b.rec.done) return a.rec.done ? 1 : -1;
          if (a.rec.done) return (b.rec.doneAt || 0) - (a.rec.doneAt || 0);
          return (b.rec.value || 0) - (a.rec.value || 0);
        });
    },
    totalCount() {
      return (this.visible[this.activeCat] || []).length;
    },
    doneCount() {
      return (this.visible[this.activeCat] || []).filter((r) => r.done).length;
    },
    suggestions() {
      const all = this.visible.flat().filter((r) => !r.done);
      all.sort((a, b) => (b.value || 0) - (a.value || 0));
      return all.slice(0, 3).map((r) => `How do I "${r.title}"?`);
    },
    finances() {
      const d = this.data || {};
      const modsRaw = d.modules || {};
      // Modules may arrive as an array (keyed by .key) or as an object map.
      const find = (k) => {
        if (Array.isArray(modsRaw)) return modsRaw.find((m) => m.key === k) || {};
        return modsRaw[k] || {};
      };
      const num = (v) => Number(v) || 0;
      const nw = d.net_worth || {};
      const prot = find('protection');
      const sav = find('savings');
      const ret = find('retirement');
      const trend = num(nw.trend);

      // Savings — emergency-fund runway as a bar (out of a 6-month target).
      const efMonths = num(sav.emergency_fund_months);
      const efTarget = 6;
      const savValue = sav.total_savings != null ? sav.total_savings : sav.value;

      // Retirement — projected income vs target as a bar.
      const projected = num(ret.projected_income);
      const target = num(ret.target_income);
      const retPct = target > 0 ? Math.min(100, Math.round((projected / target) * 100)) : 0;
      const retValue = ret.income_gap != null ? ret.income_gap : ret.value;

      return [
        {
          key: 'net_worth', label: 'Net worth', tone: 'horizon', icon: ICON.netWorth,
          value: this.fmt(nw.total), route: '/net-worth',
          viz: 'donut',
          progress: 72, vizNum: (trend >= 0 ? '+' : '') + trend + '%', vizCap: 'Trend',
          caption: this.fmt(nw.assets) + ' assets',
        },
        {
          key: 'protection', label: 'Protection', tone: 'raspberry', icon: ICON.shield,
          value: this.fmt(prot.value != null ? prot.value : prot.total_coverage), route: '/protection',
          viz: 'donut',
          progress: (prot.value || prot.total_coverage) > 0 ? 85 : 0,
          vizNum: (prot.value || prot.total_coverage) > 0 ? 'Active' : 'None', vizCap: 'Cover',
          caption: (prot.value || prot.total_coverage) > 0 ? 'Cover in place' : 'Add your cover',
        },
        {
          key: 'savings', label: 'Savings', tone: 'spring', icon: ICON.card,
          value: this.fmt(savValue), route: '/savings',
          viz: 'bar',
          barFill: efTarget > 0 ? Math.min(100, Math.round((efMonths / efTarget) * 100)) : 0,
          barValue: efMonths ? (Math.round(efMonths * 10) / 10) : '0',
          barUnit: '/ ' + efTarget + ' months',
          caption: efMonths >= efTarget ? 'Emergency fund on track' : (efMonths > 0 ? 'Building your fund' : 'Start your emergency fund'),
        },
        {
          key: 'retirement', label: 'Retirement', tone: 'violet', icon: ICON.clock,
          value: this.fmt(retValue), route: '/retirement',
          viz: 'bar',
          barFill: retPct,
          barValue: retPct + '%',
          barUnit: 'of target',
          caption: target > 0 ? 'Towards your target' : 'Plan your retirement',
        },
      ];
    },
  },
  watch: {
    level(newLevel, oldLevel) {
      if (newLevel > oldLevel) this.celebrate(newLevel);
    },
  },
  methods: {
    fmt(n) {
      return '£' + Math.round(Number(n) || 0).toLocaleString('en-GB');
    },
    actionsForLevel(level) {
      return Math.min(10, 3 + (level - 1) * 2);
    },
    statFor(ci) {
      const list = this.visible[ci] || [];
      const top = list.filter((r) => !r.done).sort((a, b) => (b.value || 0) - (a.value || 0))[0];
      return top && top.value ? this.fmt(top.value) : '—';
    },
    async load() {
      this.loading = true;
      this.error = '';
      try {
        const res = await apiGet('/api/v1/mobile/dashboard', store.token);
        if (!res.ok) {
          this.error = 'We could not load your dashboard. Please try again.';
          return;
        }
        const d = res.data?.data || res.data || {};
        this.data = d;
        const recs = d.recommendations || {};
        this.cats.forEach((cat, ci) => {
          const list = (recs[cat.key] || []).map((r) => ({
            id: r.id,
            title: r.title,
            meta: r.meta,
            value: Number(r.value) || 0,
            module: r.module,
            done: !!r.done,
            doneAt: 0,
            completing: false,
            skipping: false,
            entering: false,
          }));
          this.pools[ci] = list;
          this.visible[ci] = list.slice(0, MAX_VISIBLE);
          this.poolCursor[ci] = this.visible[ci].length;
        });
        // Seed level baseline so percentile climbs from the server figure.
        this.basePercentile = d.percentile ?? 57;
        this.baseLevel = this.level;   // level computed from seeded done flags
      } catch (e) {
        this.error = 'Network error. Please try again.';
      } finally {
        this.loading = false;
      }
    },
    selectCard(ci) {
      this.activeCat = ci;
    },
    toggleRec(idx) {
      const rec = this.visible[this.activeCat][idx];
      if (!rec) return;
      if (rec.done) {
        rec.done = false;
        rec.doneAt = 0;
        return;
      }
      // Show the tick, hold, then fade + re-order.
      rec.done = true;
      rec.doneAt = ++this.doneCounter;
      rec.completing = false;
      window.setTimeout(() => {
        rec.completing = true;
        window.setTimeout(() => {
          rec.completing = false;
        }, 320);
      }, 450);
    },
    skipRec(idx) {
      const rec = this.visible[this.activeCat][idx];
      if (!rec) return;
      rec.skipping = true;
      window.setTimeout(() => {
        const pool = this.pools[this.activeCat];
        const cursor = this.poolCursor[this.activeCat];
        if (cursor < pool.length) {
          const next = pool[cursor];
          next.entering = true;
          this.visible[this.activeCat].splice(idx, 1, next);
          this.poolCursor[this.activeCat] = cursor + 1;
          window.setTimeout(() => { next.entering = false; }, 360);
        } else {
          this.visible[this.activeCat].splice(idx, 1);
        }
      }, 260);
    },
    celebrate(level) {
      this.pulsing = false;
      this.$nextTick(() => { this.pulsing = true; });
      window.setTimeout(() => { this.pulsing = false; }, 900);
      const frame = this.$refs.root;
      if (!frame) return;
      const layer = document.createElement('div');
      layer.className = 'md-confetti';
      const pieces = 80;
      for (let i = 0; i < pieces; i += 1) {
        const s = document.createElement('span');
        s.className = 'md-confetti__piece';
        s.style.left = Math.round((i / pieces) * 100) + '%';
        s.style.background = CONFETTI_COLORS[i % CONFETTI_COLORS.length];
        s.style.animationDelay = ((i % 10) * 40) + 'ms';
        s.style.animationDuration = (1400 + (i % 7) * 180) + 'ms';
        s.style.setProperty('--drift', (((i % 11) - 5) * 14) + 'px');
        s.style.setProperty('--rot', ((i % 2 ? 1 : -1) * (180 + (i % 5) * 120)) + 'deg');
        if (i % 3 === 0) s.style.borderRadius = '50%';
        layer.appendChild(s);
      }
      const banner = document.createElement('div');
      banner.className = 'md-confetti__banner';
      banner.textContent = 'Level ' + level + '!';
      layer.appendChild(banner);
      frame.appendChild(layer);
      window.setTimeout(() => { layer.remove(); }, 2600);
    },
    goto(route) {
      this.closeDrawer();
      if (this.$route.path !== route) this.$router.push(route);
    },
    signOut() {
      store.logout();
      this.$router.push('/login');
    },
    openDrawer() {
      this.drawerMounted = true;
      this.$nextTick(() => { this.drawerOpen = true; });
    },
    closeDrawer() {
      this.drawerOpen = false;
      window.setTimeout(() => { this.drawerMounted = false; }, 300);
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
    // First-open initialiser: onboarding-incomplete users (incl. funnel
    // arrivals) start the onboarding conversation — Fyn greets, recaps their
    // funnel answers and asks for the rest via bubbles. Everyone else gets a
    // short greeting and free chat.
    initFyn() {
      if (this.fynStarted) return;
      this.fynStarted = true;
      if (this.onboardingActive) {
        this.startOnboarding();
      } else if (!this.messages.length) {
        this.messages.push({ role: 'fyn', text: `Hi ${this.firstName}. What would you like to look at?` });
      }
    },
    async startOnboarding() {
      if (this.sending) return;
      this.sending = true;
      this.resumeId = null;
      const cursor = { reply: { role: 'fyn', text: '', bubbles: [] }, got: false, navigation: null };
      this.messages.push(cursor.reply);
      this.$nextTick(this.scrollFyn);
      try {
        await apiStream(
          '/api/ai-chat/onboarding/start',
          {},
          store.token,
          (piece) => { if (piece) cursor.got = true; cursor.reply.text += piece; this.$nextTick(this.scrollFyn); },
          (ev) => this.handleFynEvent(cursor, ev),
        );
        if (this.resumeId) {
          // Mid-onboarding resume — replace the placeholder with the transcript.
          await this.loadConversation(this.resumeId);
        } else if (!cursor.got && !(cursor.reply.bubbles && cursor.reply.bubbles.length)) {
          cursor.reply.text = `Hi ${this.firstName}. Let's get your plan started — what would you like to look at?`;
        }
      } catch (e) {
        cursor.reply.text = 'Sorry, I had trouble starting just now. Please try again.';
      } finally {
        this.sending = false;
        this.$nextTick(this.scrollFyn);
      }
    },
    // Non-text onboarding SSE events. Text arrives via the onDelta path into
    // `cursor.reply`. A single user message can stream MULTIPLE onboarding turns
    // (a capture acknowledgement, then the next prompt) separated by an
    // `onboarding_advance` event — so we split into a fresh bubble on each
    // advance, otherwise the ack and the next question merge into one message.
    handleFynEvent(cursor, ev) {
      if (!ev || !ev.type) return;
      if ((ev.type === 'conversation_created' || ev.type === 'resume') && ev.conversation_id) {
        this.conversationId = ev.conversation_id;
        // 'resume' means the user is mid-onboarding from a prior session —
        // startOnboarding loads the existing transcript instead of a first turn.
        if (ev.type === 'resume') this.resumeId = ev.conversation_id;
        return;
      }
      if (ev.type === 'onboarding_advance') {
        // New onboarding turn — open a fresh bubble so the just-streamed
        // acknowledgement and the upcoming prompt render as separate messages
        // (matching how a resumed transcript renders them).
        if (cursor.reply.text || (cursor.reply.bubbles && cursor.reply.bubbles.length)) {
          cursor.reply = { role: 'fyn', text: '', bubbles: [] };
          this.messages.push(cursor.reply);
        }
        return;
      }
      if (ev.type === 'navigation' && ev.route_path) {
        // Terminal turn (e.g. campaign → /tax-strategy). Captured here; the
        // caller decides how the mobile surface presents it after the stream.
        cursor.navigation = ev.route_path;
        return;
      }
      if (ev.type === 'quick_replies') {
        // A bubbles turn. If the current bubble already carries streamed text
        // (an acknowledgement from the prior capture), open a fresh bubble for
        // the prompt + choices.
        if (cursor.reply.text) {
          cursor.reply = { role: 'fyn', text: '', bubbles: [] };
          this.messages.push(cursor.reply);
        }
        cursor.got = true;
        if (ev.prompt_text) cursor.reply.text = ev.prompt_text;
        cursor.reply.bubbles = Array.isArray(ev.bubbles) ? ev.bubbles : [];
        this.$nextTick(this.scrollFyn);
      }
    },
    // Load an existing onboarding conversation's transcript (resume). Only the
    // last assistant turn keeps its bubbles — earlier turns are already answered.
    async loadConversation(id) {
      try {
        const res = await apiGet(`/api/ai-chat/conversations/${id}`, store.token);
        const list = res.data?.data?.messages || res.data?.messages || [];
        const mapped = list.map((m) => ({
          role: m.role === 'user' ? 'user' : 'fyn',
          text: m.content || '',
          bubbles: (m.metadata && Array.isArray(m.metadata.bubbles)) ? m.metadata.bubbles : [],
        }));
        let lastFyn = -1;
        mapped.forEach((m, i) => { if (m.role === 'fyn') lastFyn = i; });
        mapped.forEach((m, i) => { if (i !== lastFyn) m.bubbles = []; });
        if (mapped.length) this.messages = mapped;
      } catch (e) { /* keep whatever is shown */ }
    },
    chooseBubble(bubble) {
      if (this.sending || !bubble) return;
      this.send(bubble.label || bubble.id);
    },
    openRecChat(rec) {
      this.openFyn();
      this.send(`How do I "${rec.title}"?`);
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
      // Prior bubbles are now answered — remove them so they can't be re-tapped.
      this.messages.forEach((m) => { if (m.bubbles) m.bubbles = []; });
      this.messages.push({ role: 'user', text });
      const cursor = { reply: { role: 'fyn', text: '', bubbles: [] }, got: false, navigation: null };
      this.messages.push(cursor.reply);
      this.$nextTick(this.scrollFyn);
      try {
        const cid = await this.ensureConversation();
        if (!cid) {
          cursor.reply.text = 'Sorry, I could not start a conversation just now.';
          return;
        }
        await apiStream(
          `/api/ai-chat/conversations/${cid}/messages`,
          { message: text, current_route: '/dashboard' },
          store.token,
          (piece) => {
            if (piece) cursor.got = true;
            cursor.reply.text += piece;
            this.$nextTick(this.scrollFyn);
          },
          (ev) => this.handleFynEvent(cursor, ev),
        );
        if (!cursor.got && !(cursor.reply.bubbles && cursor.reply.bubbles.length)) {
          cursor.reply.text = 'Sorry, I had trouble responding just now.';
        } else if (!cursor.reply.text && !(cursor.reply.bubbles && cursor.reply.bubbles.length)) {
          // Trailing empty bubble (e.g. an advance opened one but the turn ended
          // on a navigation) — drop it so no blank message lingers.
          const idx = this.messages.indexOf(cursor.reply);
          if (idx !== -1) this.messages.splice(idx, 1);
        }
        if (cursor.navigation) this.handleOnboardingNavigation(cursor.navigation);
      } catch (e) {
        cursor.reply.text = 'Sorry, something went wrong. Please try again.';
      } finally {
        this.sending = false;
        this.$nextTick(this.scrollFyn);
      }
    },
    // Terminal onboarding navigation (e.g. the savetax campaign → /tax-strategy).
    // The campaign terminal completes onboarding and asks to "show your tax
    // position"; route the user to the mobile Tax Strategy view. Close the Fyn
    // overlay first so the view is in front. Unknown desktop-only routes are
    // ignored (the chat thread still carries the result).
    handleOnboardingNavigation(routePath) {
      if (routePath !== '/tax-strategy') return;
      this.closeFyn();
      this.$nextTick(() => {
        if (this.$route.path !== '/tax-strategy') this.$router.push('/tax-strategy');
      });
    },
    scrollFyn() {
      const b = this.$refs.fynBody;
      if (b) b.scrollTop = b.scrollHeight;
    },
    // Populate store.user so the greeting / drawer show the real name. The mobile
    // store only sets user during the in-app verify flow; on a token-only arrival
    // (e.g. returning session) it's null, so fetch it from the authenticated API.
    async loadUser() {
      if (store.user || !store.token) return;
      try {
        const res = await apiGet('/api/auth/user', store.token);
        if (res.ok) {
          store.user = res.data?.data?.user || res.data?.user || res.data?.data || null;
        }
      } catch (e) {
        /* non-fatal — greeting falls back to a generic label */
      }
    },
  },
  mounted() {
    this.loadUser();
    this.load();
  },
};
</script>

<style src="./dashboard.css"></style>
