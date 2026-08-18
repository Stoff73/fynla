# Registry — Communications

**Status:** Proposed 2026-08-13, session 2. Awaiting CSJ ratification and Slack
authorisation. Nothing here is live.
**Owner:** CSJ. Amendments gated.

---

## 1. Slack

**Workspace:** `fynla.slack.com`
**Existing channels:** `#all-fynla`, `#marketing` (renamed from `#social`, CSJ 2026-08-13)
**Members:** three founders

### 1.1 Two new channels, not twelve

The original design proposed a channel per workstream — seven, plus five more for
gates, gaps, interviews and forensics. **That was designed for a company that does
not exist.** With three people, most of those channels would carry one message a
week, and a channel nobody opens is worse than no channel.

**Proposed, total: two.**

| Channel | Purpose | Expectation |
|---|---|---|
| `#fyn-brief` | Everything the workforce says: daily brief, Monday plan, Friday delta, mission state, judgements, completions. **One thread per work item.** | Read when you want. Never requires a reply. |
| `#fyn-decisions` | Gates, provisioning requests, interview batches. | **Never muted. Every message here needs a human reply.** |

**The split is the whole point.** Separating *things that need you* from *things
that inform you* is the one distinction worth a second channel. Mixed together,
the decisions drown in the status and the channel gets muted within a fortnight —
at which point the gate queue silently stops working.

Threading inside `#fyn-brief` gives per-work-item separation without channel
sprawl, and it is where "who handed what to whom" becomes visible for free.

**Deliberately not created:** per-workstream channels, `#fyn-gaps`,
`#fyn-firehose`. The gap register and event log live in the control centre and the
tree. If forensics in Slack are ever genuinely wanted, that is one channel added
later, on evidence.

### 1.1b The workforce speaks as Myrtle

**Ratified 2026-08-14.** The Chief of Staff signs every outbound message —
Slack, WhatsApp, email — as **Myrtle**. Never "the Chief of Staff", never a bot
label.

A named colleague is answerable in a way a system is not: people ask a name why it
did something and expect a reply. It also makes the single-voice rule visible —
anything in a channel not signed Myrtle did not come from the workforce.

**The role stays `chief-of-staff` everywhere internally** — board, event log, agent
definitions, trunk. Myrtle is who speaks; `chief-of-staff` is what runs.

### 1.2 Triage — existing channels only

The Chief of Staff joins `#all-fynla` and `#marketing` and triages them under the
workforce design §7: classify, confirm back, assign, report once. It creates no
channels of its own for triage and speaks nowhere else.

**`#marketing` is in scope for the publishing gate.** It is marketing output, not
team banter — so anything heading for publication that surfaces there is subject
to `charter.md` §10, and articles route into the existing approver rather than a
new one.

**No lead is in any Slack channel.** Single-reader triage is the security boundary
(`charter.md` §3), not merely an editorial preference.

### 1.3 Open

Channel names above are a proposal. Rename freely — the workforce reads the
registry, not hardcoded names.

## 2. WhatsApp

**Group: "Fynla 500."** Three founders. Already exists.

Design in the workforce spec §9.2: founders' channel plus pager, four templates for
outside the 24-hour window, single-voice triage identical to Slack. **Phase 5** —
not before Slack triage is proven.

Note the group being pre-existing changes nothing mechanically: the workforce still
needs the WhatsApp Business Cloud API to participate, and an ordinary group chat is
not reachable through it. Whether "Fynla 500" becomes the integration point or a
parallel business number is set up is a Phase 5 decision.

## 3. Email

| Known | Status |
|---|---|
| `ADMIN_EMAILS` env key | **All three founders** (CSJ, 2026-08-13) |
| Transactional sender | `MAIL_FROM_ADDRESS` |
| Marketing sender | `MAIL_MARKETING_FROM_ADDRESS` — deliberately separate |
| CSJ personal | `slaterjoneschris@gmail.com` — the connected Gmail and Calendar account |

**Fynla Google Workspace: being set up** (CSJ, 2026-08-13). Until it exists,
everything runs on the personal account, so Meet recording and transcription are
unavailable and **Pattern B — the live working session — is the only meeting
mechanism** (`08-process.md` §8.5). Revisit once the Workspace is live and its tier
is known; Business Standard or above is required for recording.

## 4. Bug intake — already exists

`GITHUB_BUG_ISSUE_TOKEN`, `GITHUB_BUG_ISSUE_REPO`, `GITHUB_BUG_ISSUE_ENABLED`, and
a commit titled *"mobile bug reporter → GitHub issue → autonomous Claude fix loop"*.

**So a bug pipeline already runs, and it already ends in an autonomous fix loop.**
The workforce must route into it rather than build a second intake — same
reasoning as the marketing approver (`charter.md` §10). **Needs mapping in
session 2 follow-up:** how it triggers, what it does today, and whether the Build
lead subsumes it or leaves it alone.

## 5. Not in use

No Discord, Teams or other chat platform found. The Laravel `slack` **log** driver
in `config/logging.php:76` is a log sink with no configured webhook — unrelated to
the above, and not a two-way integration.
