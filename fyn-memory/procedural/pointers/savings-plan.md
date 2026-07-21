---
pointer_id: savings-plan
topic: The user's composed savings plan
triggers: [savings plan, what should i do with my savings, emergency fund, savings recommendations, savings strategy]
mode: tool
handler: savings-plan
source_label: savings plan engine
version: 1
---

Use when the user asks what they should do with their savings, about an emergency
fund, or for a savings plan. Returns the composed savings plan: strategies
ordered by what to do first, with conflicts resolved, claim tiers, and any locked
strategies with the single data point each needs. Computed live from the user's
current position — a heavier, explicit ask, so it is a tool not a blanket
pre-fetch. Acknowledge any strategy already surfaced this session when
re-surfacing it.
