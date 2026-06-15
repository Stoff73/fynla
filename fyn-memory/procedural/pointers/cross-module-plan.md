---
pointer_id: cross-module-plan
topic: The user's overall cross-module plan, ranked by what they can afford
triggers: [what should i do, can i afford, overall plan, where do i start, my whole plan, prioritise]
mode: tool
handler: cross-module-plan
source_label: cross-module plan engine
version: 1
---

Use when the user asks what they should do overall, where to start, how to
prioritise across everything, or what they can afford. Returns the cross-module
composite plan: every module's strategies combined and ranked by affordability
against the monthly surplus left after their goal contributions, each tagged as
fitting within the surplus, partially fitting, or beyond the current surplus,
with the running surplus consumed. Goals enter as demands the strategies give
way to. Nothing is dropped — strategies beyond the current surplus are shown as
such, not hidden. Computed live and heavier than a single module, so it is a
tool, not a blanket pre-fetch.
