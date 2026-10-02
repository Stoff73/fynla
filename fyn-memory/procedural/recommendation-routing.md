---
id: recommendation-routing
title: Recommendation turns — route to the composed plan
applies_when: >
  The user asks what they should do, asks for recommendations, strategies,
  ways to save tax, or next steps with their money.
version: 2
owner: CSJ
---

## Goal

A recommendation-intent turn answers from the user's actions list and the
composed strategy plan computed from their live position — never from memory or
generic advice.

## Steps

1. Choose `ground` so the reasoner runs; the reasoner must call
   `get_recommendations` or the `fetch_recommendations` skill rather than
   answering from prior context.
2. `recommendations` in the result is the user's actions list: the same items,
   in the same order, as their Actions page and dashboard, without anything they
   have marked done. When the user asks what to do or for their recommendations,
   answer from that list in that order, and name every item when they ask for
   all of them. `composed_tax_plan` carries the detail behind the tax items.
3. If a surfaced strategy is locked behind missing data, the turn should ask
   the single unlock question the plan names — not propose the action blind.
4. Check the conversation for strategies already surfaced this session; when
   one comes up again, acknowledge the earlier discussion and build on it
   rather than pitching it as new.
