/**
 * The one home for ownership-share display logic on the front end.
 *
 * Mirrors `App\Traits\CalculatesOwnershipShare` and `App\Support\SharedOwnership`
 * on the back end. Single-record architecture: ONE record holds the FULL value,
 * `ownership_percentage` is the PRIMARY owner's share, and the joint owner holds
 * the remainder.
 *
 * The API already computes the viewer's share and hands it over as `user_share`
 * (with `is_primary_owner`, `is_shared`, `owner_name` and `joint_owner_name`
 * alongside it). Every helper here prefers those fields. The arithmetic below is
 * only a fallback for payloads that do not carry them.
 *
 * Components must not do this arithmetic themselves. Doing so is what showed the
 * SAME joint account as 100%-owned to BOTH spouses — £190,000 of claimed
 * ownership against a £95,000 asset — while the wealth summary, reading the API,
 * showed the correct half to each (W-0014 / W-0015).
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

/** The primary owner's share when a shared asset carries none. */
export const DEFAULT_SHARED_PERCENTAGE = 50;

const VALUE_FIELDS = ['full_value', 'full_balance', 'current_value', 'current_balance', 'current_valuation'];

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
 * Does this record have a second party on it at all?
 *
 * A joint asset is joint whatever the split — including 100/0, which is how a
 * joint account looked before the write-side fix. Gating a "Joint" badge on
 * `percentage < 100` hid exactly the records that were wrong (W-0015).
 *
 * @param {Object} item - The asset item
 * @returns {boolean}
 */
export function isSharedRecord(item) {
  if (!item) return false;
  if (item.is_shared === true) return true;
  return isSharedOwnership(item.ownership_type) || item.joint_owner_id != null;
}

/**
 * Is the person looking at this record its primary owner (`user_id`)?
 *
 * Prefers the API's `is_primary_owner`; falls back to comparing `viewerId`.
 *
 * @param {Object} item - The asset item
 * @param {number|null} viewerId - The logged-in user's id, when known
 * @returns {boolean}
 */
export function isPrimaryOwner(item, viewerId = null) {
  if (!item) return false;
  if (typeof item.is_primary_owner === 'boolean') return item.is_primary_owner;
  if (viewerId != null && item.user_id != null) return Number(item.user_id) === Number(viewerId);
  // Nothing to distinguish the two sides: assume the primary owner, which is
  // what a list scoped to one user returns in every case but a joint record.
  return true;
}

/**
 * The FULL value of an asset, before any ownership split.
 *
 * @param {Object} item - The asset item
 * @param {string|null} valueField - Explicit field name, when the caller knows it
 * @returns {number}
 */
export function getFullValue(item, valueField = null) {
  if (!item) return 0;

  const fields = valueField ? [valueField, ...VALUE_FIELDS] : VALUE_FIELDS;
  for (const field of fields) {
    if (item[field] != null && item[field] !== '') {
      return parseFloat(item[field]) || 0;
    }
  }

  return 0;
}

/**
 * The percentage of the asset THIS VIEWER owns.
 *
 * The primary owner holds `ownership_percentage`; the joint owner holds the
 * remainder. Rendering the stored percentage to both sides is the labelling half
 * of the double-count (W-0015).
 *
 * @param {Object} item - The asset item
 * @param {number|null} viewerId - The logged-in user's id, when known
 * @returns {number} 0-100
 */
export function userSharePercent(item, viewerId = null) {
  if (!item) return 0;
  if (!isSharedRecord(item)) return 100;

  const stored = item.ownership_percentage == null
    ? DEFAULT_SHARED_PERCENTAGE
    : parseFloat(item.ownership_percentage) || 0;

  return isPrimaryOwner(item, viewerId) ? stored : 100 - stored;
}

/**
 * The value of the asset THIS VIEWER owns.
 *
 * Returns the API's `user_share` when present — that is the authoritative
 * figure, computed by the same trait the wealth summary and estate calculations
 * use. Only computes when the payload does not carry it.
 *
 * @param {Object} item - The asset item
 * @param {Object} [options]
 * @param {number|null} [options.viewerId] - The logged-in user's id, when known
 * @param {string|null} [options.valueField] - Explicit full-value field name
 * @returns {number}
 */
export function calculateUserShare(item, { viewerId = null, valueField = null } = {}) {
  if (!item) return 0;

  if (item.user_share != null && item.user_share !== '') {
    return parseFloat(item.user_share) || 0;
  }

  return getFullValue(item, valueField) * (userSharePercent(item, viewerId) / 100);
}

/**
 * Total value of multiple assets, counting only this viewer's shares.
 *
 * @param {Array} items - Array of asset items
 * @param {Object} [options] - Same options as calculateUserShare
 * @returns {number}
 */
export function calculateTotalUserShare(items, options = {}) {
  if (!Array.isArray(items)) return 0;

  return items.reduce((total, item) => total + calculateUserShare(item, options), 0);
}

/**
 * The name of the OTHER party on a shared record — never the viewer's own.
 *
 * The stored `joint_owner_name` is the counterparty only when the viewer is the
 * primary owner. Rendering it unconditionally told the spouse the property was
 * "Joint with <her own name>" (W-0016), and would name the wrong party on a
 * tenants-in-common asset shared with someone outside the household.
 *
 * @param {Object} item - The asset item
 * @param {number|null} viewerId - The logged-in user's id, when known
 * @returns {string|null} The counterparty's name, or null when unknown
 */
export function coOwnerName(item, viewerId = null) {
  if (!item || !isSharedRecord(item)) return null;

  return isPrimaryOwner(item, viewerId)
    ? (item.joint_owner_name || personName(item.joint_owner))
    : (item.owner_name || personName(item.user));
}

/**
 * Readable name from a nested user relation, as chattels and some other
 * resources return it instead of a flat `*_name` string.
 *
 * @param {Object|null} person
 * @returns {string|null}
 */
function personName(person) {
  if (!person) return null;

  const name = person.name || [person.first_name, person.surname].filter(Boolean).join(' ');

  return name.trim() || null;
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
