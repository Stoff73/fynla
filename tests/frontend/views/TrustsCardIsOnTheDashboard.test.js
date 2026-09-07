import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const read = (p) => readFileSync(resolve(process.cwd(), p), 'utf8');

/**
 * W-0538. `TrustsOverviewCard.vue` had no importers anywhere in the repo — its
 * only reference was its own `name` property — so W-0045's palette fix to it
 * reached no screen and nobody noticed for a fortnight.
 *
 * These read the file rather than mounting, because the defect was the ABSENCE
 * of any render path: a mounted-component test would have passed throughout.
 */
describe('the trusts overview card is reachable', () => {
  const dashboard = read('resources/js/views/GamifiedDashboard.vue');

  it('is imported and registered by the web dashboard', () => {
    expect(dashboard).toContain("import TrustsOverviewCard from '@/components/Trusts/TrustsOverviewCard.vue'");
    expect(dashboard).toContain('components: { TrustsOverviewCard }');
  });

  it('renders exactly once, because the card fetches on mount', () => {
    // Both layout blocks are in the DOM and swapped by media query, so a card
    // placed in each mounts TWICE and makes two API calls per dashboard load.
    // Measured on csjones: two GETs of /api/estate/trusts on one page view.
    const renders = dashboard.match(/<TrustsOverviewCard\s*\/>/g) || [];
    expect(renders).toHaveLength(1);
  });

  it('is gated on the capability the endpoint actually enforces', () => {
    // /api/estate/trusts sits behind `estate.full` -> TeaserGate::isFull(), which
    // has NO admin or preview bypass. `hasCapability` mirrors allows(), which
    // does — so gating on it showed the card to an admin the API then 403'd.
    expect(dashboard).toContain("hasFullCapability']('estate')");
    expect(dashboard).not.toContain("hasCapability']('estate')");
    // The single instance sits outside both layout blocks, so it carries their
    // `!isEmpty` guard as well as the capability one.
    expect(dashboard).toMatch(/v-if="!isEmpty && showTrusts"/);
  });

  it('carries no icon, because a dashboard card is a banned surface', () => {
    // Rule 15. The card had never rendered, so its info-banner icon lands new
    // the moment it is wired in, and forward-only means it complies now.
    const card = read('resources/js/components/Trusts/TrustsOverviewCard.vue');
    const banner = card.slice(card.indexOf('class="info-banner"'), card.indexOf('</div>', card.indexOf('class="info-banner"')));
    expect(banner).not.toContain('<svg');
  });

  it('leaves /m alone — trusts are reached there through the nav, not a card', () => {
    // CSJ, 2026-09-04. Rule 19 is satisfied by a deliberate exclusion recorded
    // here, not by silence.
    const mobileRouter = read('resources/mobile/router.js');
    expect(mobileRouter).not.toContain('TrustsOverviewCard');
  });
});

describe('the auth capability getters answer two different questions', () => {
  // `hasCapability` mirrors TeaserGate::allows() — admin and preview bypass.
  // `hasFullCapability` mirrors TeaserGate::isFull() — the matrix alone. Screens
  // must gate on whichever one the endpoint behind them enforces.
  const getters = () => {
    const mod = {
      hasCapability: (state) => (key) => state.user?.is_admin === true
        || state.user?.is_preview_user === true
        || state.tierFlags?.capabilities?.[key] === 'full',
      hasFullCapability: (state) => (key) => state.tierFlags?.capabilities?.[key] === 'full',
    };
    return mod;
  };

  it('an admin on a tier without the capability passes allows() but not isFull()', () => {
    const state = { user: { is_admin: true }, tierFlags: { capabilities: { estate: 'teaser' } } };
    expect(getters().hasCapability(state)('estate')).toBe(true);
    expect(getters().hasFullCapability(state)('estate')).toBe(false);
  });

  it('a preview persona without the capability behaves the same way', () => {
    const state = { user: { is_preview_user: true }, tierFlags: { capabilities: { estate: 'teaser' } } };
    expect(getters().hasCapability(state)('estate')).toBe(true);
    expect(getters().hasFullCapability(state)('estate')).toBe(false);
  });

  it('both agree when the tier itself grants it', () => {
    const state = { user: {}, tierFlags: { capabilities: { estate: 'full' } } };
    expect(getters().hasCapability(state)('estate')).toBe(true);
    expect(getters().hasFullCapability(state)('estate')).toBe(true);
  });
});
