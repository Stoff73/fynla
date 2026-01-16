/**
 * Ownership calculation utilities for joint assets.
 *
 * Provides consistent ownership share calculations across the application.
 * Handles individual, joint, and tenants-in-common ownership types.
 */

/**
 * Valid ownership types in the FPS system.
 */
export const OWNERSHIP_TYPES = {
  INDIVIDUAL: 'individual',
  JOINT: 'joint',
  TENANTS_IN_COMMON: 'tenants_in_common',
  TRUST: 'trust',
};

/**
 * Check if an ownership type represents shared ownership.
 *
 * @param {string} ownershipType - The ownership type to check
 * @returns {boolean} True if the ownership is shared (joint or tenants in common)
 */
export function isSharedOwnership(ownershipType) {
  return (
    ownershipType === OWNERSHIP_TYPES.JOINT ||
    ownershipType === OWNERSHIP_TYPES.TENANTS_IN_COMMON
  );
}

/**
 * Calculate the user's share of an asset value.
 *
 * For individual ownership, returns the full value.
 * For shared ownership, returns the proportional share based on ownership_percentage.
 *
 * @param {Object} item - The asset item (account, property, etc.)
 * @param {string} [valueField='current_balance'] - The field containing the value
 * @returns {number} The user's share of the value
 *
 * @example
 * // Individual ownership
 * calculateUserShare({ current_balance: 10000, ownership_type: 'individual' })
 * // Returns: 10000
 *
 * @example
 * // Joint ownership (50/50)
 * calculateUserShare({ current_balance: 10000, ownership_type: 'joint', ownership_percentage: 50 })
 * // Returns: 5000
 */
export function calculateUserShare(item, valueField = 'current_balance') {
  if (!item) return 0;

  const value = parseFloat(item[valueField] || item.current_value || 0);

  if (item.ownership_type === OWNERSHIP_TYPES.INDIVIDUAL) {
    return value;
  }

  if (isSharedOwnership(item.ownership_type)) {
    const percentage = item.ownership_percentage || 50;
    return value * (percentage / 100);
  }

  // Default: return full value for unknown ownership types
  return value;
}

/**
 * Calculate the total value of multiple assets, accounting for ownership shares.
 *
 * @param {Array} items - Array of asset items
 * @param {string} [valueField='current_balance'] - The field containing the value
 * @returns {number} Total value of all assets (user's shares only)
 */
export function calculateTotalUserShare(items, valueField = 'current_balance') {
  if (!Array.isArray(items)) return 0;

  return items.reduce((total, item) => {
    return total + calculateUserShare(item, valueField);
  }, 0);
}

/**
 * Filter items to only those owned by a specific user.
 *
 * @param {Array} items - Array of items to filter
 * @param {number} userId - The user ID to filter by
 * @returns {Array} Items where user_id matches
 */
export function filterByOwner(items, userId) {
  if (!Array.isArray(items) || !userId) return [];
  return items.filter((item) => item.user_id === userId);
}

/**
 * Get the display label for an ownership type.
 *
 * @param {string} ownershipType - The ownership type
 * @returns {string} Human-readable label
 */
export function getOwnershipLabel(ownershipType) {
  const labels = {
    [OWNERSHIP_TYPES.INDIVIDUAL]: 'Individual',
    [OWNERSHIP_TYPES.JOINT]: 'Joint',
    [OWNERSHIP_TYPES.TENANTS_IN_COMMON]: 'Tenants in Common',
    [OWNERSHIP_TYPES.TRUST]: 'Trust',
  };
  return labels[ownershipType] || ownershipType;
}
