---
pointer_id: recommendations
topic: Fyn's recommended actions for the user
triggers: [recommend, what should i do, suggestions, advice on what]
mode: tool
handler: recommendations
source_label: recommendation engine
version: 1
---

Use when the user asks what they should do, or for recommendations. The
recommendation engine computes these live from the user's current position --
exposed as a tool because it is a heavier, explicit ask, not a blanket pre-fetch.
