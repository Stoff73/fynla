/**
 * Shared route-to-module mapping for InfoGuidePanel and ModuleStatusBar.
 * More specific routes must come before less specific ones.
 */
const moduleMap = {
  // Net Worth sub-sections
  '/net-worth/properties': 'properties',
  '/net-worth/property': 'properties',
  '/net-worth/investments': 'investment',
  '/net-worth/retirement': 'retirement',
  '/net-worth/savings': 'savings',
  '/net-worth/liabilities': 'liabilities',
  '/net-worth/business': 'business_interests',
  '/net-worth/chattels': 'chattels',
  '/net-worth/cash': 'savings',
  '/net-worth/wealth-summary': 'net_worth',
  // Main modules
  '/protection': 'protection',
  '/savings': 'savings',
  '/investment': 'investment',
  '/retirement': 'retirement',
  '/pension': 'retirement',
  '/estate': 'estate',
  '/trusts': 'trusts',
  '/risk-profile': 'investment',
  // Planning
  '/holistic-plan': 'coordination',
  '/plans': 'coordination',
  '/planning/journeys': 'coordination',
  '/planning/what-if': 'coordination',
  '/goals': 'goals',
  '/actions': 'coordination',
  // Utility pages
  '/valuable-info': 'profile',
  '/settings': 'profile',
  '/help': 'dashboard',
  '/profile': 'profile',
  '/net-worth': 'net_worth',
  '/dashboard': 'dashboard',
  '/preview': 'dashboard',
};

/**
 * Resolve the infoGuide module name for a given route path.
 * @param {string} path - The route path (e.g. '/protection')
 * @returns {string} The module key for the infoGuide store
 */
export function resolveModule(path) {
  const sortedPrefixes = Object.keys(moduleMap).sort((a, b) => b.length - a.length);
  for (const prefix of sortedPrefixes) {
    if (path.startsWith(prefix)) {
      return moduleMap[prefix];
    }
  }
  return 'dashboard';
}

export default moduleMap;
