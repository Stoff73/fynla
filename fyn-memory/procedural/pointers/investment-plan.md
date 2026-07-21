---
pointer_id: investment-plan
topic: The user's composed investment plan
triggers: [investment plan, what should i do with my investments, portfolio plan, investment recommendations, investment strategy]
mode: tool
handler: investment-plan
source_label: investment plan engine
version: 1
---

Use when the user asks what they should do with their investments or portfolio,
or for an investment plan. Returns the composed investment plan: strategies
ordered by what to do first, with conflicts resolved, claim tiers, and any locked
strategies with the single data point each needs. Computed live from the user's
current position — a heavier, explicit ask, so it is a tool not a blanket
pre-fetch. Acknowledge any strategy already surfaced this session when
re-surfacing it.
