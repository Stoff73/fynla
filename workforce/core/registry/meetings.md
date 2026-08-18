# Registry — Meetings

**Status:** Cadence ratified 2026-08-13 session 1. Mechanism constrained by the
Google Workspace being mid-setup.
**Owner:** CSJ. Amendments gated.

Times live in `rhythm.md` §4 and are not repeated. This file covers **how** a
meeting happens and what it produces.

---

## 1. The two weekly meetings

| | When | Shape |
|---|---|---|
| **Monday plan** | 09:00 | Forward. Carried-forward items, the week's commitment, decisions expected. Output is a commitment record. |
| **Friday delta** | 16:00 | Backward. Done · not done and why · drift · trunk amendments · interview batch · gap register. |

**Friday is computed against Monday, item by item.** Every Monday item appears
Friday as done, not done, or descoped — no fourth option, nothing disappears
between them. Full agendas: `rhythm.md` §4bis.

**Ad-hoc:** the Chief of Staff may call one when something cannot wait for Friday
and is too complex for a gate. **Maximum two per week**, or they erode the weekly
and the discipline of writing things down.

## 2. Mechanism — what is actually possible

**No agent can join a Google Meet.** There is no connector and no participant seat.
This is stated plainly because designing around it quietly produces a meeting
process that does not work.

| Capability | Status |
|---|---|
| Google Calendar — create events with Meet links | **Connected** |
| Gmail | **Connected** |
| Google Drive — read recordings and transcripts | Via the **service account** (`tools.md` §5) |
| Meet recording and transcription | **Requires Workspace Business Standard or above. Workspace is mid-setup — currently unavailable.** |
| An agent attending a Meet | **Not possible** |

### Pattern A — Meet as the record

A founder holds the meeting; Meet records and transcribes to Drive; the Archivist
ingests the transcript and writes the meeting document. **Best for anything with
other people in the room. Currently unavailable** — no Workspace tier yet.

### Pattern B — live session, Meet as the anchor

The meeting happens as a working conversation in the agent interface; the
Calendar/Meet event is the slot, and the session transcript is the record. Higher
bandwidth than A, because the Chief of Staff can act mid-conversation rather than
afterwards.

**Pattern B is the operative mechanism for Monday, Friday and ad-hoc meetings**
until the Workspace is live. Revisit then.

## 3. What every meeting produces

A document at `workforce/branches/meetings/<date>-<slug>/`, parent-linked to the
trunk like any branch:

- **Decisions**, each attributed to a named founder
- **Actions**, which become board items **before the note is closed**
- **Trunk amendments** raised as proposals — never left in the note

**Doctrine never lives in a meeting note.** If a meeting changes a rule, the rule
changes in the trunk and the note records that it did. Otherwise the trunk quietly
becomes fiction while the real rules live in a folder nobody reads
(`charter.md` §11).

## 4. Recording

If anyone other than a founder is present, recording carries consent and data-
protection obligations. Not a legal opinion — a flag. It joins the questions in
`05-perimeter.md` §6 if it ever becomes live.

## 5. Open

- Which calendar the meetings sit on once the Workspace exists. The connected
  account is currently personal (`slaterjoneschris@gmail.com`).
- Whether Azlan and Brett join Monday and Friday once activated, or receive the
  outputs.
- Where Meet recordings land in Drive, and whether the service account's scope
  reaches that folder.
