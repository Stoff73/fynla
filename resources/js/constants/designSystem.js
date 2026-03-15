/**
 * Fynla Design System Constants
 *
 * This file is the single source of truth for all design tokens used in the application.
 * All components should import from here instead of using hardcoded values.
 *
 * Based on fynlaDesignGuide.md v1.2.0
 */

// =============================================================================
// COLOR SYSTEM
// =============================================================================

/**
 * Primary Brand Colors (Raspberry)
 */
export const PRIMARY_COLORS = {
  50: '#FDF2F8',
  100: '#FCE7F3',
  200: '#F9A8D4',
  300: '#F472B6',
  400: '#EC4899',
  500: '#E83E6D',     // Accent color - CTAs, links
  600: '#DB2777',     // Hover states
  700: '#BE185D',     // Active/pressed states
  800: '#9D174D',
  900: '#831843',
};

/**
 * Secondary/Navigation Colors (Horizon Blue)
 */
export const SECONDARY_COLORS = {
  50: '#F8FAFC',
  100: '#F1F5F9',
  200: '#E2E8F0',
  300: '#CBD5E1',
  400: '#94A3B8',
  500: '#1F2A44',     // Main brand dark - text, navigation
  600: '#0F172A',     // Hover dark
  700: '#020617',     // Active dark
  800: '#0A0E1A',
  900: '#03060D',
};

/**
 * Semantic Colors
 */
export const SUCCESS_COLORS = {
  50: '#F0FDF9',
  100: '#D1FAE5',
  500: '#20B486',     // Success text, icons
  600: '#059669',     // Success borders, buttons
  700: '#047857',
};

export const ERROR_COLORS = {
  50: '#FDF2F8',
  100: '#FCE7F3',
  500: '#E83E6D',     // Error icons
  600: '#DB2777',     // Error text, borders
  700: '#BE185D',
};

export const WARNING_COLORS = {
  50: '#F5F3FF',
  100: '#EDE9FE',
  500: '#5854E6',     // Warning icons, text
  600: '#7C3AED',     // Warning borders
  700: '#6D28D9',
};

export const INFO_COLORS = {
  50: '#F8FAFC',
  100: '#DDE2EF',
  500: '#6C83BC',
  600: '#5A6FA3',
  700: '#4C5D8A',
};

/**
 * Chart Colors - Consistent palette for data visualization
 * ORDER MATTERS: This sequence is used for multi-series charts
 */
export const CHART_COLORS = [
  '#1F2A44',  // Chart 1: Horizon 500 - Primary data series
  '#20B486',  // Chart 2: Spring 500 - Positive values
  '#5854E6',  // Chart 3: Violet 500 - Alternative series
  '#E83E6D',  // Chart 4: Raspberry 500 - Negative/accent
  '#E6C9A8',  // Chart 5: Savannah 500 - Neutral
  '#6C83BC',  // Chart 6: Light Blue 500 - Secondary
  '#717171',  // Chart 7: Neutral 500 - Tertiary
  '#0F172A',  // Chart 8: Horizon 600 - Dark accent
];

/**
 * Asset Category Colors - For wealth breakdown charts
 * Maps to specific asset types for consistency across all charts
 */
export const ASSET_COLORS = {
  pensions: '#1F2A44',      // Horizon 500 - largest category
  property: '#20B486',      // Spring 500 - real assets
  real_estate: '#20B486',   // Spring 500 - alias for property
  investments: '#5854E6',   // Violet 500 - investment accounts
  cash: '#6C83BC',          // Light Blue 500 - liquid assets
  business: '#E83E6D',      // Raspberry 500 - business interests
  chattels: '#E6C9A8',      // Savannah 500 - personal valuables
  equity: '#5854E6',        // Violet 500 - equities/stocks
  equities: '#5854E6',      // Violet 500 - equities
  stock: '#5854E6',         // Violet 500 - individual stocks
  fixed_income: '#1F2A44',  // Horizon 500 - bonds/fixed income
  bonds: '#1F2A44',         // Horizon 500 - bonds
  bond: '#1F2A44',          // Horizon 500 - single bond
  commodities: '#E6C9A8',   // Savannah 500 - commodities
  alternatives: '#DB2777',  // Raspberry 600 - alternatives
  fund: '#7C3AED',          // Violet 600 - funds
  etf: '#0D9488',           // Teal - ETFs
  other: SECONDARY_COLORS[400], // Horizon 400 - other/fallback
};

/**
 * Confetti Colors - For celebration overlays
 */
export const CONFETTI_COLORS = [
  PRIMARY_COLORS[500],      // Raspberry 500
  WARNING_COLORS[500],      // Violet 500
  SUCCESS_COLORS[500],      // Spring 500
  '#FDFAF7',                // Savannah 100
  SECONDARY_COLORS[500],    // Horizon 500
];

/**
 * Spending Category Colors - For expenditure donut charts
 * Extended palette for detailed spending breakdown (16 categories)
 */
export const SPENDING_COLORS = [
  '#5854E6', // Violet 500 - Mortgage Payments
  '#1F2A44', // Horizon 500 - Loan Payments
  '#6C83BC', // Light Blue 500 - Pension Contributions
  '#E83E6D', // Raspberry 500 - Protection Premiums
  '#20B486', // Spring 500 - Food & Groceries
  '#4C5D8A', // Info 700 - Transport
  '#DB2777', // Raspberry 600 - Healthcare
  '#7C3AED', // Violet 600 - Insurance
  '#A78BFA', // Violet 400 - Clothing & Personal
  '#34D399', // Spring 400 - Entertainment
  '#059669', // Spring 600 - Childcare
  '#047857', // Spring 700 - School Fees
  '#5A6FA3', // Info 600 - Holidays
  '#717171', // Neutral 500 - Other
  '#D1B08C', // Savannah 600 - Savings Deposits
  '#BE185D', // Raspberry 700 - Credit Card Spending
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
    bg: 'bg-savannah-100',
    text: 'text-horizon-500',
    border: 'border-light-gray',
    combined: 'bg-savannah-100 text-horizon-500',
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
  primary: '#1F2A44',       // Headings - horizon-500
  secondary: '#717171',     // Body - neutral-500
  tertiary: '#717171',      // Subtle - neutral-500
  muted: '#717171',         // Captions - neutral-500
  placeholder: '#94A3B8',   // Placeholder - horizon-400
  disabled: '#CBD5E1',      // Disabled - horizon-300
};

/**
 * Background Colors
 */
export const BG_COLORS = {
  page: '#F7F6F4',          // Page background - eggshell-500
  card: '#FFFFFF',          // Card/component background
  subtle: '#FDFAF7',        // Subtle highlight - savannah-100
  overlay: 'rgba(31, 42, 68, 0.75)',  // Modal overlay - horizon-500/75
};

/**
 * Border Colors
 */
export const BORDER_COLORS = {
  default: '#EEEEEE',       // light-gray
  hover: '#CBD5E1',         // horizon-300
  focus: '#5854E6',         // violet-500
  error: '#E83E6D',         // raspberry-500
  success: '#20B486',       // spring-500
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
    fontFamily: 'Segoe UI, Inter, system-ui, sans-serif',
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
    fontFamily: 'Segoe UI, Inter, system-ui, sans-serif',
  },
  tooltip: {
    style: {
      fontSize: '14px',
      fontFamily: 'Segoe UI, Inter, system-ui, sans-serif',
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
