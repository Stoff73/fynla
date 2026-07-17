function hasFuturePeriod(subscriptionData, now) {
  if (!subscriptionData?.current_period_end) return false;
  const periodEnd = new Date(subscriptionData.current_period_end);
  return !Number.isNaN(periodEnd.getTime()) && periodEnd > now;
}

function formatEndDate(value) {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return null;
  return date.toLocaleDateString('en-GB', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    timeZone: 'UTC',
  });
}

export function getSubscriptionPresentation(subscriptionData, now = new Date()) {
  const paymentEnabled = subscriptionData?.payment_enabled === true;
  const status = subscriptionData?.subscription_status ?? null;
  const isPremium = subscriptionData?.tier === 'premium';
  const tierLabel = isPremium && subscriptionData?.tier_display_name
    ? subscriptionData.tier_display_name
    : 'Premium';
  const periodActive = hasFuturePeriod(subscriptionData, now);

  if (status === 'pending') {
    return {
      state: 'pending',
      label: 'Free — payment pending',
      canWrite: true,
      showUpgrade: paymentEnabled,
    };
  }

  if (status === 'active' && isPremium) {
    return {
      state: 'active',
      label: tierLabel,
      canWrite: true,
      showUpgrade: false,
    };
  }

  if (status === 'cancelled' && isPremium && periodActive) {
    const endDate = formatEndDate(subscriptionData.current_period_end);
    return {
      state: 'cancelled',
      label: endDate ? `${tierLabel} — ends ${endDate}` : tierLabel,
      canWrite: true,
      showUpgrade: false,
    };
  }

  if (status === 'past_due' && isPremium && periodActive) {
    return {
      state: 'past_due',
      label: `${tierLabel} — payment issue`,
      canWrite: true,
      showUpgrade: paymentEnabled,
    };
  }

  const terminalPaid = subscriptionData?.is_terminal_paid === true
    || (isPremium && ['expired', 'cancelled', 'past_due'].includes(status));
  if (terminalPaid && subscriptionData?.is_in_grace_period === true) {
    return {
      state: 'grace',
      label: 'Subscription ended — read-only access',
      canWrite: false,
      showUpgrade: paymentEnabled,
    };
  }

  if (terminalPaid) {
    return {
      state: 'expired',
      label: 'Subscription ended',
      canWrite: false,
      showUpgrade: paymentEnabled,
    };
  }

  return {
    state: 'free',
    label: 'Free',
    canWrite: true,
    showUpgrade: paymentEnabled,
  };
}
