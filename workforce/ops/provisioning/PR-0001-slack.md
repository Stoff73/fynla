---
id: PR-0001
needs: Slack workspace authorisation for fynla.slack.com
kind: connector
requested_by: chief-of-staff
raised: 2026-08-13
blocking: [phase-3, phase-4]
spend: none
status: resolved
---

# PR-0001 — Slack authorisation

## What it unlocks

Two things, and only these:

1. **The decisions channel.** Gates, provisioning requests and interview batches
   reach you where you already are, rather than sitting in a queue you have to
   remember to open.
2. **Channel triage.** The Chief of Staff reads `#all-fynla` and `#social`,
   classifies what's said, confirms back, and turns real issues into tracked work
   (workforce design §7).

## Cost of not having it

Everything the workforce needs from you queues to the board and the control
centre. **You have to come looking rather than being told.** That is survivable
for a week and corrosive over a month — the gate timeout is 48 hours, so unread
gates park items and the workforce quietly slows down without anything looking
broken.

Triage does not degrade — it simply does not exist. Issues raised in Slack stay
invisible to the workforce entirely.

## Setup

**Authorise the Slack connector.** OAuth, in an interactive session — it cannot be
done from an automated one. Either the Slack MCP (`mcp.slack.com/mcp`) via
connector settings, or the `engineering:slack` plugin server via `/mcp`.

**No spend.** Free tier is sufficient for two channels and read access.

## Also needed, once authorised

Two channels created — `#fyn-brief` and `#fyn-decisions`, per
`registry/comms.md` §1.1. The workforce can create them itself once it has
authorisation, or CSJ can create them by hand; either is fine.

## Decision

*Pending.*

## Decision — 2026-08-14

**Approved and applied by CSJ.** Slack connected; `#fyn-brief` and `#fyn-decisions`
created; `#social` renamed `#marketing`. GitHub authorised, branch protection removed.

First workforce message posted to `#fyn-brief` 2026-08-14. The workforce is now
reachable without anyone coming to look for it — charter §13 satisfied on Slack.
