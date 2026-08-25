const overviewPaths = Object.freeze({
  dashboard: '/dashboard',
  income: '/income',
  expenditure: '/expenditure',
  net_worth: '/net-worth',
  protection: '/protection',
  savings: '/savings',
  investment: '/investment',
  retirement: '/retirement',
  estate: '/estate',
  goals: '/goals',
  tax_strategy: '/tax-strategy',
  holistic_plan: '/holistic-plan',
  achievements: '/achievements',
  conversation_history: '/conversation-history',
  personal_information: '/personal-information',
  subscription: '/subscription',
  settings: '/settings',
});

const detailPaths = Object.freeze({
  protection_policy_detail: (params) => {
    const type = identifier(params.policy_type);
    const id = identifier(params.policy_id);
    return type && id ? `/protection/policy/${type}/${id}` : null;
  },
  savings_account_detail: (params) => {
    const id = identifier(params.account_id);
    return id ? `/savings/account/${id}` : null;
  },
  investment_account_detail: (params) => {
    const id = identifier(params.account_id);
    return id ? `/investment/account/${id}` : null;
  },
  pension_detail: (params) => {
    const type = identifier(params.pension_type);
    const id = identifier(params.pension_id);
    return type && id ? `/retirement/pension/${type}/${id}` : null;
  },
  goal_detail: (params) => {
    const id = identifier(params.goal_id);
    return id ? `/goals/${id}` : null;
  },
  property_detail: (params) => {
    const id = identifier(params.property_id);
    return id ? `/net-worth/property/${id}` : null;
  },
  mortgage_detail: (params) => {
    const id = identifier(params.mortgage_id);
    return id ? `/net-worth/mortgage/${id}` : null;
  },
  liability_detail: (params) => {
    const id = identifier(params.liability_id);
    return id ? `/net-worth/liability/${id}` : null;
  },
  income_detail: (params) => {
    const owner = identifier(params.income_owner);
    const source = identifier(params.income_source);
    return owner && source ? `/income/${owner}/${source}` : null;
  },
});

const legacyPaths = new Set([
  ...Object.values(overviewPaths),
  '/net-worth/history',
  '/settings',
]);

function identifier(value) {
  if ((typeof value !== 'string' && typeof value !== 'number') || String(value).length === 0) {
    return null;
  }
  return encodeURIComponent(String(value));
}

function pathForScreen(screen, params = {}) {
  if (Object.hasOwn(overviewPaths, screen)) return overviewPaths[screen];
  if (Object.hasOwn(detailPaths, screen)) return detailPaths[screen](params);
  return null;
}

function allowlistedLegacyPath(path) {
  if (legacyPaths.has(path)) return path;
  if (/^\/protection\/policy\/[^/]+\/[^/]+$/.test(path)) return path;
  if (/^\/savings\/account\/[^/]+$/.test(path)) return path;
  if (/^\/investment\/account\/[^/]+$/.test(path)) return path;
  if (/^\/retirement\/pension\/[^/]+\/[^/]+$/.test(path)) return path;
  if (/^\/goals\/[^/]+$/.test(path)) return path;
  if (/^\/net-worth\/(property|mortgage|liability)\/[^/]+$/.test(path)) return path;
  if (/^\/income\/[^/]+\/[^/]+$/.test(path)) return path;
  if (/^\/net-worth\/[^/]+$/.test(path)) return path;
  return null;
}

export function resolveMobileDestination(action, recordUnknown = () => {}) {
  const destination = action?.destination;
  if (destination && typeof destination.screen === 'string') {
    const path = pathForScreen(destination.screen, destination.params || {});
    if (path) return path;

    recordUnknown(destination.screen);
    return pathForScreen(destination.fallback, {}) || '/dashboard';
  }

  return allowlistedLegacyPath(action?.payload) || '/dashboard';
}

export function recordUnknownMobileDestination() {
  // Intentionally omit the unknown value and all parameters. This fixed event
  // is enough to detect contract drift without logging user or financial data.
  console.warn('navigation.unknown_destination');
}
