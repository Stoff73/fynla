// Multi-select Fyn bubbles (M4, live fynla.org /m 2026-09-29): a state the
// director flags `multi_select: true` (on the quick_replies event and the
// stored message metadata) lets the user toggle chips locally, then its submit
// bubble sends every pick in ONE message. One home for the web panel
// (FynQuickReplies.vue) and /m (FynBubbles.vue); iOS mirrors it in
// FynConversationModel.swift.
//
// Wire format — must match OnboardingStateMachine::matchBubbles: the picked
// labels in display order, joined with ", ", with the submit bubble's label
// last, e.g. "Bank account, ISA, That's everything". Nothing picked sends the
// submit label alone.

export const MULTI_SELECT_SUBMIT_ID = 'done';
export const MULTI_SELECT_SEPARATOR = ', ';

/** True when tapping this bubble submits the step's picks. */
export function isMultiSelectSubmit(bubble) {
  return Boolean(bubble) && bubble.id === MULTI_SELECT_SUBMIT_ID;
}

/** True when tapping this bubble toggles it (not the submit, a director action or a link). */
export function isToggleable(bubble) {
  return Boolean(bubble) && !isMultiSelectSubmit(bubble) && bubble.action !== true && !bubble.route;
}

/** The selection with `id` added, or removed if it was already there. */
export function toggleSelection(selectedIds, id) {
  const current = Array.isArray(selectedIds) ? selectedIds : [];
  return current.includes(id) ? current.filter((x) => x !== id) : [...current, id];
}

/** The one message a submit tap sends, in the director's wire format. */
export function multiSelectMessage(bubbles, selectedIds, submitBubble) {
  const selected = Array.isArray(selectedIds) ? selectedIds : [];
  const picked = (Array.isArray(bubbles) ? bubbles : [])
    .filter((b) => isToggleable(b) && selected.includes(b.id))
    .map((b) => b.label);

  return [...picked, submitBubble.label].join(MULTI_SELECT_SEPARATOR);
}

/**
 * The bubble a submit tap hands to the surface's existing send path: the
 * submit bubble itself with its label replaced by the combined message, so
 * every surface keeps one "send the label" code path.
 */
export function multiSelectSubmission(bubbles, selectedIds, submitBubble) {
  return { ...submitBubble, label: multiSelectMessage(bubbles, selectedIds, submitBubble) };
}
