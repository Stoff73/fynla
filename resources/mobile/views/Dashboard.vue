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
          <button type="button" class="md-level md-level--button" aria-labelledby="md-level-heading" @click="goToAchievements">
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
              <h2 class="md-level__heading" id="md-level-heading">{{ actionsCompleted }} of {{ actionsTotal }} actions complete</h2>
              <p class="md-level__sub">Complete actions to reach <strong>Level {{ level + 1 }}</strong>.</p>
            </div>
          </button>
        </div>

        <!-- Callout: rank statement + focus-area carousel + actions -->
        <div class="md-callout" role="note">
          <div class="md-callout__top">
            <p class="md-callout__levelup">LEVEL<br>UP</p>
            <div class="md-callout__top-copy">
              <p class="md-callout__lead">You're ahead of <strong>{{ percentile }}%</strong> of people</p>
              <p class="md-callout__sub">Complete your actions to get further ahead, level up and change your financial future</p>
            </div>
          </div>

          <div class="md-callout__carousel">
            <!-- Focus-area cards: swipeable (scroll-snap) + tappable -->
            <div class="md-focus" ref="focusStrip" role="tablist" aria-label="Focus areas" @scroll.passive="onFocusScroll">
              <button
                v-for="(area, ai) in focusAreas"
                :key="area.key"
                type="button"
                class="md-focus__card"
                :class="{ 'is-active': activeArea === ai, 'is-locked': area.locked }"
                role="tab"
                :aria-selected="activeArea === ai ? 'true' : 'false'"
                @click="selectArea(ai)"
              >
                <span class="md-focus__label">{{ area.label }}</span>
                <span class="md-focus__stat">{{ area.stat }}</span>
                <span v-if="area.locked" class="md-focus__lock">Locked</span>
              </button>
            </div>

            <!-- Dots -->
            <div class="md-callout__dots" role="tablist" aria-label="Focus area navigation">
              <button
                v-for="(area, ai) in focusAreas"
                :key="area.key"
                type="button"
                class="md-callout__dot"
                :class="{ 'is-active': activeArea === ai }"
                :aria-label="area.label"
                :aria-selected="activeArea === ai ? 'true' : 'false'"
                @click="selectArea(ai)"
              ></button>
            </div>

            <!-- Actions for the selected card -->
            <section class="md-recs is-open" :aria-label="activeCard.label + ' actions'">
              <p v-if="!activeCard.locked && activeRecCount" class="md-recs__count">{{ activeDoneCount }} of {{ activeRecCount }} done</p>

              <div class="md-recs__body">
                <ul class="md-recs__list" aria-live="polite">
                  <li v-if="!activeActions.length" class="md-rec md-rec--empty">
                    <span class="md-rec__action"><span class="md-rec__text"><span class="md-rec__title">You're on track here — nothing to action right now.</span></span></span>
                  </li>
                  <li
                    v-for="item in activeActions"
                    :key="item.id"
                    class="md-rec"
                    :class="{ 'is-done': item.done, 'is-unlock': item.type === 'unlock' }"
                  >
                    <button
                      v-if="item.type === 'recommendation'"
                      type="button"
                      class="md-rec__check-btn"
                      :aria-pressed="item.done ? 'true' : 'false'"
                      :aria-label="item.done ? 'Mark as not done' : 'Mark complete'"
                      @click="toggleRec(item)"
                    >
                      <span class="md-rec__check" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" width="14" height="14"><path stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg></span>
                    </button>
                    <a href="#" class="md-rec__action" @click.prevent="onActionTap(item)">
                      <span class="md-rec__text">
                        <span class="md-rec__title">{{ item.title }}</span>
                        <span class="md-rec__meta">{{ item.meta }}</span>
                      </span>
                      <svg class="md-rec__chevron" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </a>
                  </li>
                </ul>
                <a v-if="activeCard.key !== 'top' && !activeCard.locked" href="#" class="md-recs__view-all" @click.prevent="goto('/' + (activeCard.key === 'estate' ? 'estate' : activeCard.key))">View all {{ activeCard.label.toLowerCase() }}</a>
              </div>
            </section>
          </div>
        </div>

        <!-- Today's insight (Fyn) — surfaced from the dashboard payload's
             fyn_insight (no extra request). Plain text, no icons. -->
        <section v-if="fynInsight" class="md-insight" aria-labelledby="md-insight-heading">
          <div class="md-section-head">
            <h3 class="md-section-head__title" id="md-insight-heading">Today's insight</h3>
          </div>
          <p class="md-insight__text">{{ fynInsight }}</p>
        </section>

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
              :class="[`md-panel--${p.tone}`, { 'md-panel--wide': p.wide }]"
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

    <!-- Milestone celebration — a financial milestone the user just crossed,
         with a Share action. Plain text, dismissable, one at a time. -->
    <div v-if="milestoneToast" class="md-milestone" role="status" aria-live="polite">
      <div class="md-milestone__body">
        <p class="md-milestone__title">Milestone reached</p>
        <p class="md-milestone__label">{{ milestoneToast.label }}</p>
      </div>
      <div class="md-milestone__actions">
        <button type="button" class="md-milestone__share" @click="shareMilestone">Share</button>
        <button type="button" class="md-milestone__dismiss" aria-label="Dismiss" @click="milestoneToast = null">Dismiss</button>
      </div>
    </div>

    <!-- Level-up celebration — shared fireworks takeover (Rule #12 carve-out).
         Driven by the gamification store's pendingCelebration: set after a
         level_up SSE frame (post-reply) or a missed celebration on next open. -->
    <GamificationCelebration
      v-if="celebration"
      :key="celebration.level"
      :level="celebration.level"
      :level-name="celebration.level_name"
      :next-actions="celebration.next_actions"
      @dismiss="onCelebrationDismiss"
    />

    <!-- Onboarding nudge — gently points a funnel/incomplete user to finish
         their personalised tax plan with Fyn. Tapping opens Fyn; "Later"
         dismisses it for the session. Hidden once Fyn is open or onboarded. -->
    <div v-if="showFynNudge" class="md-fyn-nudge">
      <button type="button" class="md-fyn-nudge__cta" @click="openFyn">Finish your personalised tax plan with Fyn</button>
      <button type="button" class="md-fyn-nudge__later" aria-label="Dismiss" @click="nudgeDismissed = true">Later</button>
    </div>

    <!-- Fyn unlock nudge: when a high-value area is KYC-gated, Fyn offers to
         collect the missing details. Dismissible; tapping opens the chat
         pre-seeded (the user's choice). Plain text only — Rule #15. -->
    <div v-if="showUnlockBubble" class="md-fyn-nudge md-fyn-nudge--unlock">
      <button type="button" class="md-fyn-nudge__cta" @click="openFynForCapture(topUnlock.module)">{{ unlockBubbleText }}</button>
      <button type="button" class="md-fyn-nudge__later" aria-label="Dismiss" @click="unlockBubbleDismissed = true">Not now</button>
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
        <div v-for="section in navSections" :key="section.group" class="md-drawer__section">
          <p class="md-drawer__group">{{ section.group }}</p>
          <a v-for="link in section.links" :key="link.slug" href="#" class="md-drawer__link" @click.prevent="goto(link.route)">
            <span class="md-drawer__icon" aria-hidden="true" v-html="link.icon"></span>
            <span class="md-drawer__label">{{ link.label }}</span>
          </a>
        </div>
        <div v-if="isAdmin" class="md-drawer__section">
          <p class="md-drawer__group">Admin</p>
          <a href="#" class="md-drawer__link" @click.prevent="gotoAdmin">
            <span class="md-drawer__icon" aria-hidden="true" v-html="adminIcon"></span>
            <span class="md-drawer__label">Admin Panel</span>
          </a>
        </div>
        <div class="md-drawer__section md-drawer__section--account">
          <a href="#" class="md-drawer__link" @click.prevent="shareReferral">
            <span class="md-drawer__icon" aria-hidden="true" v-html="shareIcon"></span>
            <span class="md-drawer__label">Share Fynla</span>
          </a>
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
          <!-- Onboarding bubble choices (quick_replies). Tapping sends the
               label, which the director matches back to the bubble. -->
          <div v-if="m.bubbles && m.bubbles.length" class="md-fyn__bubbles">
            <button
              v-for="b in m.bubbles"
              :key="b.id"
              type="button"
              class="md-fyn__bubble"
              :disabled="sending"
              @click="chooseBubble(b, m)"
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
// Mirrors resources/js/components/Gamification/GamificationCelebration.vue. The
// isolated mobile build (vite.mobile.config.js) aliases only '@m' -> resources/mobile
// (deliberately no '@' coupling to web code, for iOS-safety), so the /m bundle keeps
// its own copy. Keep the two in sync if the celebration changes.
import GamificationCelebration from '@m/components/GamificationCelebration.vue';

const ICON = {
  saveTax: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
  retirement: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
  savings: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>',
  netWorth: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>',
  shield: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>',
  card: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>',
  clock: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
  investment: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M7 12l3-3 3 3 4-4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/></svg>',
};

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
  name: 'MobileDashboard',
  components: { GamificationCelebration },
  data() {
    return {
      loading: true,
      error: '',
      data: null,
      // Focus-area carousel — one card per focus area (Top actions + modules),
      // each with its own action list. activeArea indexes the selected card.
      focusAreas: [],
      activeArea: 0,
      // Level wheel — fed from the gamification engine via the dashboard payload
      // (d.level.* + d.percentile). Single source of truth; no client recompute.
      level: 1,
      levelName: 'Starter',
      progressPercent: 0,
      actionsCompleted: 0,
      actionsTotal: 0,
      percentile: 57,
      pulsing: false,
      // drawer / fyn
      milestoneToast: null,
      drawerOpen: false,
      drawerMounted: false,
      fynOpen: false,
      fynMounted: false,
      fynStarted: false,
      nudgeDismissed: false,
      unlockBubbleDismissed: false,
      resumeId: null,
      conversationId: null,
      messages: [],
      draft: '',
      sending: false,
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
    // Drives the shared fireworks takeover. Set from a level_up SSE frame
    // (after Fyn's reply) or a missed celebration delivered by fetchStatus.
    celebration() {
      return store.pendingCelebration;
    },
    // Onboarding is "active" only when explicitly not completed (null/undefined
    // — e.g. user not yet loaded — must not trigger the onboarding chat).
    onboardingActive() {
      return store.user?.onboarding_completed === false;
    },
    showFynNudge() {
      return this.onboardingActive && !this.fynOpen && !this.nudgeDismissed;
    },
    // First KYC-gated area's unlock action (locked cards carry exactly one).
    topUnlock() {
      for (const area of this.focusAreas) {
        if (area.locked && area.actions && area.actions[0] && area.actions[0].type === 'unlock') {
          return area.actions[0];
        }
      }
      const top = (this.focusAreas[0] && this.focusAreas[0].actions) || [];
      return top.find((a) => a.type === 'unlock') || null;
    },
    showUnlockBubble() {
      return !this.onboardingActive && !this.fynOpen && !this.showFynNudge
        && !this.unlockBubbleDismissed && !!this.topUnlock;
    },
    unlockBubbleText() {
      return this.topUnlock ? `${this.topUnlock.meta} — Fyn can help` : '';
    },
    // Drawer nav grouped to mirror the web sidebar's sections. Only modules
    // that have a dedicated mobile view are linked (the web sidebar's other
    // items have no mobile screen yet). Labels match the web wording.
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
    // Admin section — only for admin users (matches the web sidebar's gated
    // Admin section). The link opens the desktop Admin Panel, which has no
    // mobile equivalent; `admin/*` is exempt from the phone→/m redirect.
    isAdmin() {
      return store.user?.is_admin === true;
    },
    adminIcon() {
      return NAV_ICON.admin;
    },
    // The level wheel reads engine-fed values directly from the dashboard
    // payload (see load()). The "X of Y actions complete" heading binds to
    // actionsCompleted / actionsTotal — a CLAUDE.md Rule #12-approved display.
    // The selected focus-area card (Top actions or a per-module card). Falls
    // back to an empty Top card so the template never reads from undefined.
    activeCard() {
      return this.focusAreas[this.activeArea] || { key: 'top', label: 'Top actions', locked: false, actions: [] };
    },
    activeActions() {
      return this.activeCard.actions || [];
    },
    activeDoneCount() {
      return this.activeActions.filter((a) => a.type === 'recommendation' && a.done).length;
    },
    activeRecCount() {
      return this.activeActions.filter((a) => a.type === 'recommendation').length;
    },
    suggestions() {
      const top = (this.focusAreas[0] && this.focusAreas[0].actions) || [];
      return top
        .filter((a) => a.type === 'recommendation' && !a.done)
        .slice(0, 3)
        .map((a) => `How do I "${a.title}"?`);
    },
    fynInsight() {
      return this.data?.fyn_insight || '';
    },
    shareIcon() {
      return NAV_ICON.share;
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

      // Investment — portfolio value as a full-width donut tile (5th panel).
      const inv = find('investment');
      const invValue = num(inv.portfolio_value != null ? inv.portfolio_value : inv.value);
      const invAccounts = num(inv.accounts_count);
      const invHoldings = num(inv.holdings_count);

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
        {
          key: 'investment', label: 'Investment', tone: 'horizon', icon: ICON.investment, wide: true,
          value: this.fmt(invValue), route: '/investment',
          viz: 'donut',
          progress: invValue > 0 ? 72 : 0,
          vizNum: invValue > 0 ? String(invAccounts) : '0',
          vizCap: invAccounts === 1 ? 'Account' : 'Accounts',
          caption: invValue > 0 ? `${invHoldings} ${invHoldings === 1 ? 'holding' : 'holdings'}` : 'Add your investments',
        },
      ];
    },
  },
  watch: {
    level(newLevel, oldLevel) {
      // Pulse the wheel when the engine-fed level climbs (e.g. on a fresh load
      // after a level-up). The full fireworks takeover is driven separately by
      // the shared GamificationCelebration via store.pendingCelebration.
      if (newLevel > oldLevel) this.pulseWheel();
    },
  },
  methods: {
    fmt(n) {
      return '£' + Math.round(Number(n) || 0).toLocaleString('en-GB');
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
        this.focusAreas = Array.isArray(d.focus_areas)
          ? d.focus_areas.map((area) => ({
              ...area,
              actions: (area.actions || []).map((a) => ({ ...a, done: !!a.done, busy: false })),
            }))
          : [];
        if (this.activeArea >= this.focusAreas.length) this.activeArea = 0;
        // Level wheel — read engine-fed values from the dashboard payload
        // (the single source of truth; MobileLevelService -> points engine).
        // d.level.* feeds the ring + the "X of Y actions complete" heading;
        // d.percentile feeds the "ahead of X% of people" rank statement.
        const lv = d.level || {};
        this.level = lv.level ?? 1;
        this.levelName = lv.level_name ?? 'Starter';
        this.progressPercent = lv.progress_percent ?? 0;
        this.actionsCompleted = lv.actions_completed ?? 0;
        this.actionsTotal = lv.actions_total ?? 0;
        this.percentile = d.percentile ?? 57;

        // Surface the single most significant newly-crossed milestone (each
        // fires once server-side). Highest net-worth threshold, else highest goal.
        const ms = d.new_milestones || [];
        if (ms.length) {
          const nw = ms.filter((m) => m.type === 'net_worth').sort((a, b) => b.threshold - a.threshold);
          const goal = ms.filter((m) => m.type === 'goal').sort((a, b) => b.threshold - a.threshold);
          this.milestoneToast = nw[0] || goal[0] || ms[0];
        }
      } catch (e) {
        this.error = 'Network error. Please try again.';
      } finally {
        this.loading = false;
      }
    },
    selectArea(i) {
      this.activeArea = i;
      // Slide the carousel to the chosen card (dots / card tap).
      const strip = this.$refs.focusStrip;
      const el = strip && strip.children[i];
      if (el) strip.scrollTo({ left: el.offsetLeft, behavior: 'smooth' });
    },
    // Sync the active card to the swipe position (one card per view).
    onFocusScroll() {
      const strip = this.$refs.focusStrip;
      if (!strip) return;
      const center = strip.scrollLeft + strip.clientWidth / 2;
      let best = 0;
      let bestDist = Infinity;
      Array.from(strip.children).forEach((el, i) => {
        const elCenter = el.offsetLeft + el.offsetWidth / 2;
        const dist = Math.abs(elCenter - center);
        if (dist < bestDist) { bestDist = dist; best = i; }
      });
      if (best !== this.activeArea) this.activeArea = best;
    },
    goToAchievements() {
      this.$router.push('/achievements');
    },
    // Tapping an action row: an unlock card sends the user into Fyn to capture
    // the missing module data; a recommendation opens a Fyn chat about it.
    onActionTap(item) {
      if (!item) return;
      if (item.type === 'unlock') {
        this.openFynForCapture(item.module);
      } else {
        this.openRecChat({ title: item.title });
      }
    },
    openFynForCapture(module) {
      const prompts = {
        protection: 'Help me add my protection cover details',
        savings: 'Help me add my savings details',
        investment: 'Help me add my investment details',
        retirement: 'Help me add my pension details',
        estate: 'Help me add my estate planning details',
        goals: 'Help me set a financial goal',
      };
      this.openFyn();
      this.send(prompts[module] || 'Help me add my financial details');
    },
    // Mark / unmark a recommendation action complete. Optimistic toggle, then
    // persist so the shared gamification engine awards points (mark-done ->
    // RecommendationTracking::markAsCompleted -> observer -> PointsService);
    // refresh status to sync the wheel. Reverts the toggle if the call fails.
    toggleRec(item) {
      if (!item || item.type !== 'recommendation') return;
      item.done = !item.done;
      if (item.id && store.token) {
        apiPost(`/api/recommendations/${item.id}/mark-done`, {
          module: item.module || 'general',
          recommendation_text: item.title || '',
        }, store.token)
          .then(() => store.fetchStatus())
          .then(() => {
            this.level = store.gamification.level;
            this.progressPercent = store.gamification.progressPercent;
          })
          .catch(() => { item.done = !item.done; });
      }
    },
    // Brief pulse on the level wheel. The full-screen fireworks takeover is
    // handled by the shared GamificationCelebration component (Rule #12 carve-out).
    pulseWheel() {
      this.pulsing = false;
      this.$nextTick(() => { this.pulsing = true; });
      window.setTimeout(() => { this.pulsing = false; }, 900);
    },
    // Dismissing the celebration acknowledges it server-side so it isn't
    // redelivered on next open.
    onCelebrationDismiss() {
      store.ack();
    },
    goto(route) {
      this.closeDrawer();
      if (this.$route.path !== route) this.$router.push(route);
    },
    // Leave the /m mobile SPA for the desktop Admin Panel (no mobile equivalent).
    // The desktop SPA reads its Sanctum token from sessionStorage('auth_token');
    // the /m app holds it in localStorage('m_scaffold_token'). The reliable
    // bridge is on the desktop side (mScaffoldBridge.js adopts the shared
    // localStorage token at boot) — iOS partitions cross-context sessionStorage,
    // so the seed below is only a best-effort fast-path for desktop browsers.
    // Navigation must ALWAYS target the TOP window: navigating the iframe would
    // load /admin inside the /m frame, where the in-frame guard bounces it back
    // to /m/app.
    gotoAdmin() {
      this.closeDrawer();
      const url = (import.meta.env.VITE_ROUTER_BASE || '/') + 'admin';
      try {
        const token = store.token || localStorage.getItem('m_scaffold_token');
        if (token && window.top && window.top !== window) {
          window.top.sessionStorage.setItem('auth_token', token);
        }
      } catch (e) { /* iOS partitioned storage — the desktop boot bridge covers it */ }
      (window.top || window).location.href = url;
    },
    // Share via the native share sheet (navigator.share) with a clipboard
    // fallback. Content comes from /api/v1/mobile/share/{type} — generic,
    // no monetary values (Rule #12). Silent no-op if the user cancels.
    async doShare(shareType) {
      try {
        const { ok, data } = await apiGet(`/api/v1/mobile/share/${shareType}`, store.token);
        if (!ok) return;
        const c = data?.data || data || {};
        const payload = { title: c.title || 'Fynla', text: c.text || '', url: c.url || 'https://fynla.org' };
        if (navigator.share) {
          await navigator.share(payload);
        } else if (navigator.clipboard) {
          await navigator.clipboard.writeText(`${payload.text} ${payload.url}`.trim());
        }
      } catch (e) { /* user cancelled / unsupported — no-op */ }
    },
    shareMilestone() {
      if (this.milestoneToast) this.doShare(this.milestoneToast.share_type);
    },
    shareReferral() {
      this.closeDrawer();
      this.doShare('app_referral');
    },
    async signOut() {
      // Revoke the bearer token server-side before clearing local state. /m is
      // bearer-only (no biometric here, native not shipping), so the usual
      // "local-only mobile logout" caveat does not apply — a full sign-out should
      // invalidate the Sanctum token on the server too. Best-effort: still clear
      // local state + redirect even if the call fails (expired token / offline).
      try {
        if (store.token) await apiPost('/api/auth/logout', {}, store.token);
      } catch (e) { /* best-effort — proceed to local clear regardless */ }
      store.logout();
      // Return to the CANONICAL funnel login (framed in /m), not a scaffold
      // screen. Full navigation so the main app's login renders in-frame.
      window.location.href = (import.meta.env.VITE_ROUTER_BASE || '/') + 'login';
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
    // Open the bug-report sheet from the Fyn chat, carrying the active
    // conversation so the report captures the transcript (e.g. a startup repeat).
    reportFynProblem() {
      store.openBugReport(this.conversationId);
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
          // Mid-onboarding resume — show the welcome-back re-engagement (a short
          // summary of where they left off + Continue / Something else), NOT the
          // full transcript. Streams the resume action into the same placeholder.
          await this.streamFynAction(this.resumeId, 'resume', cursor);
        } else if (!cursor.got && !(cursor.reply.bubbles && cursor.reply.bubbles.length)) {
          cursor.reply.text = `Hi ${this.firstName}. Let's get your plan started — what would you like to look at?`;
        }
        // Celebrate after the onboarding turn renders (level_up arrives post-`done`).
        if (cursor.levelUp) {
          store.queueCelebration(cursor.levelUp);
          this.pulseWheel();
        }
      } catch (e) {
        cursor.reply.text = 'Sorry, I had trouble starting just now. Please try again.';
      } finally {
        this.sending = false;
        this.$nextTick(this.scrollFyn);
      }
    },
    // Stream a director action (resume / continue / something_else) into the
    // given cursor's reply, rendering the turn it produces — e.g. the welcome-
    // back summary + Continue / Something else bubbles on resume, or the next
    // onboarding state on continue. Mobile resume uses this instead of replaying
    // the full transcript so a returning user sees a summary, not a wall of
    // historical answers.
    async streamFynAction(conversationId, action, cursor) {
      cursor.got = false;
      cursor.reply.text = '';
      cursor.reply.bubbles = [];
      try {
        await apiStream(
          `/api/ai-chat/conversations/${conversationId}/action`,
          { action },
          store.token,
          (piece) => { if (piece) cursor.got = true; cursor.reply.text += piece; this.$nextTick(this.scrollFyn); },
          (ev) => this.handleFynEvent(cursor, ev),
        );
      } catch (e) {
        if (!cursor.got && !(cursor.reply.bubbles && cursor.reply.bubbles.length)) {
          cursor.reply.text = 'Sorry, I had trouble loading that just now. Please try again.';
        }
      }
    },
    // Run a resume action bubble (Continue / Something else): stream the turn it
    // produces into a fresh Fyn message.
    async runFynAction(action) {
      if (this.sending || !this.conversationId) return;
      this.sending = true;
      const cursor = { reply: { role: 'fyn', text: '', bubbles: [] }, got: false };
      this.messages.push(cursor.reply);
      this.$nextTick(this.scrollFyn);
      try {
        await this.streamFynAction(this.conversationId, action, cursor);
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
      if (ev.type === 'level_up') {
        // A write this turn crossed a level threshold. The frame arrives AFTER
        // `done`, so the reply is already on screen. Stash it; the caller fires
        // the celebration once the stream settles so we never interrupt mid-reply.
        cursor.levelUp = {
          level: ev.level,
          level_name: ev.level_name,
          next_actions: ev.next_actions || [],
        };
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
        // Resume re-engagement bubbles (Continue / Something else) are director
        // actions, not onboarding answers — flag them so chooseBubble routes
        // them to the action endpoint instead of sending the label as a message.
        cursor.reply.actionBubbles = ev.action_bubbles === true;
        this.$nextTick(this.scrollFyn);
      }
    },
    chooseBubble(bubble, message) {
      if (this.sending || !bubble) return;
      // Resume re-engagement bubbles (Continue / Something else) are director
      // actions — route to the action endpoint and consume the bubbles so they
      // can't be re-tapped. Regular onboarding bubbles send their label.
      if (message && message.actionBubbles) {
        message.bubbles = [];
        this.runFynAction(bubble.id);
        return;
      }
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
        // Celebrate AFTER the reply has rendered (the level_up frame arrives
        // after `done`). Queueing here, post-stream, guarantees the fireworks
        // never interrupt Fyn mid-reply — the completed reply stays beneath.
        if (cursor.levelUp) {
          store.queueCelebration(cursor.levelUp);
          this.pulseWheel();
        }
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
    // Deliver any celebration missed since last open (server-persisted
    // pending_celebration_level surfaced via GET /api/gamification/status).
    store.fetchStatus();
  },
};
</script>

<style src="./dashboard.css"></style>
