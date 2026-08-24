import { describe, it, expect } from 'vitest';
import fs from 'node:fs';
import path from 'node:path';

const source = fs.readFileSync(
  path.resolve(process.cwd(), 'resources/js/views/Settings/FamilySettings.vue'),
  'utf8',
);

/**
 * W-0347 — the consent panel has to be reachable, and it has to not break the
 * screen it sits on.
 *
 * Both assertions come from live browser failures, not from theory:
 *
 * 1. SpouseDataSharing was written complete and never mounted anywhere, while
 *    the notification email linked to a route that did not exist. Nobody could
 *    grant consent, so the backend forged it. If this component is ever
 *    unmounted again, the forging comes back with it.
 *
 * 2. Mounted AFTER the family-members card, it hit-tested on top of the open
 *    FamilyMemberFormModal and swallowed clicks on Add Family Member — the form
 *    could be filled and never submitted. The modal is `fixed z-10` but lives
 *    INSIDE that card, which establishes its own stacking context, so the
 *    modal's z-10 is trapped at the card's level. Raising z-index on the panel
 *    does not help; ordering is the fix.
 *
 * A DOM-mount test would need AppLayout, the router, the whole Vuex store and
 * real layout, and would still not reproduce a stacking bug in jsdom, which has
 * no layout engine. The ordering IS the contract, so the source order is what
 * is asserted.
 */
describe('FamilySettings — spouse data sharing panel', () => {
  it('mounts SpouseDataSharing', () => {
    expect(source).toContain('<SpouseDataSharing />');
    expect(source).toContain("import SpouseDataSharing from '@/components/UserProfile/SpouseDataSharing.vue'");
    expect(source).toMatch(/components:\s*\{[^}]*SpouseDataSharing/);
  });

  it('renders it BEFORE the family-members card', () => {
    const panelAt = source.indexOf('<SpouseDataSharing />');
    const cardAt = source.indexOf('<FamilyMembers v-else />');

    expect(panelAt).toBeGreaterThan(-1);
    expect(cardAt).toBeGreaterThan(-1);
    expect(panelAt).toBeLessThan(cardAt);
  });
});
