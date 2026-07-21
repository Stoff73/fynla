# Save Tax Allowance Registration CTAs Design

**Date:** 2026-07-15

## Goal

Restore two prominent registration calls to action on the Save Tax result page so a user reviewing their allowances can return directly to the existing registration form without manually scrolling back to the hero.

## Scope

The change applies to the shared public Save Tax result page rendered at `/savetax/plan`. The `/m` campaign pathway embeds this same page, so the implementation must work at mobile iframe widths as well as on the desktop public page.

No registration fields, tax calculations, allowance rendering, API behaviour, or authenticated Fyn behaviour will change.

## Approved Interaction

- Place one green `Register for free` link styled as a button after the allowance introduction and before the rendered allowance cards.
- Place a second identical link after the rendered allowance cards.
- Both links target `#register-form`, the existing registration form in the hero.
- Preserve the existing smooth-scroll behaviour from the public global stylesheet.
- Remove the current single `Return to the registration form` text link.
- Retain one registration form only; do not duplicate the form or its fields.

## Visual and Accessibility Requirements

- Use the established Spring action palette: `--spring-500`, with `--spring-600` on hover.
- Use existing typography, radius, transition, and spacing tokens.
- Each link must have a minimum 48px touch height, a visible keyboard focus state, and no icon.
- The registration form target must use scroll margin so it is not hidden behind persistent page chrome.
- The result page must remain free of horizontal overflow at 320px, 768px, and 1440px widths.

## Test Contract

The public Save Tax Playwright test must prove that:

1. Exactly two allowance-section links are exposed with the accessible name `Register for free`.
2. Both links have `href="#register-form"`.
3. Their document order is: top link, allowance grid, bottom link.
4. Activating either link returns the registration form to the visible viewport.
5. The existing hero form still contains exactly one `Register for free` submit button.
6. Existing allowance-state, layout-shift, runtime-error, and responsive overflow assertions remain green.
