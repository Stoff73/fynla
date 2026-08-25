import { describe, expect, it, vi } from 'vitest';
import {
  shouldShowUpgradeEntry,
  subscriptionOptionsLocation,
} from '@/utils/subscriptionNavigation';
import {
  shouldShowMobileUpgrade,
  upgradeMixin,
} from '../../../resources/mobile/mixins/upgrade.js';

describe('subscription navigation', () => {
  it('uses the canonical subscription options route', () => {
    expect(subscriptionOptionsLocation()).toEqual({
      path: '/settings/subscription',
      query: { openPricing: '1' },
    });
  });

  it('keeps /m upgrades inside the mobile subscription flow', () => {
    const push = vi.fn();

    upgradeMixin.methods.goUpgrade.call({
      paidUpgradeAvailable: true,
      $router: { push },
    });

    expect(push).toHaveBeenCalledWith('/subscription');
  });

  it('shows paid entry points only to Free users when payments are enabled', () => {
    expect(shouldShowUpgradeEntry({ tier: 'free', payment_enabled: true }, false)).toBe(true);
    expect(shouldShowUpgradeEntry({ tier: 'premium', payment_enabled: true }, false)).toBe(false);
    expect(shouldShowUpgradeEntry({ tier: 'free', payment_enabled: true }, true)).toBe(false);
    expect(shouldShowUpgradeEntry({ tier: 'free', payment_enabled: false }, false)).toBe(false);
    expect(shouldShowUpgradeEntry(null, false)).toBe(false);
  });

  it('uses the same payment and tier visibility contract on /m', () => {
    expect(shouldShowMobileUpgrade({ tier: 'free', payment_enabled: true })).toBe(true);
    expect(shouldShowMobileUpgrade({ tier: 'premium', payment_enabled: true })).toBe(false);
    expect(shouldShowMobileUpgrade({ tier: 'free', payment_enabled: false })).toBe(false);
    expect(shouldShowMobileUpgrade(null)).toBe(false);
  });
});
