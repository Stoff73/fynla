// resources/js/constants/subNavConfig.js
//
// Category-based navigation config.
// Each category maps to a header title and a set of sibling page tabs.
// CTAs are shown based on the active page within the category.

export const SUB_NAV_CONFIG = [
  {
    category: 'cashManagement',
    headerTitle: 'Cash Management',
    tabs: [
      { label: 'Bank Accounts', to: '/net-worth/cash', matchPrefixes: ['/net-worth/cash', '/savings'] },
      { label: 'Income', to: { path: '/valuable-info', query: { section: 'income' } }, matchQuery: { section: 'income' }, matchPrefixes: ['/valuable-info'] },
      { label: 'Expenditure', to: { path: '/valuable-info', query: { section: 'expenditure' } }, matchQuery: { section: 'expenditure' }, matchPrefixes: ['/valuable-info'] },
    ],
    ctas: {
      '/net-worth/cash': [
        { label: 'Add Account', icon: 'plus', action: 'addAccount', style: 'primary' },
      ],
    },
  },
  {
    category: 'finances',
    headerTitle: 'Finances',
    tabs: [
      { label: 'Investments', to: '/net-worth/investments', matchPrefixes: ['/net-worth/investments', '/net-worth/investment-detail', '/net-worth/tax-efficiency', '/net-worth/holdings-detail', '/net-worth/fees-detail', '/net-worth/strategy-detail'] },
      { label: 'Retirement', to: '/net-worth/retirement', matchPrefixes: ['/net-worth/retirement', '/pension'] },
      { label: 'Property', to: '/net-worth/property', matchPrefixes: ['/net-worth/property'] },
      { label: 'Liabilities', to: '/net-worth/liabilities', matchPrefixes: ['/net-worth/liabilities'] },
      { label: 'Personal Valuables', to: '/net-worth/chattels', matchPrefixes: ['/net-worth/chattels'] },
      { label: 'Risk Profile', to: '/risk-profile', matchPrefixes: ['/risk-profile'] },
      { label: 'Business', to: '/net-worth/business', matchPrefixes: ['/net-worth/business'] },
    ],
    ctas: {
      '/net-worth/investments': [
        { label: 'Add Account', icon: 'plus', action: 'addAccount', style: 'primary' },
        { label: 'Upload Statement', icon: 'upload', action: 'uploadStatement', style: 'secondary' },
      ],
      '/net-worth/retirement': [
        { label: 'Add Pension', icon: 'plus', action: 'addPension', style: 'primary' },
        { label: 'Upload Statement', icon: 'upload', action: 'uploadStatement', style: 'secondary' },
      ],
      '/net-worth/property': [
        { label: 'Add Property', icon: 'plus', action: 'addProperty', style: 'primary' },
      ],
      '/net-worth/liabilities': [
        { label: 'Add Liability', icon: 'plus', action: 'addLiability', style: 'primary' },
      ],
      '/net-worth/chattels': [
        { label: 'Add Valuable', icon: 'plus', action: 'addValuable', style: 'primary' },
        { label: 'Import', icon: 'upload', action: 'importValuables', style: 'secondary' },
      ],
      '/net-worth/business': [
        { label: 'Add Business', icon: 'plus', action: 'addBusiness', style: 'primary' },
      ],
    },
  },
  {
    category: 'family',
    headerTitle: 'Family',
    tabs: [
      { label: 'Protection', to: '/protection', matchPrefixes: ['/protection'] },
      { label: 'Will', to: '/estate/will-builder', matchPrefixes: ['/estate/will-builder'] },
      { label: 'Letter to Spouse', to: { path: '/valuable-info', query: { section: 'letter' } }, matchQuery: { section: 'letter' }, matchPrefixes: ['/valuable-info'] },
      { label: 'Trusts', to: '/trusts', matchPrefixes: ['/trusts'] },
      { label: 'Estate Planning', to: '/estate', matchPrefixes: ['/estate'] },
      { label: 'Power of Attorney', to: '/estate/power-of-attorney', matchPrefixes: ['/estate/power-of-attorney'] },
    ],
    ctas: {
      '/protection': [
        { label: 'Add Policy', icon: 'plus', action: 'addPolicy', style: 'primary' },
      ],
      '/trusts': [
        { label: 'Add Trust', icon: 'plus', action: 'addTrust', style: 'primary' },
        { label: 'Upload Document', icon: 'upload', action: 'uploadDocument', style: 'secondary' },
      ],
    },
  },
  {
    category: 'planning',
    headerTitle: 'Planning',
    tabs: [
      { label: 'Holistic Plan', to: '/holistic-plan', matchPrefixes: ['/holistic-plan'] },
      { label: 'Plans', to: '/plans', matchPrefixes: ['/plans'] },
      { label: 'Journeys', to: '/planning/journeys', matchPrefixes: ['/planning/journeys'] },
      { label: 'What If', to: '/planning/what-if', matchPrefixes: ['/planning/what-if'] },
      { label: 'Goals', to: '/goals', matchPrefixes: ['/goals'] },
      { label: 'Life Events', to: { path: '/goals', query: { tab: 'events' } }, matchQuery: { tab: 'events' }, matchPrefixes: ['/goals'] },
      { label: 'Actions', to: '/actions', matchPrefixes: ['/actions'] },
    ],
    ctas: {
      '/goals': [
        { label: 'Add Goal', icon: 'plus', action: 'addGoal', style: 'primary' },
      ],
      '/goals?tab=events': [
        { label: 'Add Life Event', icon: 'plus', action: 'addLifeEvent', style: 'primary' },
      ],
    },
  },
  {
    category: 'account',
    headerTitle: 'My Account',
    tabs: [
      { label: 'User Profile', to: '/profile', matchPrefixes: ['/profile'] },
      { label: 'Settings', to: '/settings', matchPrefixes: ['/settings'] },
    ],
    ctas: {},
  },
];

// Helper: find category config for a given route
export function findCategoryConfig(routePath, routeQuery = {}) {
  for (const category of SUB_NAV_CONFIG) {
    // Check query-param tabs first (more specific)
    for (const tab of category.tabs) {
      if (tab.matchQuery) {
        const queryMatch = Object.entries(tab.matchQuery).every(
          ([key, value]) => routeQuery[key] === value
        );
        if (queryMatch && tab.matchPrefixes.some(p => routePath.startsWith(p))) {
          return category;
        }
      }
    }
    // Then check prefix-only tabs
    for (const tab of category.tabs) {
      if (!tab.matchQuery && tab.matchPrefixes.some(p => routePath.startsWith(p))) {
        return category;
      }
    }
  }
  return null;
}

// Helper: find the active tab within a category
export function findActiveTab(category, routePath, routeQuery = {}) {
  if (!category) return null;

  // Priority 1: query-param match (most specific)
  for (const tab of category.tabs) {
    if (tab.matchQuery) {
      const queryMatch = Object.entries(tab.matchQuery).every(
        ([key, value]) => routeQuery[key] === value
      );
      if (queryMatch && tab.matchPrefixes.some(p => routePath.startsWith(p))) {
        return tab;
      }
    }
  }

  // Priority 2: longest prefix match (e.g., /estate/will-builder before /estate)
  let bestMatch = null;
  let bestLength = 0;
  for (const tab of category.tabs) {
    if (tab.matchQuery) continue;
    for (const prefix of tab.matchPrefixes) {
      if (routePath.startsWith(prefix) && prefix.length > bestLength) {
        bestMatch = tab;
        bestLength = prefix.length;
      }
    }
  }
  return bestMatch;
}

// Helper: get CTAs for the active tab's route
export function getActiveCtas(category, activeTab) {
  if (!category || !activeTab || !category.ctas) return [];
  // Build a key that includes query params for query-based tabs
  let tabKey;
  if (typeof activeTab.to === 'string') {
    tabKey = activeTab.to;
  } else if (activeTab.to.query && Object.keys(activeTab.to.query).length) {
    const queryString = Object.entries(activeTab.to.query).map(([k, v]) => `${k}=${v}`).join('&');
    tabKey = `${activeTab.to.path}?${queryString}`;
  } else {
    tabKey = activeTab.to.path;
  }
  return category.ctas[tabKey] || [];
}
