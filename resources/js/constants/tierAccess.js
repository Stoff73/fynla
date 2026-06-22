/**
 * tierAccess — capability-matrix-driven feature access (SP2 freemium model).
 *
 * Replaces the legacy plan-tier model (`featureGating.js`, student/standard/
 * family/pro). Access is read from `tier_configurations.capability_matrix`
 * (delivered to the frontend via `/payment/trial-status` → auth.subscriptionData)
 * keyed by capability verb:
 *   full    → fully usable
 *   limited → usable, but count-capped (the cap is surfaced separately by the
 *             limit-reached modal — NOT a nav gate)
 *   teaser  → reachable, shows the teaser/upgrade page
 *   none    → reachable, shows the teaser/upgrade page
 *
 * A route with no capability key is ungated (accessible to every tier).
 * Gated == the destination shows the teaser/upgrade page instead of the module.
 */

/**
 * Route path → capability key in the matrix.
 * Sub-routes resolve by prefix (e.g. /estate/will-builder → estate).
 * Letter to Spouse is a query-param route, handled specially below.
 */
export const ROUTE_CAPABILITY = {
    '/net-worth/cash': 'savings_account',
    '/net-worth/investments': 'investment',
    '/net-worth/retirement': 'pension_account',
    '/net-worth/property': 'property',
    '/net-worth/liabilities': 'liabilities',
    '/net-worth/chattels': 'chattels',
    '/estate': 'estate',
    '/trusts': 'estate',
    '/holistic-plan': 'holistic_plan',
    '/planning/what-if': 'what_if',
    '/protection': 'protection',
    '/goals': 'goals',
};

/** Resolve the capability key for a route path + query, or null if ungated. */
export function capabilityForRoute(path, query = {}) {
    // Letter to Spouse / Expression of Wishes uses a query param, not a unique path.
    if (path === '/valuable-info' && query.section === 'letter') {
        return 'letter_to_spouse';
    }
    if (ROUTE_CAPABILITY[path]) return ROUTE_CAPABILITY[path];
    for (const [routePath, key] of Object.entries(ROUTE_CAPABILITY)) {
        if (path.startsWith(routePath + '/')) return key;
    }
    return null;
}

/**
 * Access verb for a route given the user's capability matrix.
 * Returns 'full' for ungated routes (no key) or when the matrix is unknown.
 */
export function accessForRoute(path, query, capabilityMatrix) {
    const key = capabilityForRoute(path, query);
    if (!key) return 'full';
    if (!capabilityMatrix) return 'full';
    return capabilityMatrix[key] || 'full';
}

/** True when a route should show the teaser/upgrade page instead of the module. */
export function isRouteGated(path, query, capabilityMatrix) {
    const verb = accessForRoute(path, query, capabilityMatrix);
    return verb === 'teaser' || verb === 'none';
}
