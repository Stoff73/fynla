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

  it('renders in both of the web dashboard layouts, not just the wide one', () => {
    // The file carries a narrow and a wide block, both in the DOM and swapped by
    // media query. A card in only one of them is invisible at the other width.
    const renders = dashboard.match(/<TrustsOverviewCard\s*\/>/g) || [];
    expect(renders).toHaveLength(2);
  });

  it('is gated on the capability the trusts endpoint enforces', () => {
    // /api/estate/trusts sits behind `estate.full`. Without the gate every user
    // without it takes a 403 on each dashboard load and is shown an empty card
    // for a module they cannot open.
    expect(dashboard).toContain("hasCapability']('estate')");
    expect(dashboard).toContain('v-if="showTrusts"');
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
