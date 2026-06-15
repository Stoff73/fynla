---
pointer_id: retirement-plan
topic: The user's composed retirement plan
triggers: [retirement plan, pension plan, what should i do about my pension, retirement recommendations, retirement strategy]
mode: tool
handler: retirement-plan
source_label: retirement plan engine
version: 1
---

Use when the user asks what they should do about their retirement or pension, or
for a retirement plan. Returns the composed retirement plan: strategies ordered
by what to do first, with conflicts resolved, claim tiers, and any locked
strategies with the single data point each needs. Computed live from the user's
current position — a heavier, explicit ask, so it is a tool not a blanket
pre-fetch. Acknowledge any strategy already surfaced this session when
re-surfacing it.
