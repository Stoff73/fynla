/**
 * The one home for how Fynla renders a signature on any generated document.
 *
 * **The rule is one sentence: Fynla never draws a signature.** Not the testator's,
 * not an attorney's, not a witness's, not a certificate provider's. A signature line
 * is blank, always, on every document this application generates, whatever the user
 * has typed into the record.
 *
 * ## Why this is a module and not a convention
 *
 * It was a convention, and the convention failed twice in the same way.
 *
 * `lpaDocumentRenderer.js` drew the donor's, every attorney's and the certificate
 * provider's name onto the signature lines in a script font whenever `completed_at`
 * was set — which the user sets by pressing "Complete" in a wizard (W-0100).
 * `willDocumentRenderer.js` drew the testator's name once `signed_date` was typed,
 * and **each witness's name the moment a witness row existed, with no date condition
 * at all** (W-0101) — so the witnesses' marks appeared more readily than the
 * testator's own. Under Wills Act 1837 s.9 the witnesses' signatures are the
 * formality a will's validity turns on, and Fynla was drawing them.
 *
 * The two were fixed a day apart because they were two copies of one behaviour.
 * Rule 20: they now read this file, and **a third generator gets the rule for free.**
 * `__tests__/documentSignatures.spec.js` is the register — it runs the same
 * assertions over every renderer, and a new one is added to the array there.
 *
 * ## What a typed value is not
 *
 * A typed name is not a signature. **A typed date is not a signature either** — so
 * neither renderer pre-fills an execution date onto an attestation line, because a
 * clause reading "I have hereunto set my hand this 4 March 2026" asserts an event
 * Fynla did not witness just as surely as a drawn mark does. Names may appear in a
 * "Full Name" field, which labels a person rather than standing in for their hand.
 */

/**
 * Every generated document says this next to its signature lines. One sentence, one
 * home — surfaces compose around it rather than paraphrasing it.
 */
export const SIGNATURE_NOT_RECORDED = 'Fynla has not recorded any of these signatures.';

/** The blank date a signature line offers instead of a value the user typed. */
export const BLANK_DATE_RULE = '___________';

/**
 * The only way any renderer emits a signature line.
 *
 * It takes no arguments on purpose. There is no parameter for "the name to draw",
 * because there is no case in which a name is drawn.
 */
export function blankSignatureLine() {
  return '<div class="line"></div>';
}

/**
 * The rule, made executable — used by the register spec over every renderer.
 *
 * A bare `class="line"` is a signature line and must be empty. A field the user
 * filled in legitimately (a witness's printed name, address or occupation) carries
 * `class="line filled"` and is not matched here. Anything returned by this function
 * is a mark Fynla has drawn on somebody's behalf.
 *
 * @param {string} html rendered document HTML
 * @returns {string[]} the contents of any signature line that is not empty
 */
export function drawnSignatureLines(html) {
  return [...String(html).matchAll(/<div class="line">([\s\S]*?)<\/div>/g)]
    .map((match) => match[1])
    .filter((content) => content.trim() !== '');
}
