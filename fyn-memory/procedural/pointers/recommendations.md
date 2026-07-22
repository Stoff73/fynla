---
pointer_id: recommendations
topic: Fyn's recommended actions for the user
triggers: [recommend, what should i do, suggestions, advice on what]
mode: tool
handler: recommendations
source_label: recommendation engine
version: 2
---

Use when the user asks what they should do, or for recommendations. Returns
the composed tax plan: strategies ordered by what to do first, with conflicts
resolved, claim tiers, a combined annual saving, and any locked strategies
with the single data point each needs. Computed live from the user's current
position -- exposed as a tool because it is a heavier, explicit ask, not a
blanket pre-fetch. Check the conversation for strategies already surfaced this
session and acknowledge prior discussion when re-surfacing one.
