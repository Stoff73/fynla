// resources/js/constants/subNavConfig.js
//
// Maps route prefixes to sub-navigation tabs and CTAs.
// Tabs use router-link `to` values. CTAs dispatch actions via the subNav store.
// Order matters — first match wins (most specific prefixes first).

export const SUB_NAV_CONFIG = [
  // ── Cash Management ──
  {
    match: '/net-worth/cash',
    tabs: [
      { label: 'All Accounts', to: '/net-worth/cash' },
    ],
    ctas: [
      { label: 'Add Account', icon: 'plus', action: 'addAccount', style: 'primary' },
    ],
  },

  // ── Investments (sub-routes first) ──
  {
    match: ['/net-worth/investments', '/net-worth/investment-detail', '/net-worth/tax-efficiency', '/net-worth/holdings-detail', '/net-worth/fees-detail', '/net-worth/strategy-detail'],
    tabs: [
      { label: 'Portfolio', to: '/net-worth/investments' },
      { label: 'Tax Efficiency', to: '/net-worth/tax-efficiency' },
      { label: 'Holdings', to: '/net-worth/holdings-detail' },
      { label: 'Fees', to: '/net-worth/fees-detail' },
    ],
    ctas: [
      { label: 'Add Account', icon: 'plus', action: 'addAccount', style: 'primary' },
      { label: 'Upload Statement', icon: 'upload', action: 'uploadStatement', style: 'secondary' },
    ],
  },

  // ── Retirement ──
  {
    match: '/net-worth/retirement',
    tabs: [
      { label: 'Pensions', to: '/net-worth/retirement' },
    ],
    ctas: [
      { label: 'Add Pension', icon: 'plus', action: 'addPension', style: 'primary' },
      { label: 'Upload Statement', icon: 'upload', action: 'uploadStatement', style: 'secondary' },
    ],
  },

  // ── Property ──
  {
    match: '/net-worth/property',
    tabs: [
      { label: 'Properties', to: '/net-worth/property' },
    ],
    ctas: [
      { label: 'Add Property', icon: 'plus', action: 'addProperty', style: 'primary' },
    ],
  },

  // ── Protection ──
  {
    match: '/protection',
    tabs: [
      { label: 'Policies', to: '/protection' },
    ],
    ctas: [
      { label: 'Add Policy', icon: 'plus', action: 'addPolicy', style: 'primary' },
    ],
  },

  // ── Estate Planning ──
  {
    match: '/estate',
    tabs: [
      { label: 'Overview', to: '/estate' },
      { label: 'Will Builder', to: '/estate/will-builder' },
      { label: 'Power of Attorney', to: '/estate/power-of-attorney' },
    ],
    ctas: [],
  },

  // ── Trusts ──
  {
    match: '/trusts',
    tabs: [
      { label: 'Trusts', to: '/trusts' },
    ],
    ctas: [
      { label: 'Add Trust', icon: 'plus', action: 'addTrust', style: 'primary' },
      { label: 'Upload Document', icon: 'upload', action: 'uploadDocument', style: 'secondary' },
    ],
  },

  // ── Goals ──
  {
    match: '/goals',
    tabs: [
      { label: 'Overview', to: '/goals' },
      { label: 'Life Events', to: { path: '/goals', query: { tab: 'events' } } },
    ],
    ctas: [
      { label: 'Add Goal', icon: 'plus', action: 'addGoal', style: 'primary' },
    ],
  },

  // ── Actions ──
  {
    match: '/actions',
    tabs: [
      { label: 'All Actions', to: '/actions' },
    ],
    ctas: [],
  },

  // ── Savings ──
  {
    match: '/savings',
    tabs: [
      { label: 'Savings', to: '/savings' },
    ],
    ctas: [
      { label: 'Add Account', icon: 'plus', action: 'addAccount', style: 'primary' },
      { label: 'Upload Statement', icon: 'upload', action: 'uploadStatement', style: 'secondary' },
    ],
  },

  // ── Valuable Info ──
  {
    match: '/valuable-info',
    tabs: [
      { label: 'Letter to Spouse', to: { path: '/valuable-info', query: { section: 'letter' } } },
      { label: 'Income', to: { path: '/valuable-info', query: { section: 'income' } } },
      { label: 'Expenditure', to: { path: '/valuable-info', query: { section: 'expenditure' } } },
      { label: 'Risk Profile', to: { path: '/valuable-info', query: { section: 'risk' } } },
    ],
    ctas: [],
  },

  // ── Settings ──
  {
    match: '/settings',
    tabs: [
      { label: 'General', to: '/settings' },
      { label: 'Security', to: '/settings/security' },
      { label: 'Privacy', to: '/settings/privacy' },
      { label: 'Assumptions', to: '/settings/assumptions' },
    ],
    ctas: [],
  },
];
