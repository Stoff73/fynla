---
pointer_id: protection-plan
topic: The user's composed protection plan
triggers: [protection plan, life cover, do i have enough cover, protection recommendations, protection strategy]
mode: tool
handler: protection-plan
source_label: protection plan engine
version: 1
---

Use when the user asks about their protection, life cover, or whether they have
enough cover, or for a protection plan. Returns the composed protection plan:
strategies ordered by what to do first, with conflicts resolved, claim tiers, and
any locked strategies with the single data point each needs. Computed live from
the user's current position — a heavier, explicit ask, so it is a tool not a
blanket pre-fetch. Acknowledge any strategy already surfaced this session when
re-surfacing it.
