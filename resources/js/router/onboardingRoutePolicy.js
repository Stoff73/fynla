/**
 * While a campaign is forced (CSJ 2026-09-25: every onboarding goes through
 * Save Tax), the onboarding wizard routes stay in the app but are not
 * reachable: they open Fyn on the forced campaign instead. The server decides
 * the campaign and reports it on the user (onboarding_forced_campaign).
 */
export function forcedCampaignRedirect(user) {
  const campaign = user?.onboarding_forced_campaign;
  if (!campaign) {
    return null;
  }

  return { name: 'Dashboard', query: { openFyn: 'journey', from: campaign } };
}
