// Page/data-specific "Edit details" opener for the /m surface (7-D). Each module
// screen passes its editable scope + the actual holdings it has loaded, so the
// Fyn opener names the user's real records instead of a generic menu. Built
// deterministically client-side from already-loaded data.
//
// updateScope: plural noun for the "update my <scope>" phrasing ("savings").
// addPhrase:   the full message to use when the page has no records yet.
// names:       the actual record names shown on the page.
export function buildEditPrompt(updateScope, addPhrase, names) {
  const clean = (names || []).filter(Boolean);
  if (clean.length === 0) return addPhrase;

  return `I'd like to update my ${updateScope}. I currently have: ${clean.join(', ')}.`;
}
