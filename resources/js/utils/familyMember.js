/**
 * The one home for "is this family member backed by a linked account, and may
 * the viewer manage it" on the front end.
 *
 * Mirrors `App\Models\FamilyMember::isLinkedAccount()` on the back end, which
 * computes the answer and ships it as `is_linked_account` on every serialised
 * family member. These helpers read that flag; they do not re-derive it.
 *
 * Components must not branch on `relationship === 'spouse'` to decide any of
 * this. That is a fact about the household, not about whether an account exists.
 * Branching on it is what printed "Linked account — edit or delete by logging
 * into the spouse's account" over a row with `linked_user_id` NULL, removed Edit
 * and Delete on the strength of the claim, and left the record unreachable from
 * every surface — including a second, permanent copy of the same spouse once the
 * real link was made (W-0051).
 */

/**
 * Is this row backed by a linked Fynla account?
 *
 * @param {object} member - A family member from the API
 * @returns {boolean}
 */
export function isLinkedAccount(member) {
  return member?.is_linked_account === true;
}

/**
 * Is this row shared in from the spouse's own records?
 *
 * @param {object} member - A family member from the API
 * @returns {boolean}
 */
export function isSharedFromSpouse(member) {
  return member?.is_shared === true;
}

/**
 * May the viewer edit and delete this record?
 *
 * Two reasons not to, and neither is the relationship: it belongs to the
 * spouse's own records, or it is the linked account itself, which is managed by
 * signing into that account. Everything else is an ordinary record the person
 * who entered it can correct or remove.
 *
 * @param {object} member - A family member from the API
 * @returns {boolean}
 */
export function canManageFamilyMember(member) {
  return !isSharedFromSpouse(member) && !isLinkedAccount(member);
}

/**
 * The line explaining why a record has no Edit or Delete, or telling the user
 * that a spouse on file is not a linked account. Returns an empty string when
 * there is nothing to say, so callers render on truthiness alone.
 *
 * @param {object} member - A family member from the API
 * @returns {string}
 */
export function familyMemberManagementNotice(member) {
  if (isLinkedAccount(member)) {
    return 'Linked account — can only be edited or deleted by signing into their account';
  }

  if (isSharedFromSpouse(member)) {
    return 'Managed by your spouse';
  }

  if (member?.relationship === 'spouse') {
    return 'Their account is not linked, so nothing is shared between you yet. Add them again with their email address to link the accounts.';
  }

  return '';
}

/**
 * THE relationship to show a user.
 *
 * `family_members.relationship` is an enum of four values and the product offers
 * six, so a partner is stored as `other_dependent` and a step child as `child`.
 * Rendering the stored value back meant the application told someone their
 * partner was a dependent — a false statement about their household, in the
 * software's own voice (W-0114). `stated_relationship` carries what they chose;
 * it is null when nothing was translated.
 *
 * **The server owns the wording.** `display_relationship` is computed by
 * `FamilyMember::getDisplayRelationshipAttribute()`, which also applies British
 * spelling ("other dependant", not "dependent") so web, `/m` and native inherit
 * one answer from one place. The fallbacks below are a degraded path for a
 * hand-built row that carries no computed value — they return the column's own
 * words rather than offering a second opinion on what to call anything. Do not
 * add a wording map here: that is the copy-in-lockstep failure this consolidation
 * removed.
 *
 * Returns lowercase words — every surface applies its own capitalisation, which
 * is why this replaced two divergent formatters, one using a hardcoded label map
 * and one a bare `replace('_', ' ')`.
 *
 * @param {object} member - A family member from the API
 * @returns {string}
 */
export function familyMemberRelationshipLabel(member) {
  const relationship = member?.display_relationship
    || member?.stated_relationship
    || member?.relationship
    || '';

  return String(relationship).replace(/_/g, ' ');
}

/**
 * The same label, title-cased, for surfaces that cannot lean on CSS.
 *
 * `familyMemberRelationshipLabel` returns lowercase words because the family
 * cards apply `capitalize` themselves. An `<option>` inside a `<select>` does
 * not reliably take that styling, so a surface rendering into one needs the
 * cased form — and it needs it from here, not from a fifth local formatter.
 *
 * @param {object} member - A family member from the API
 * @returns {string}
 */
export function familyMemberRelationshipTitle(member) {
  return familyMemberRelationshipLabel(member).replace(/\b\w/g, (c) => c.toUpperCase());
}
