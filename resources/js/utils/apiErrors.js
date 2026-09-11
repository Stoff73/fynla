/**
 * The one reader of a failed API call on the form surfaces (W-0544, W-0549).
 *
 * The backend answers a failed save with a structured body — a tier limit
 * carries `error_type: tier_limit_reached`, its `message`, the cap and the
 * upgrade `destination` — and the onboarding steps were throwing all of it
 * away for "Please try again", which can never succeed against a hard cap.
 */

/** entity_key (TierConfigurationSeeder count_caps) => how the cap is described */
export const ENTITY_LABELS = {
  property: 'properties',
  investment: 'investment accounts',
  pension_account: 'pensions',
  savings_account: 'bank accounts',
  goal: 'goals',
  life_event: 'life events',
};

/**
 * The tier limit a failed request hit, or null when it was not one.
 *
 * @param {unknown} error - an axios error
 * The plan the cap belongs to is the user's CURRENT one (read it from the
 * subscription payload, as tierLimitMixin does); the body only names the tier
 * that lifts it.
 *
 * @returns {{entityKey: string, entityLabel: string, cap: number, requiredTier: string,
 *   message: string, destination: object|null}|null}
 */
export function tierLimitFrom(error) {
  const body = error?.response?.data;
  if (!body || (body.error_type !== 'tier_limit_reached' && body.error !== 'tier_limit_reached')) {
    return null;
  }
  const entityKey = body.entity_key || '';
  const requiredTier = body.required_tier || 'premium';

  return {
    entityKey,
    entityLabel: ENTITY_LABELS[entityKey] || entityKey.replace(/_/g, ' ') || 'items',
    cap: Number(body.hard_limit) || 0,
    requiredTier,
    message: body.message || '',
    destination: body.destination || null,
  };
}

/**
 * What to show for a failed request: the server's own message when it sent
 * one, else the caller's fallback. Never axios's "Request failed with status
 * code 403", which is what `err.message` holds.
 *
 * @param {unknown} error
 * @param {string} fallback
 * @returns {string}
 */
export function apiErrorMessage(error, fallback) {
  const message = error?.response?.data?.message;
  return typeof message === 'string' && message.trim() !== '' ? message : fallback;
}
