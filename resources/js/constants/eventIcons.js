/**
 * Event Icons Constants
 *
 * Icon and color mappings for goals and life events used in projections.
 * Uses Heroicons v2 names for Vue component mapping.
 */

/**
 * Goal type icons and colors
 */
export const GOAL_ICONS = {
  emergency_fund: {
    icon: 'ShieldCheckIcon',
    color: '#15803D',
    category: 'savings',
    label: 'Emergency Fund',
  },
  property_purchase: {
    icon: 'HomeIcon',
    color: '#1F2A44',
    category: 'property',
    label: 'Property Purchase',
  },
  home_deposit: {
    icon: 'HomeIcon',
    color: '#1F2A44',
    category: 'property',
    label: 'Home Deposit',
  },
  holiday: {
    icon: 'GlobeAltIcon',
    color: '#14B8A6',
    category: 'lifestyle',
    label: 'Holiday',
  },
  car_purchase: {
    icon: 'TruckIcon',
    color: '#64748B',
    category: 'purchase',
    label: 'Car Purchase',
  },
  wedding: {
    icon: 'HeartIcon',
    color: '#EC4899',
    category: 'lifestyle',
    label: 'Wedding',
  },
  education: {
    icon: 'AcademicCapIcon',
    color: '#7C3AED',
    category: 'education',
    label: 'Education',
  },
  retirement: {
    icon: 'SunIcon',
    color: '#5854E6',
    category: 'retirement',
    label: 'Retirement',
  },
  wealth_accumulation: {
    icon: 'ChartBarIcon',
    color: '#3B82F6',
    category: 'investment',
    label: 'Wealth Building',
  },
  debt_repayment: {
    icon: 'BanknotesIcon',
    color: '#64748B',
    category: 'debt',
    label: 'Debt Repayment',
  },
  custom: {
    icon: 'FlagIcon',
    color: '#64748B',
    category: 'custom',
    label: 'Custom Goal',
  },
};

/**
 * Life event type icons and colors
 */
export const LIFE_EVENT_ICONS = {
  // Income events (positive)
  inheritance: {
    icon: 'GiftIcon',
    color: '#7C3AED',
    impactType: 'income',
    category: 'income',
    label: 'Inheritance',
  },
  gift_received: {
    icon: 'GiftTopIcon',
    color: '#EC4899',
    impactType: 'income',
    category: 'income',
    label: 'Gift Received',
  },
  bonus: {
    icon: 'BanknotesIcon',
    color: '#15803D',
    impactType: 'income',
    category: 'income',
    label: 'Bonus',
  },
  redundancy_payment: {
    icon: 'DocumentTextIcon',
    color: '#5854E6',
    impactType: 'income',
    category: 'income',
    label: 'Redundancy Payment',
  },
  property_sale: {
    icon: 'BuildingOfficeIcon',
    color: '#1F2A44',
    impactType: 'income',
    category: 'income',
    label: 'Property Sale',
  },
  business_sale: {
    icon: 'BriefcaseIcon',
    color: '#0EA5E9',
    impactType: 'income',
    category: 'income',
    label: 'Business Sale',
  },
  pension_lump_sum: {
    icon: 'CurrencyPoundIcon',
    color: '#5854E6',
    impactType: 'income',
    category: 'income',
    label: 'Pension Lump Sum',
  },
  lottery_windfall: {
    icon: 'SparklesIcon',
    color: '#EC4899',
    impactType: 'income',
    category: 'income',
    label: 'Lottery/Windfall',
  },
  custom_income: {
    icon: 'PlusCircleIcon',
    color: '#15803D',
    impactType: 'income',
    category: 'income',
    label: 'Other Income',
  },

  // Expense events (negative)
  large_purchase: {
    icon: 'ShoppingCartIcon',
    color: '#EF4444',
    impactType: 'expense',
    category: 'expense',
    label: 'Large Purchase',
  },
  home_improvement: {
    icon: 'WrenchScrewdriverIcon',
    color: '#64748B',
    impactType: 'expense',
    category: 'expense',
    label: 'Home Improvement',
  },
  education_fees: {
    icon: 'AcademicCapIcon',
    color: '#7C3AED',
    impactType: 'expense',
    category: 'expense',
    label: 'Education Fees',
  },
  gift_given: {
    icon: 'GiftIcon',
    color: '#EC4899',
    impactType: 'expense',
    category: 'expense',
    label: 'Gift Given',
  },
  medical_expense: {
    icon: 'HeartIcon',
    color: '#EF4444',
    impactType: 'expense',
    category: 'expense',
    label: 'Medical Expense',
  },
  custom_expense: {
    icon: 'MinusCircleIcon',
    color: '#EF4444',
    impactType: 'expense',
    category: 'expense',
    label: 'Other Expense',
  },
};

/**
 * Combined event icons (goals + life events)
 */
export const EVENT_ICONS = {
  ...GOAL_ICONS,
  ...LIFE_EVENT_ICONS,
};

/**
 * Impact type colors
 */
export const IMPACT_COLORS = {
  income: '#15803D', // Green for positive
  expense: '#EF4444', // Red for negative
};

/**
 * Certainty level styling
 */
export const CERTAINTY_STYLES = {
  confirmed: {
    opacity: 1.0,
    borderStyle: 'solid',
    label: 'Confirmed',
  },
  likely: {
    opacity: 0.9,
    borderStyle: 'solid',
    label: 'Likely',
  },
  possible: {
    opacity: 0.7,
    borderStyle: 'dashed',
    label: 'Possible',
  },
  speculative: {
    opacity: 0.5,
    borderStyle: 'dotted',
    label: 'Speculative',
  },
};

/**
 * Get icon config for an event type
 * @param {string} type - Event type key
 * @param {string} source - 'goal' or 'life_event'
 * @returns {Object} Icon configuration
 */
export function getEventIconConfig(type, source = 'goal') {
  if (source === 'goal') {
    return GOAL_ICONS[type] || GOAL_ICONS.custom;
  }
  return LIFE_EVENT_ICONS[type] || LIFE_EVENT_ICONS.custom_expense;
}

/**
 * Get color for an event based on its impact type
 * @param {Object} event - Event object with impact field
 * @returns {string} Hex color code
 */
export function getEventColor(event) {
  if (event.color) {
    return event.color;
  }

  if (event.type === 'goal') {
    return GOAL_ICONS[event.category]?.color || '#64748B';
  }

  return LIFE_EVENT_ICONS[event.category]?.color || IMPACT_COLORS[event.impact] || '#64748B';
}

/**
 * Chart phase colors (accumulation vs retirement)
 */
export const PHASE_COLORS = {
  accumulation: {
    bg: 'rgba(31, 42, 68, 0.1)', // Horizon 500 with opacity
    border: '#1F2A44',
  },
  retirement: {
    bg: 'rgba(88, 84, 230, 0.1)', // Violet 500 with opacity
    border: '#5854E6',
  },
};
