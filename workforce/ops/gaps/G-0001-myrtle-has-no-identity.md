---
id: G-0001
class: capability
agent: chief-of-staff
severity: blocking
opened: 2026-08-14
action: escalate
blocking: [charter-7-single-voice, people-3.1-attribution]
status: open
---

# Myrtle posts as CSJ

## The gap

The Slack connector authenticates as CSJ's user account. Every message the
workforce sends appears as **CSJ &lt;chris@fynla.org&gt;** with a small "Sent using
Claude" footer. Verified 2026-08-14 by reading `#fyn-decisions` back.

## Why it is blocking, not cosmetic

It breaks two ratified rules at once.

**`people.md` §3.1 — every decision records its author.** A message that looks
like it came from a founder, but did not, is an unattributable decision wearing a
founder's name. Azlan joined `#fyn-decisions` at 09:30 today and could reasonably
have read the GATE-0001 recommendation as CSJ's ruling.

**`charter.md` §7 — one voice.** The rule was written as *"anything not signed
Myrtle did not come from the workforce."* It inverts: everything Myrtle says looks
like it came from CSJ. Signing the message does not fix the avatar, the display
name, or what a person skimming actually sees.

**This is a design gap, not an operator error.** The design assumed the workforce
would have its own identity in a channel and never checked whether the transport
could provide one.

## Interim rule — in force now

**Nothing posted in Slack is a founder decision, regardless of the name on it.**
Founder decisions live in `workforce/ops/gates/` with a named author and a
timestamp. That record is authoritative; the Slack message is a notification of it.

Announced in `#fyn-decisions` 2026-08-14.

## The fix — a Slack app with a bot user

CSJ creates it once; the workforce then posts as Myrtle with her own name and
avatar. Steps are in `workforce/UNBLOCK.md` §7.

Once the bot token exists, `wf slack` sends through it rather than the connector.
The connector stays useful for *reading* channels — triage is unaffected, since
reading as CSJ is correct and carries no attribution problem.

## Status

Escalated to CSJ. Cannot be closed by the workforce — it needs an app created and
a token issued.
