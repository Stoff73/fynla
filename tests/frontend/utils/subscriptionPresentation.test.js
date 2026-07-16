import { describe, expect, it } from 'vitest';
import { getSubscriptionPresentation } from '@/utils/subscriptionPresentation';

const NOW = new Date('2026-07-15T12:00:00Z');

function status(overrides = {}) {
  return {
    has_subscription: false,
    tier: 'free',
    tier_display_name: 'Free',
    status: null,
    current_period_end: null,
    is_in_grace_period: false,
    is_terminal_paid: false,
    payment_enabled: true,
    ...overrides,
  };
}

describe('subscription presentation', () => {
  it('presents Free and provisional checkout as writable Free states', () => {
    expect(getSubscriptionPresentation(status(), NOW)).toEqual({
      state: 'free',
      label: 'Free',
      canWrite: true,
      showUpgrade: true,
    });

    expect(getSubscriptionPresentation(status({
      has_subscription: true,
      status: 'pending',
      plan: 'premium',
    }), NOW)).toEqual({
      state: 'pending',
      label: 'Free — payment pending',
      canWrite: true,
      showUpgrade: true,
    });
  });

  it('presents active and cancelled-inside-period Premium without an upgrade', () => {
    expect(getSubscriptionPresentation(status({
      has_subscription: true,
      tier: 'premium',
      tier_display_name: 'Premium',
      status: 'active',
      current_period_end: '2027-01-31T23:59:59Z',
    }), NOW)).toEqual({
      state: 'active',
      label: 'Premium',
      canWrite: true,
      showUpgrade: false,
    });

    expect(getSubscriptionPresentation(status({
      has_subscription: true,
      tier: 'premium',
      tier_display_name: 'Premium',
      status: 'cancelled',
      current_period_end: '2027-01-31T23:59:59Z',
    }), NOW)).toEqual({
      state: 'cancelled',
      label: 'Premium — ends 31 January 2027',
      canWrite: true,
      showUpgrade: false,
    });
  });

  it('uses the middleware period result for a payment issue', () => {
    expect(getSubscriptionPresentation(status({
      has_subscription: true,
      tier: 'premium',
      tier_display_name: 'Premium',
      status: 'past_due',
      current_period_end: '2027-01-31T23:59:59Z',
    }), NOW)).toEqual({
      state: 'past_due',
      label: 'Premium — payment issue',
      canWrite: true,
      showUpgrade: true,
    });
  });

  it('presents terminal paid states as read-only grace or ended access', () => {
    expect(getSubscriptionPresentation(status({
      has_subscription: true,
      tier: 'premium',
      tier_display_name: 'Premium',
      status: 'expired',
      current_period_end: '2026-07-01T00:00:00Z',
      is_in_grace_period: true,
      is_terminal_paid: true,
    }), NOW)).toEqual({
      state: 'grace',
      label: 'Subscription ended — read-only access',
      canWrite: false,
      showUpgrade: true,
    });

    expect(getSubscriptionPresentation(status({
      has_subscription: true,
      tier: 'premium',
      tier_display_name: 'Premium',
      status: 'expired',
      current_period_end: '2026-07-01T00:00:00Z',
      is_terminal_paid: true,
    }), NOW)).toEqual({
      state: 'expired',
      label: 'Subscription ended',
      canWrite: false,
      showUpgrade: true,
    });
  });

  it('fails unknown states closed to Free presentation and hides paid actions when payments are unavailable', () => {
    expect(getSubscriptionPresentation(status({
      tier: 'premium',
      tier_display_name: 'Legacy Pro',
      status: 'unexpected',
    }), NOW)).toEqual({
      state: 'free',
      label: 'Free',
      canWrite: true,
      showUpgrade: true,
    });

    expect(getSubscriptionPresentation(status({
      tier: 'standard',
      tier_display_name: 'Standard',
      status: 'cancelled',
      current_period_end: '2026-07-01T00:00:00Z',
    }), NOW)).toEqual({
      state: 'free',
      label: 'Free',
      canWrite: true,
      showUpgrade: true,
    });

    expect(getSubscriptionPresentation(status({ payment_enabled: false }), NOW)).toEqual({
      state: 'free',
      label: 'Free',
      canWrite: true,
      showUpgrade: false,
    });
  });
});
