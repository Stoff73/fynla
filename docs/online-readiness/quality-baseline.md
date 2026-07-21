# Quality Baseline

Captured on 2026-07-11 against the Gate 0 repository state.

## PHP formatting ratchet

- Command: `./vendor/bin/pint --test --format=json`
- Existing files requiring formatting: 84
- Blocking rule: PHP files added, copied, or modified in `QUALITY_BASE..QUALITY_HEAD` must pass Pint.
- Legacy rule: the 84-file baseline is recorded debt and must not increase; it is not reformatted incidentally by the online-readiness programme.

The changed-file gate is implemented by `scripts/quality/pint-changed.sh`. A deliberate debt-reduction change may lower this baseline after the full command is rerun and the new count is recorded here.

## JavaScript and Vue lint ratchet

- Initial full recommended-preset scan: 49,056 findings (463 errors and 48,593 warnings).
- Dominant legacy noise: 48,416 Vue template-format warnings and 296 unused-variable errors.
- Blocking rule: JavaScript and Vue files added, copied, or modified in `QUALITY_BASE..QUALITY_HEAD` must pass the essential Vue rules plus the explicit Fynla correctness rules with zero warnings.
- Full-audit command: `npm run lint:full`.

The changed-file gate is implemented by `scripts/quality/eslint-changed.mjs`. It prevents new correctness debt without mass-formatting or making unrelated legacy files part of an otherwise focused pull request.
