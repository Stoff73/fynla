/**
 * Fynla Design System Constants
 *
 * This file is the single source of truth for all design tokens used in the application.
 * All components should import from here instead of using hardcoded values.
 *
 * Based on designStyle.md v1.0.0
 */

// =============================================================================
// COLOR SYSTEM
// =============================================================================

/**
 * Primary Brand Colors (Trust Blue & Deep Navy)
 */
export const PRIMARY_COLORS = {
  50: '#FFFFFF',
  100: '#F1F5F9',
  200: '#E2E8F0',
  300: '#CBD5E1',
  400: '#94A3B8',
  500: '#3B82F6',     // Accent blue, links
  600: '#1257A0',     // Main Brand Color - buttons, active states
  700: '#0E3A66',     // Hover states
  800: '#0B2C4F',     // Active/pressed states
  900: '#051B33',
};

/**
 * Secondary/Neutral Colors (Slate)
 */
export const SECONDARY_COLORS = {
  50: '#FFFFFF',
  100: '#F1F5F9',
  200: '#E2E8F0',
  500: '#64748B',
  600: '#475569',     // Body text
  700: '#334155',     // Headings
  800: '#1E293B',
  900: '#0F172A',
};

/**
 * Semantic Colors
 */
export const SUCCESS_COLORS = {
  50: '#FFFFFF',
  100: '#F0FDF4',
  500: '#15803D',     // Solid Green - success text, icons
  600: '#166534',     // Success borders, buttons
  700: '#14532D',
};

export const ERROR_COLORS = {
  50: '#FFFFFF',
  100: '#FEF2F2',
  500: '#EF4444',     // Error icons
  600: '#B91C1C',     // Error text, borders
  700: '#991B1B',
};

export const WARNING_COLORS = {
  50: '#EFF6FF',      // blue-50
  100: '#DBEAFE',     // blue-100
  500: '#3B82F6',     // blue-500 - Warning icons, text
  600: '#2563EB',     // blue-600 - Warning borders
  700: '#1D4ED8',     // blue-700
};

export const INFO_COLORS = {
  50: '#FFFFFF',
  100: '#F0F9FF',
  500: '#0EA5E9',
  600: '#0284C7',
  700: '#0369A1',
};

/**
 * Chart Colors - Consistent palette for data visualization
 * ORDER MATTERS: This sequence is used for multi-series charts
 */
export const CHART_COLORS = [
  '#1257A0',  // Chart 1: Trust Blue - Primary data series
  '#475569',  // Chart 2: Slate - Secondary series
  '#15803D',  // Chart 3: Green - Positive values
  '#EA580C',  // Chart 4: Orange - Neutral/caution
  '#B91C1C',  // Chart 5: Red - Negative values
  '#7C3AED',  // Chart 6: Purple - Alternative
  '#F97316',  // Chart 7: Orange light - Tertiary
  '#0F172A',  // Chart 8: Navy - Dark accent
];

/**
 * Asset Category Colors - For wealth breakdown charts
 * Maps to specific asset types for consistency across all charts
 */
export const ASSET_COLORS = {
  pensions: '#1257A0',      // Trust Blue - largest category typically
  property: '#15803D',      // Green - real assets
  investments: '#475569',   // Slate - investment accounts
  cash: '#EA580C',          // Orange - liquid assets
  business: '#7C3AED',      // Purple - business interests
  chattels: '#F97316',      // Orange light - personal valuables
};

/**
 * Spending Category Colors - For expenditure donut charts
 * Extended palette for detailed spending breakdown (16 categories)
 */
export const SPENDING_COLORS = [
  '#7c3aed', // Purple - Mortgage Payments
  '#2563eb', // Blue - Loan Payments
  '#0891b2', // Cyan - Pension Contributions
  '#dc2626', // Red - Protection Premiums
  '#16a34a', // Green - Food & Groceries
  '#ea580c', // Orange - Transport
  '#ec4899', // Pink - Healthcare
  '#6366f1', // Indigo - Insurance
  '#8b5cf6', // Violet - Clothing & Personal
  '#f97316', // Orange - Entertainment
  '#14b8a6', // Teal - Childcare
  '#84cc16', // Lime - School Fees
  '#06b6d4', // Sky - Holidays
  '#64748b', // Slate - Other
  '#059669', // Emerald - Savings Deposits
  '#be123c', // Rose - Credit Card Spending
];

/**
 * Risk Level Colors - Hex values for charts and programmatic use
 */
export const RISK_COLORS = {
  low: {
    bg: '#CA8A04',
    bgLight: '#FEF9C3',
    border: '#EAB308',
    borderLight: '#FDE047',
    text: '#713F12',
  },
  lower_medium: {
    bg: '#DB2777',
    bgLight: '#FCE7F3',
    border: '#EC4899',
    borderLight: '#F9A8D4',
    text: '#831843',
  },
  medium: {
    bg: '#15803D',
    bgLight: '#D1FAE5',
    border: '#16A34A',
    borderLight: '#86EFAC',
    text: '#14532D',
  },
  upper_medium: {
    bg: '#0D9488',
    bgLight: '#CCFBF1',
    border: '#14B8A6',
    borderLight: '#5EEAD4',
    text: '#134E4A',
  },
  high: {
    bg: '#2563EB',
    bgLight: '#DBEAFE',
    border: '#3B82F6',
    borderLight: '#93C5FD',
    text: '#1E3A8A',
  },
};

/**
 * Risk Level Tailwind Classes - For use in Vue components
 * Single source of truth for risk badge/indicator styling
 */
export const RISK_TAILWIND_CLASSES = {
  low: {
    bg: 'bg-yellow-100',
    text: 'text-yellow-800',
    border: 'border-yellow-200',
    combined: 'bg-yellow-100 text-yellow-800',
  },
  lower_medium: {
    bg: 'bg-pink-100',
    text: 'text-pink-800',
    border: 'border-pink-200',
    combined: 'bg-pink-100 text-pink-800',
  },
  medium: {
    bg: 'bg-green-100',
    text: 'text-green-800',
    border: 'border-green-200',
    combined: 'bg-green-100 text-green-800',
  },
  upper_medium: {
    bg: 'bg-teal-100',
    text: 'text-teal-800',
    border: 'border-teal-200',
    combined: 'bg-teal-100 text-teal-800',
  },
  high: {
    bg: 'bg-blue-100',
    text: 'text-blue-800',
    border: 'border-blue-200',
    combined: 'bg-blue-100 text-blue-800',
  },
};

/**
 * Risk Level Display Names
 */
export const RISK_DISPLAY_NAMES = {
  low: 'Low',
  lower_medium: 'Lower-Medium',
  medium: 'Medium',
  upper_medium: 'Upper-Medium',
  high: 'High',
  // Legacy values
  cautious: 'Cautious',
  balanced: 'Balanced',
  adventurous: 'Adventurous',
};

/**
 * Risk Level Abbreviated Labels
 */
export const RISK_ABBREVIATED_LABELS = {
  low: 'Low',
  lower_medium: 'L-Med',
  medium: 'Med',
  upper_medium: 'U-Med',
  high: 'High',
};

/**
 * Risk Level Descriptions for tooltips
 */
export const RISK_DESCRIPTIONS = {
  low: 'Low Risk - Capital preservation focus',
  lower_medium: 'Lower-Medium Risk - Stability with modest growth',
  medium: 'Medium Risk - Balanced approach',
  upper_medium: 'Upper-Medium Risk - Growth focus',
  high: 'High Risk - Maximum growth potential',
};

/**
 * Legacy risk tolerance to new system mapping
 */
export const RISK_LEGACY_MAP = {
  cautious: 'lower_medium',
  balanced: 'medium',
  adventurous: 'upper_medium',
};

/**
 * Helper: Get risk level Tailwind classes
 * @param {string} level - The risk level value
 * @returns {Object} Object with bg, text, border, and combined classes
 */
export function getRiskClasses(level) {
  const normalizedLevel = RISK_LEGACY_MAP[level] || level;
  return RISK_TAILWIND_CLASSES[normalizedLevel] || {
    bg: 'bg-gray-100',
    text: 'text-gray-800',
    border: 'border-gray-200',
    combined: 'bg-gray-100 text-gray-800',
  };
}

/**
 * Helper: Get risk level display name
 * @param {string} level - The risk level value
 * @returns {string} Display name for the risk level
 */
export function getRiskDisplayName(level) {
  return RISK_DISPLAY_NAMES[level] || 'Medium';
}

/**
 * Helper: Normalize legacy risk tolerance to new system
 * @param {string} tolerance - Legacy tolerance value (cautious, balanced, adventurous)
 * @returns {string} Normalized risk level
 */
export function normalizeRiskLevel(level) {
  return RISK_LEGACY_MAP[level] || level;
}

/**
 * Text Colors
 */
export const TEXT_COLORS = {
  primary: '#111827',       // Headings - gray-900
  secondary: '#374151',     // Body - gray-700
  tertiary: '#4B5563',      // Subtle - gray-600
  muted: '#6B7280',         // Captions - gray-500
  placeholder: '#9CA3AF',   // Placeholder - gray-400
  disabled: '#D1D5DB',      // Disabled - gray-300
};

/**
 * Background Colors
 */
export const BG_COLORS = {
  page: '#F9FAFB',          // Page background - gray-50
  card: '#FFFFFF',          // Card/component background
  subtle: '#F3F4F6',        // Subtle highlight - gray-100
  overlay: 'rgba(107, 114, 128, 0.75)',  // Modal overlay
};

/**
 * Border Colors
 */
export const BORDER_COLORS = {
  default: '#E5E7EB',       // gray-200
  hover: '#D1D5DB',         // gray-300
  focus: PRIMARY_COLORS[600],
  error: ERROR_COLORS[500],
  success: SUCCESS_COLORS[500],
};

// =============================================================================
// APEXCHARTS CONFIGURATION
// =============================================================================

/**
 * Default ApexCharts configuration
 * Import and spread this into your chart options
 */
export const CHART_DEFAULTS = {
  chart: {
    fontFamily: 'Inter, system-ui, sans-serif',
    toolbar: { show: false },
    zoom: { enabled: false },
  },
  colors: CHART_COLORS,
  dataLabels: {
    style: {
      fontSize: '12px',
      fontWeight: 600,
    },
  },
  legend: {
    fontSize: '14px',
    fontFamily: 'Inter, system-ui, sans-serif',
  },
  tooltip: {
    style: {
      fontSize: '14px',
      fontFamily: 'Inter, system-ui, sans-serif',
    },
  },
  grid: {
    borderColor: BORDER_COLORS.default,
    strokeDashArray: 4,
  },
  xaxis: {
    labels: {
      style: {
        colors: TEXT_COLORS.muted,
        fontSize: '11px',
      },
    },
    axisBorder: { show: false },
    axisTicks: { show: false },
  },
  yaxis: {
    labels: {
      style: {
        colors: TEXT_COLORS.muted,
        fontSize: '11px',
      },
    },
  },
};

/**
 * Get color by threshold percentage
 * Used for gauges, progress bars, and status indicators
 */
export function getColorByThreshold(value, thresholds = { success: 80, warning: 60 }) {
  if (value >= thresholds.success) return SUCCESS_COLORS[500];
  if (value >= thresholds.warning) return WARNING_COLORS[500];
  return ERROR_COLORS[500];
}

/**
 * Get color for positive/negative values
 * Used for financial charts showing gains/losses
 */
export function getValueColor(value) {
  if (value > 0) return SUCCESS_COLORS[500];
  if (value < 0) return ERROR_COLORS[500];
  return TEXT_COLORS.muted;
}

// =============================================================================
// SPACING & SIZING
// =============================================================================

export const SPACING = {
  xs: '0.25rem',   // 4px
  sm: '0.5rem',    // 8px
  md: '1rem',      // 16px
  lg: '1.5rem',    // 24px
  xl: '2rem',      // 32px
  '2xl': '3rem',   // 48px
};

export const BORDER_RADIUS = {
  none: '0',
  sm: '0.25rem',   // 4px - badges, chips
  md: '0.375rem',  // 6px - inputs, small elements
  button: '0.5rem', // 8px - buttons
  card: '0.75rem', // 12px - cards, modals
  lg: '1rem',      // 16px - large containers
  xl: '1.5rem',    // 24px - feature cards
  full: '9999px',  // pills, avatars
};

// =============================================================================
// ANIMATION
// =============================================================================

export const ANIMATION = {
  duration: {
    fast: '150ms',
    default: '200ms',
    slow: '300ms',
    slower: '500ms',
  },
  easing: {
    easeOut: 'cubic-bezier(0.4, 0, 0.2, 1)',
    easeIn: 'cubic-bezier(0.4, 0, 1, 1)',
    bounce: 'cubic-bezier(0.34, 1.56, 0.64, 1)',
  },
};
