import { describe, expect, it } from 'vitest';

import { primaryNavigationSections } from '../navigationModel.js';

describe('mobile primary navigation model', () => {
  it('places Conversation History immediately after Achievements', () => {
    const overview = primaryNavigationSections.find((section) => section.group === 'Overview');

    expect(overview.links.map((link) => link.label)).toEqual([
      'Dashboard',
      'Achievements',
      'Conversation History',
    ]);
    expect(overview.links[2]).toMatchObject({
      slug: 'conversation_history',
      route: '/conversation-history',
    });
    expect(Object.isFrozen(primaryNavigationSections)).toBe(true);
  });
});
