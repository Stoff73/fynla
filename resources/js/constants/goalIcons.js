/**
 * Goal type icon mapping.
 *
 * Shared constant used across Goal components to avoid duplication.
 */
export const GOAL_TYPE_ICONS = {
    emergency_fund: '🛡️',
    property_purchase: '🏠',
    home_deposit: '🔑',
    education: '🎓',
    retirement: '☀️',
    wealth_accumulation: '📈',
    wedding: '💍',
    holiday: '✈️',
    car_purchase: '🚗',
    debt_repayment: '💳',
    custom: '⭐',
};

/**
 * Get the icon for a goal type with a fallback.
 *
 * @param {string} goalType - The goal type key
 * @returns {string} The emoji icon
 */
export function getGoalIcon(goalType) {
    return GOAL_TYPE_ICONS[goalType] || '🎯';
}
