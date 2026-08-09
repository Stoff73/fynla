function deepFreeze(value) {
  Object.freeze(value);
  Object.values(value).forEach((child) => {
    if (child && typeof child === 'object' && !Object.isFrozen(child)) deepFreeze(child);
  });
  return value;
}

// One navigation definition for both dashboard and inner-page drawers. Icons
// are stable keys resolved by each shell, keeping this model presentation-light.
export const primaryNavigationSections = deepFreeze([
  {
    group: 'Overview',
    links: [
      { slug: 'dashboard', label: 'Dashboard', icon: 'net_worth', route: '/dashboard' },
      { slug: 'achievements', label: 'Achievements', icon: 'goals', route: '/achievements' },
    ],
  },
  {
    group: 'Cash Management',
    links: [
      { slug: 'income', label: 'Income', icon: 'income', route: '/income' },
      { slug: 'expenditure', label: 'Expenditure', icon: 'expenditure', route: '/expenditure' },
    ],
  },
  {
    group: 'Finances',
    links: [
      { slug: 'net_worth', label: 'Net Worth', icon: 'net_worth', route: '/net-worth' },
      { slug: 'savings', label: 'Bank Accounts', icon: 'savings', route: '/savings' },
      { slug: 'investment', label: 'Investments', icon: 'investment', route: '/investment' },
      { slug: 'retirement', label: 'Retirement', icon: 'retirement', route: '/retirement' },
    ],
  },
  {
    group: 'Family',
    links: [
      { slug: 'protection', label: 'Protection', icon: 'protection', route: '/protection' },
      { slug: 'estate', label: 'Estate Planning', icon: 'estate', route: '/estate' },
    ],
  },
  {
    group: 'Planning',
    links: [
      { slug: 'goals', label: 'Goals', icon: 'goals', route: '/goals' },
      { slug: 'tax', label: 'Tax Strategy', icon: 'tax', route: '/tax-strategy' },
      { slug: 'holistic', label: 'Holistic Plan', icon: 'holistic', route: '/holistic-plan' },
    ],
  },
  {
    group: 'Account',
    links: [
      { slug: 'personal_information', label: 'Personal Information', icon: 'admin', route: '/personal-information' },
      { slug: 'subscription', label: 'Subscription', icon: 'savings', route: '/subscription' },
      { slug: 'settings', label: 'Settings', icon: 'admin', route: '/settings' },
    ],
  },
]);
