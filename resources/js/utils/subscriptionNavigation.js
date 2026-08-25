export function subscriptionOptionsLocation() {
  return {
    path: '/settings/subscription',
    query: { openPricing: '1' },
  };
}

export function shouldShowUpgradeEntry(subscriptionData, isPreviewMode) {
  return Boolean(
    subscriptionData
    && subscriptionData.tier === 'free'
    && subscriptionData.payment_enabled === true
    && !isPreviewMode
  );
}
