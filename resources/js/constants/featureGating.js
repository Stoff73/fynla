/**
 * Tier hierarchy — higher index = more access.
 * Used by both sidebar gating and router guard.
 */
export const PLAN_TIERS = ['student', 'standard', 'family', 'pro'];

/**
 * Sidebar route path → minimum required tier.
 * Only gated routes are listed — unlisted routes are accessible to all tiers.
 */
export const FEATURE_TIER_MAP = {
    // Standard+
    '/net-worth/property': 'standard',
    '/net-worth/liabilities': 'standard',
    '/net-worth/chattels': 'standard',
    '/net-worth/business': 'standard',
    '/planning/what-if': 'standard',

    // Pro
    '/estate': 'pro',
    '/estate/will-builder': 'pro',
    '/trusts': 'pro',
    '/estate/power-of-attorney': 'pro',
    '/estate/lpa': 'pro',
    '/holistic-plan': 'pro',
};

/**
 * Human-readable plan names for tooltip display.
 */
export const PLAN_LABELS = {
    student: 'Student',
    standard: 'Standard',
    family: 'Family',
    pro: 'Pro',
};

/**
 * Check if a user's plan meets the minimum tier requirement.
 * Returns true if userPlan >= requiredTier in the hierarchy.
 */
export function hasFeatureAccess(userPlan, requiredTier) {
    if (!userPlan || !requiredTier) return true;
    const userIndex = PLAN_TIERS.indexOf(userPlan);
    const requiredIndex = PLAN_TIERS.indexOf(requiredTier);
    if (userIndex === -1 || requiredIndex === -1) return true;
    return userIndex >= requiredIndex;
}

/**
 * Get the minimum required tier for a given route.
 * Handles both path-only routes and the special /valuable-info?section=letter case.
 * Returns null if the route is not gated.
 */
export function getRequiredTier(path, query = {}) {
    // Special: Letter to Spouse uses a query param, not a unique path
    if (path === '/valuable-info' && query.section === 'letter') return 'standard';

    // Check exact match first, then prefix match for sub-routes
    if (FEATURE_TIER_MAP[path]) return FEATURE_TIER_MAP[path];
    for (const [routePath, tier] of Object.entries(FEATURE_TIER_MAP)) {
        if (path.startsWith(routePath + '/')) return tier;
    }
    return null;
}
