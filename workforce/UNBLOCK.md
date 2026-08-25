# Unblock — click by click

Everything the workforce cannot do for itself, in the order that unblocks the most.
No jargon. Each step says exactly where to click and how to know it worked.

**Total time: about 25 minutes.**

---

## 1 · Authorise Slack — 5 minutes

**Unblocks:** the workforce reading your channels, noticing problems, and telling
you things without you going to look. This is the biggest single unblock.

1. Open **claude.ai** in a browser (or the Claude desktop app).
2. Click your **name / profile picture**, bottom-left.
3. Click **Settings**.
4. Click **Connectors** in the left-hand menu.
5. Click the **+ Add** button (or "Browse connectors").
6. Find **Slack**. Click it.
7. Click **Connect**. A Slack window opens.
8. Choose the **fynla** workspace from the dropdown.
9. Click **Allow**.

**Worked if:** Slack now shows in your Connectors list marked *Connected*.

> If Slack says an admin must approve it: you are the workspace owner, so approve
> it at `fynla.slack.com` → **Settings & administration** → **Manage apps** →
> **Pending requests**.

---

## 2 · Create two Slack channels — 3 minutes

**Unblocks:** somewhere for the workforce to talk to you.

1. Open Slack, workspace **fynla**.
2. Bottom of the channel list, click **+ Add channels** → **Create a new channel**.
3. Name it exactly `fyn-brief`. Set it **Public**. Click **Create**.
4. Repeat for a second channel named exactly `fyn-decisions`.
5. **Rename `#social`:** click into `#social`, click the channel name at the top,
   click **Edit**, change the name to `marketing`, click **Save**.

**Worked if:** you can see `#fyn-brief`, `#fyn-decisions` and `#marketing`.

**What each is for:** `#fyn-brief` is everything the workforce says — read it when
you want. `#fyn-decisions` is only things needing an answer from you — never mute it.

---

## 3 · Authorise GitHub — 3 minutes

**Unblocks:** the workforce seeing pull requests and issues, and mission control
reading live state.

Same path as Slack:

1. **claude.ai** → your name → **Settings** → **Connectors**.
2. **+ Add** → find **GitHub** → **Connect**.
3. GitHub opens. Choose the account **Stoff73**.
4. Under *Repository access* choose **Only select repositories** → tick **fynla**.
5. Click **Approve and install**.

**Worked if:** GitHub shows *Connected*.

---

## 4 · Turn off branch protection — 4 minutes

**Unblocks:** the workforce actually merging. Right now it cannot, so the evidence
gate you approved does nothing.

1. Go to **https://github.com/Stoff73/fynla**
2. Click **Settings** (top row, far right, next to *Insights*).
3. Left menu, click **Branches**.
4. You will see rules for `main` and `dev`. For each one:
   - Click the **⋯** or **Edit** next to it
   - Scroll to the bottom
   - Click **Delete** (or **Delete rule**), then confirm

   *If instead you see a section called **Rulesets**, click it and delete the rules
   there the same way. Newer repositories use Rulesets rather than Branch
   protection — check both.*

**Worked if:** the Branches page shows no rules for `main` or `dev`.

> **Why this is safe:** you replaced human approval with the evidence gate — no
> merge happens without test output, a real browser run, and database state read
> back, produced by an agent that did not write the code. Nothing merges on trust.

---

## 5 · Merge the widow cleanup — 5 minutes

**Unblocks:** removes dead code that already caused one wrong conclusion.

Open Terminal and paste these one at a time:

```bash
cd /Users/CSJ/Desktop/fynla
git checkout fix/widow-persona-cleanup
./vendor/bin/pint
php -l database/seeders/PreviewUserSeeder.php
php -l database/seeders/AdvisorClientSeeder.php
```

Expect `No syntax errors detected` twice. Then:

```bash
git checkout dev
git merge fix/widow-persona-cleanup
```

**If anything errors, stop and paste the error to me.** Do not force it.

---

## 6 · Decide three things — 5 minutes

Reply with your answers; no clicking required.

**A. The autonomous fix loop** (`GATE-0001`). There is a GitHub Action that takes a
bug report, writes a fix, opens a pull request **and tries to merge it itself**, with
no human in between. Nobody can tell from the repo whether it is switched on in
production.

- Is it on in production? *(If unsure: GitHub → repo → **Settings** → **Secrets and
  variables** → **Actions** — if `CLAUDE_BOT_TOKEN` or `GITHUB_BUG_ISSUE_TOKEN` are
  listed, it is armed.)*
- Should it keep merging itself, or open the pull request and stop?

**B. Production safety list.** Should deploys to fynla.org still need your yes when
they touch: database migrations · login/security · payments · tax calculations ·
Fyn's prompts · public claims about Fynla? *(About 10% of deploys. Everything else
goes automatically.)*

**C. Out of hours.** Your window is 09:00–18:00. If production goes down at 23:00,
may the workforce wake you? *(Only for: site down, data leak, security incident,
payments failing.)*

---

## 7 · Give Myrtle her own Slack identity — 8 minutes

**Why:** right now the workforce posts through your account, so its messages show
**your name and email**. Azlan cannot tell what you said from what an agent said,
and a recommendation can be mistaken for your decision. (`G-0001`.)

1. Go to **https://api.slack.com/apps**
2. Click **Create New App** → **From scratch**
3. App Name: `Myrtle` · Workspace: **fynla** → **Create App**
4. Left menu → **OAuth & permissions**
5. Scroll to **Scopes** → **Bot Token Scopes** → **Add an OAuth Scope**.

   **Already added (correct):** `chat:write` · `chat:write.customize` ·
   `channels:read` · `channels:history`

   **Add these eight — without them Myrtle cannot really participate:**

   | Scope | Without it |
   |---|---|
   | `users:read` | She cannot tell who is speaking. Cannot address anyone by name, cannot check whether a founder has authority to approve something. |
   | `im:history` | Cannot read a direct message sent to her |
   | `im:write` | Cannot reply to one |
   | `im:read` | Cannot list her DM conversations |
   | `groups:history` | **Blind in every private channel** |
   | `groups:read` | Cannot see private channels exist |
   | `channels:join` | Cannot add herself to a public channel — you must `/invite` her to each, forever |
   | `files:read` | Cannot open a screenshot. Most bug reports are screenshots. |

   **Optional but recommended:** `reactions:write` — lets her acknowledge a message
   with an emoji instead of a reply. Useful for "seen, nothing needed from me"
   without adding noise. `mpim:history` + `mpim:read` if you use group DMs.

   **Also add `app_mentions:read`.** *(Correction — I first told you to leave this
   off. That was wrong.)* Without it, `@Myrtle` in a channel does nothing and she
   sits there silently while you address her. Charter §13 requires that a trigger is
   never *needed* — not that she ignores one. She reads everything **and** answers
   when spoken to.

6. **Install: left sidebar → "Install App"** (not a button on the OAuth page —
   that is where I sent you wrongly). Then **Install to Fynla** → **Allow**.
   *You must re-run this every time you add a scope.*
7. Copy the **Bot User OAuth Token** (starts `xoxb-`)
8. Left menu → **App home**. Three things here, and the third is the one that makes
   DMs work:
   - Set **Display Name** to `Myrtle` (icon under **Basic information → Display
     information** if you want a face)
   - Turn **Display Messages tab** ON
   - **Tick "Allow users to send Slash commands and messages from the messages
     tab"** — without this, clicking Myrtle in Slack says messaging is unavailable.
     It is off by default and it is the exact cause of that message.

   Then **reinstall** (sidebar → Install App). App Home changes need it.
9. `channels:join` lets her add herself, but she is not running on a timer yet, so
   for now invite her once per channel: in `#fyn-brief`, `#fyn-decisions`,
   `#all-fynla` and `#marketing`, type `/invite @Myrtle`

**Then give the workforce the token — do not paste it to me.** In Terminal:

```bash
cd /Users/CSJ/Desktop/fynla
echo 'SLACK_BOT_TOKEN=xoxb-paste-yours-here' >> .env
```

**Worked if:** `bash workforce/ops/wf.sh slack fyn-brief "test"` posts a message
showing **Myrtle** as the sender, not your name.

> The token lives only in `.env`, which is gitignored and hook-protected. No agent
> reads or repeats its value — `access.md` §2.

### What these scopes do and do not give you

**They do give you:** Myrtle reading every public and private channel she is in,
reading and replying to DMs, knowing who people are, opening shared files, and
posting under her own name and face.

**They do not give you real-time.** These scopes let her *fetch* messages; they do
not push new ones to her. Until something runs on a timer, she reads when asked
rather than as you type.

**Leave Socket Mode OFF for now.** I raised it too early and sent you looking for
it — that was unhelpful.

Socket Mode opens a websocket so Slack pushes events instantly with no public URL.
It is the right long-term answer. But it only does anything if **a process is
running on your machine holding that socket open**, and no such process exists yet.
Switching it on with nothing listening achieves nothing, and it changes how Slack
delivers events, so it can make debugging harder later.

**Order of work:**

| Now | Scopes + token + Messages tab. Myrtle posts as herself, can be DM'd, can be read. |
| Next | A process that runs on a timer — the same missing piece as the 17:30 brief. Polling every few minutes covers most of the value. |
| Then | Socket Mode, once that process exists, for instant rather than every-few-minutes. |

---

## 8 · Start Myrtle — 1 minute

**This is the step that makes her exist.** Everything before it gave her the
*ability* to speak and be read. Nothing was listening. That is why she did not
answer when you mentioned her — there was no process, so there was nobody home.

```bash
cd /Users/CSJ/Desktop/fynla
bash workforce/ops/myrtle-install.sh
```

That installs two background jobs on your Mac:

| Job | When |
|---|---|
| **Listener** | every 60 seconds — reads the channels, wakes her if a human has said something |
| **Daily brief** | 17:30, weekdays, posted to `#fyn-brief` |

**Test it:**

```bash
bash workforce/ops/myrtle-listen.sh          # one pass, right now
tail -f workforce/ops/log/myrtle-listen.log  # watch her
```

Then say something in `#fyn-brief` and wait a minute.

**Other commands:**

```bash
bash workforce/ops/myrtle-install.sh --status
bash workforce/ops/myrtle-install.sh --stop
```

### What the listener actually does

Every 60 seconds it fetches anything new in `#fyn-brief`, `#fyn-decisions` and
`#all-fynla`, **discards her own messages, other bots, and join/leave noise**, and
if a human has said something it wakes the chief-of-staff agent with those messages
plus her charter. She then decides: answer, confirm back, open a work item, or stay
quiet. Staying quiet is a valid outcome and will be the common one.

**She only runs while this Mac is awake.** A closed laptop is a sleeping colleague.
Moving her somewhere always-on is a later decision.

---

## Later, not now

- **WhatsApp** needs a Meta Business account and a verified number — a bigger job,
  and it should wait until Slack is proven.
- **Azlan and Brett** — send me their GitHub usernames, emails, Slack handles and
  working hours when you want them switched on.

---

## What happens after each step

| You do | The workforce can then |
|---|---|
| 1 + 2 | Read your channels, notice problems, tell you without being asked |
| 3 | See pull requests and issues; mission control shows live state |
| 4 | Merge its own work behind the evidence gate |
| 5 | — (housekeeping) |
| 6A | Stop two agents fixing the same bug |
| 6B + 6C | Run without asking you about routine things |
