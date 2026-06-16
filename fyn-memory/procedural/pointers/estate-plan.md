---
pointer_id: estate-plan
topic: The user's composed estate plan
triggers: [estate plan, inheritance tax plan, will and power of attorney, estate recommendations, estate strategy]
mode: tool
handler: estate-plan
source_label: estate plan engine
version: 1
---

Use when the user asks about their estate, inheritance tax, will, or power of
attorney, or for an estate plan. Returns the composed estate plan: strategies
ordered by what to do first, with conflicts resolved, claim tiers, and any locked
strategies with the single data point each needs. Computed live from the user's
current position — a heavier, explicit ask, so it is a tool not a blanket
pre-fetch. Acknowledge any strategy already surfaced this session when
re-surfacing it.
