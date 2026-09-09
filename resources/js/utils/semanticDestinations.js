/**
 * Resolve a server-issued semantic destination ({screen, params, fallback})
 * to a web SPA route — the web counterpart of resources/mobile/navigation/
 * semanticDestinations.js and the native SemanticDestinationResolver. The
 * server never emits web paths for detail screens; it names the screen and
 * the identifiers, and each client maps them to its own routes.
 */

// Overview screens — the GateRoutes `web` column.
const overviewPaths = Object.freeze({
  dashboard: '/dashboard',
  personal_information: '/settings/personal',
  income: '/valuable-info?section=income',
  expenditure: '/valuable-info?section=expenditure',
  protection: '/protection',
  savings: '/savings',
  investment: '/investment',
  retirement: '/retirement',
  estate: '/estate',
  goals: '/goals',
  net_worth: '/net-worth',
  tax_strategy: '/tax-strategy',
  holistic_plan: '/holistic-plan',
  settings: '/settings',
  subscription: '/settings/subscription',
});

function identifier(value) {
  if (typeof value === 'number' && Number.isInteger(value) && value > 0) return String(value);
  if (typeof value === 'string' && /^[A-Za-z0-9_-]+$/.test(value)) return value;
  return null;
}

// Detail screens with a web route of their own; the rest fall back to the
// module overview (the web SPA has no goal or investment-account detail page).
const detailPaths = Object.freeze({
  savings_account_detail: (params) => {
    const id = identifier(params.account_id);
    return id ? `/savings/account/${id}` : null;
  },
  pension_detail: (params) => {
    const type = identifier(params.pension_type);
    const id = identifier(params.pension_id);
    return type && id ? `/pension/${type}/${id}` : null;
  },
  protection_policy_detail: (params) => {
    const type = identifier(params.policy_type);
    const id = identifier(params.policy_id);
    return type && id ? `/protection/policy/${type}/${id}` : null;
  },
});

/**
 * @param {{screen?: string, params?: object, fallback?: string}|null} destination
 * @returns {string|null} a web route, or null when nothing resolves
 */
export function resolveWebDestination(destination) {
  if (!destination || typeof destination !== 'object') return null;
  const params = destination.params && typeof destination.params === 'object' ? destination.params : {};
  const detail = detailPaths[destination.screen];
  if (detail) {
    const path = detail(params);
    if (path) return path;
  }
  if (overviewPaths[destination.screen]) return overviewPaths[destination.screen];
  if (overviewPaths[destination.fallback]) return overviewPaths[destination.fallback];
  return null;
}
