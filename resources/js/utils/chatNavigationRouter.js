/**
 * Client-side navigation intent detector for AI chat.
 * Matches user messages to app routes WITHOUT calling the LLM.
 * Zero tokens, instant response.
 */

const ROUTE_MAP = [
  // Dashboard
  { keywords: ['dashboard', 'home', 'overview', 'main page'], route: '/dashboard', label: 'Dashboard' },

  // Profile & Settings
  { keywords: ['profile', 'my details', 'personal info', 'my information', 'account details'], route: '/profile', label: 'User Profile' },
  { keywords: ['settings', 'preferences', 'configuration'], route: '/settings', label: 'Settings' },
  { keywords: ['security settings', 'two factor', 'two-factor', '2fa'], route: '/settings/security', label: 'Security Settings' },
  { keywords: ['planning assumptions', 'assumptions', 'growth rate'], route: '/settings/assumptions', label: 'Planning Assumptions' },

  // Net Worth / Assets
  { keywords: ['wealth summary', 'net worth', 'total wealth', 'wealth overview'], route: '/net-worth/wealth-summary', label: 'Wealth Summary' },
  { keywords: ['bank account', 'savings', 'cash', 'bank balance', 'current account', 'easy access', 'savings account', 'cash savings'], route: '/net-worth/cash', label: 'Bank Accounts & Savings' },
  { keywords: ['investment', 'portfolio', 'stocks', 'shares', 'stocks and shares'], route: '/net-worth/investments', label: 'Investments' },
  { keywords: ['pension', 'retirement', 'retirement fund', 'sipp', 'workplace pension', 'defined contribution', 'defined benefit'], route: '/net-worth/retirement', label: 'Pensions & Retirement' },
  { keywords: ['property', 'properties', 'house', 'flat', 'real estate', 'buy to let', 'main residence'], route: '/net-worth/property', label: 'Property' },
  { keywords: ['business', 'company', 'sole trader', 'partnership', 'business interest'], route: '/net-worth/business', label: 'Business Interests' },
  { keywords: ['valuable', 'chattel', 'jewellery', 'art', 'collectible', 'personal valuable'], route: '/net-worth/chattels', label: 'Personal Valuables' },
  { keywords: ['liabilit', 'debt', 'loan', 'credit card', 'student loan'], route: '/net-worth/liabilities', label: 'Liabilities' },

  // Income & Expenditure (Valuable Info tabs)
  { keywords: ['income', 'salary', 'earnings', 'pay', 'wage', 'my income'], route: '/valuable-info?section=income', label: 'Income' },
  { keywords: ['expenditure', 'spending', 'budget', 'expenses', 'outgoing'], route: '/valuable-info?section=expenditure', label: 'Expenditure' },
  { keywords: ['letter to spouse', 'spouse letter', 'expression of wishes'], route: '/valuable-info?section=letter', label: 'Letter to Spouse' },

  // Protection
  { keywords: ['protection', 'life insurance', 'critical illness', 'income protection', 'insurance polic'], route: '/protection', label: 'Protection' },

  // Estate Planning
  { keywords: ['estate planning', 'estate', 'inheritance tax', 'inheritance'], route: '/estate', label: 'Estate Planning' },
  { keywords: ['will', 'will builder', 'testament', 'last will'], route: '/estate/will-builder', label: 'Will Builder' },
  { keywords: ['power of attorney', 'lasting power', 'lpa'], route: '/estate/power-of-attorney', label: 'Power of Attorney' },
  { keywords: ['trust', 'trusts'], route: '/trusts', label: 'Trusts' },

  // Goals & Life Events
  { keywords: ['goal', 'target', 'saving goal', 'financial goal'], route: '/goals', label: 'Goals' },
  { keywords: ['life event', 'upcoming event', 'future event'], route: '/goals?tab=events', label: 'Life Events' },

  // Risk & Analysis
  { keywords: ['risk profile', 'risk assessment', 'risk tolerance', 'attitude to risk'], route: '/risk-profile', label: 'Risk Profile' },

  // Plans (specific before generic)
  { keywords: ['investment plan'], route: '/plans/investment', label: 'Investment Plan' },
  { keywords: ['retirement plan', 'pension plan'], route: '/plans/retirement', label: 'Retirement Plan' },
  { keywords: ['protection plan'], route: '/plans/protection', label: 'Protection Plan' },
  { keywords: ['estate plan'], route: '/plans/estate', label: 'Estate Plan' },
  { keywords: ['holistic plan', 'financial plan', 'overall plan'], route: '/holistic-plan', label: 'Holistic Financial Plan' },
  { keywords: ['plans', 'my plans', 'module plans'], route: '/plans', label: 'Plans' },

  // Actions & Journeys
  { keywords: ['action', 'to do', 'task', 'recommendation'], route: '/actions', label: 'Actions & Recommendations' },
  { keywords: ['journey', 'onboarding', 'planning journey'], route: '/planning/journeys', label: 'Planning Journeys' },
  { keywords: ['what if', 'scenario', 'what-if'], route: '/planning/what-if', label: 'What-If Scenarios' },

  // Help
  { keywords: ['help', 'support', 'how do i'], route: '/help', label: 'Help' },
];

// Navigation trigger phrases — the message must contain one of these
// to be treated as a navigation request (not just a mention of a topic)
const NAV_TRIGGERS = [
  'go to', 'take me to', 'show me', 'open', 'navigate to',
  'show', 'view', 'see my', 'look at', 'check my',
  'where is', 'where are', 'how do i find', 'find my',
];

/**
 * Attempt to match a user message to a navigation route.
 *
 * @param {string} message - The user's chat message
 * @returns {{ route: string, label: string, response: string } | null}
 *   Returns route info if matched, null if the message should go to the LLM.
 */
export function matchNavigationIntent(message) {
  if (!message || message.length > 200) return null;

  const lower = message.toLowerCase().trim();

  // Must contain a navigation trigger phrase
  const hasTrigger = NAV_TRIGGERS.some(trigger => lower.includes(trigger));
  if (!hasTrigger) return null;

  // Find the best matching route (longest keyword match wins)
  let bestMatch = null;
  let bestKeywordLength = 0;

  for (const entry of ROUTE_MAP) {
    for (const keyword of entry.keywords) {
      if (lower.includes(keyword) && keyword.length > bestKeywordLength) {
        bestMatch = entry;
        bestKeywordLength = keyword.length;
      }
    }
  }

  if (!bestMatch) return null;

  return {
    route: bestMatch.route,
    label: bestMatch.label,
    response: `Navigating to ${bestMatch.label}.`,
  };
}
