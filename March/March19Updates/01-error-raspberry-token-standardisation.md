# Error → Raspberry Token Standardisation

**Date:** 19 March 2026
**Branch:** `main`
**Commit:** `6c96214`

## Summary

Replaced all `error-*` Tailwind color tokens with `raspberry-*` across 43 Vue files to comply with the design system (fynlaDesignGuide.md v1.2.0). Also removed the legacy `error`, `warning`, and `info` semantic color aliases from `tailwind.config.js` since they are now unused.

## Rationale

The design system mandates using named palette tokens (`raspberry-*`, `violet-*`, `spring-*`, etc.) rather than semantic aliases (`error-*`, `warning-*`, `info-*`). The `error-*` tokens mapped to the same hex values as `raspberry-*`, so this is a visual no-op — purely a naming standardisation.

## Changes

### Token Replacement (43 Vue files)

All instances of `error-50` through `error-900` replaced with `raspberry-50` through `raspberry-900` in Tailwind classes (`bg-error-500` → `bg-raspberry-500`, `text-error-700` → `text-raspberry-700`, etc.).

### Tailwind Config (`tailwind.config.js`)

Removed 3 legacy semantic color definitions:
- `error` (100, 500, 600, 700) — duplicated `raspberry`
- `warning` (100, 500, 600, 700) — duplicated `violet`
- `info` (100, 500, 600, 700) — duplicated `horizon`

### Global CSS (`resources/css/app.css`)

Updated `.badge-error` and `.badge-info` classes to use palette tokens:
- `.badge-error`: `bg-error-100 text-error-700` → `bg-raspberry-100 text-raspberry-700`
- `.badge-info`: `bg-info-100 text-info-700` → `bg-horizon-100 text-horizon-700`

## Files Changed

43 Vue files (token swap) + `tailwind.config.js` + `resources/css/app.css`

## Testing

- Verified zero remaining `error-*` tokens in `resources/`
- Tailwind safelist already included `raspberry-*` variants
- Visual output unchanged (same hex values)
