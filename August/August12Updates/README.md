# August 12 Updates — pointers, not copies

The sixteen documents that used to sit in this folder were **byte-identical
copies** of files that already live under `docs/`. The copies are gone; the
originals are untouched. Nothing was lost — verified by checksum before removal
on 17 August 2026.

`docs/` is canonical. Do not copy these back here:
`tests/Architecture/ClientParityLedgerTest.php:85` pins the literal path of the
PR7 evidence document, so moving it breaks the test suite.

## The iOS and `/m` parity wave (PRs #674–#689)

**Design**
- [ios-m-parity-debugging-design](../../docs/superpowers/specs/2026-08-09-ios-m-parity-debugging-design.md)

**Plans**
- [ios-m-parity-foundations](../../docs/superpowers/plans/2026-08-09-ios-m-parity-foundations.md)
- [canonical-details-navigation-reuse](../../docs/superpowers/plans/2026-08-10-canonical-details-navigation-reuse.md)
- [contextual-fyn-conversation-history](../../docs/superpowers/plans/2026-08-10-contextual-fyn-conversation-history.md)
- [financial-data-parity](../../docs/superpowers/plans/2026-08-10-financial-data-parity.md)
- [ios-m-projections](../../docs/superpowers/plans/2026-08-10-ios-m-projections.md)
- [ios-m-parity-closure](../../docs/superpowers/plans/2026-08-11-ios-m-parity-closure.md)
- [ios-m-personalised-achievements](../../docs/superpowers/plans/2026-08-11-ios-m-personalised-achievements.md)

**Evidence**
- [pr1 parity evidence](../../docs/testing/2026-08-09-ios-m-parity-pr1-evidence.md)
- [canonical details evidence](../../docs/testing/2026-08-10-canonical-details-ios-m-evidence.md)
- [contextual Fyn conversation history evidence](../../docs/testing/2026-08-10-contextual-fyn-conversation-history-evidence.md)
- [financial data parity evidence](../../docs/testing/2026-08-10-financial-data-parity-evidence.md)
- [projection parity evidence](../../docs/testing/2026-08-10-projection-parity-evidence.md)
- [PR6 personalised achievements](../../docs/superpowers/evidence/2026-08-11-pr6-personalised-achievements.md)
- [PR7 iOS/`m` parity closure](../../docs/superpowers/evidence/2026-08-11-pr7-ios-m-parity-closure.md) — **test-pinned path**

**Ledger**
- [client-parity-ledger](../../docs/architecture/client-parity-ledger.md) — the
  M-01–M-34 matrix `ClientParityLedgerTest` checks (722 assertions)

## Everything else, by date

[`docs/INDEX.md`](../../docs/INDEX.md) — chronological index of all 176 dated
documents across `docs/superpowers/{plans,specs,evidence}`, `docs/testing`,
`docs/plans` and `docs/architecture`.
