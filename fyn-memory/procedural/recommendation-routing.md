---
id: recommendation-routing
title: Recommendation turns — route to the composed plan
applies_when: >
  The user asks what they should do, asks for recommendations, strategies,
  ways to save tax, or next steps with their money.
version: 1
owner: CSJ
---

## Goal

A recommendation-intent turn surfaces the composed strategy plan computed from
the user's live position — never an answer from memory or generic advice.

## Steps

1. Choose `ground` so the reasoner runs; the reasoner must call
   `get_recommendations` or the `fetch_recommendations` skill rather than
   answering from prior context.
2. If a surfaced strategy is locked behind missing data, the turn should ask
   the single unlock question the plan names — not propose the action blind.
3. Check the conversation for strategies already surfaced this session; when
   one comes up again, acknowledge the earlier discussion and build on it
   rather than pitching it as new.
