import { describe, expect, it } from 'vitest';

import { forcedCampaignRedirect } from '@/router/onboardingRoutePolicy.js';

describe('onboarding wizard while a campaign is forced (CSJ 2026-09-25)', () => {
  it('sends the wizard to Fyn on the forced campaign', () => {
    expect(forcedCampaignRedirect({ onboarding_forced_campaign: 'savetax' })).toEqual({
      name: 'Dashboard',
      query: { openFyn: 'journey', from: 'savetax' },
    });
  });

  it('leaves the wizard reachable when nothing is forced', () => {
    expect(forcedCampaignRedirect({ onboarding_forced_campaign: null })).toBeNull();
    expect(forcedCampaignRedirect(null)).toBeNull();
  });
});
