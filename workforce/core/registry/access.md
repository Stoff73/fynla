# Registry — Access

**Status:** Drafted from discovery 2026-08-13, session 2. Awaiting CSJ correction.
**Owner:** CSJ. Amendments gated.

**This file records where credentials live and who can grant what. It never
records a credential.** Any agent that writes a secret value into this tree has
committed a defect, and the Quartermaster treats it as an incident, not a tidy-up.

---

## 1. Where secrets live

| Location | Contents | Reachable by the workforce? |
|---|---|---|
| `/Users/CSJ/Desktop/fynla/.env` | Local development | **No.** `env-guard.sh` denies Write/Edit to `.env` and `.env.*`; `.env.example` is exempt. Gitignored. |
| `deploy/fynla-org/.env.production` | Production template | In-repo. **Verify this holds no live values** — see questions. |
| `deploy/csjones-fynla/.env.production` | Dev/staging template | Same. |
| Production server `.env` | Live production secrets | Only via `ssh-fynla`. Never copied locally, never quoted in the tree. |
| csjones server `.env` | Live staging secrets | Same, via csjones SSH. |
| macOS Keychain / password manager | SSH passphrases, personal logins | Outside the workforce entirely |

`CLAUDE.md` is explicit: credentials live only in each server's `.env`, never in
the repo or in chat. That rule extends to this tree unchanged.

## 2. What the workforce may do with credentials

| | |
|---|---|
| **May** | Name a credential; report that one is missing, expired or unauthorised; raise a provisioning request for one |
| **Never** | Read, echo, copy, paste, log, commit, or transmit a value — including into a gate file, a report, an evidence pack, or a message |

An authentication failure is reported as *"`X` failed to authenticate"* and never
by quoting what was tried.

## 3. Who grants what

Any founder may authorise, per `people.md` §1 — but only **CSJ is active** during
staged rollout, so in practice every access grant is CSJ until Azlan and Brett are
registered.

| Access | Granted by |
|---|---|
| MCP connector OAuth (Slack, GitHub, Drive) | The founder whose account it is. In an interactive session — cannot be done from an automated one. |
| Server SSH | CSJ |
| Third-party API keys | CSJ, per the domain table |
| Repository permissions | CSJ (`@Stoff73` owns the origin) |

## 4. Standing constraint

**Every access grant is also a spend decision until proven otherwise** — free
tiers requiring a card, and anything that renews, are gated under `charter.md` §5.
Provisioning requests state which of the two they are.

## 5. A trap in `deploy/*/.env.production`

**Checked 2026-08-13: both files contain placeholders** — `YOUR_PRODUCTION_…`,
`YOUR_REVOLUT_…`, `base64:GENERATE…`. No live secret is committed. Not an incident.

**But both files are tracked by git while `.gitignore:14` lists
`.env.production`.** Ignore rules do not apply to already-tracked files, so the
protection reads as present and is not. If anyone ever fills in real values in
place, git will stage them and `.gitignore` will not intervene.

**Standing rule regardless:** these files are **templates only**. No agent writes a
value into either. Filling them in happens on the server, in the server's own
`.env`, never in the repo.

### Fix — started 2026-08-13, NOT COMPLETE

CSJ authorised the fix. It is **staged but uncommitted**, and a stale lock is
blocking git.

| | State |
|---|---|
| `deploy/*/.env.production.example` created | **Done** — on disk, staged |
| Rename staged in the index (`R100` both files) | **Done** |
| Commit | **NOT DONE** |
| `.git/index.lock` | **Present and stale** — will block all git writes until removed |

The lock came from a `git commit` that exceeded the sandbox timeout; the mount
denies deleting it. **CSJ must run, locally:**

```bash
cd /Users/CSJ/Desktop/fynla
rm -f .git/index.lock
git commit -m "chore(deploy): untrack .env.production, keep templates as .example"
```

To back the whole thing out instead:

```bash
rm -f .git/index.lock
git reset HEAD deploy/fynla-org/.env.production.example deploy/csjones-fynla/.env.production.example
rm deploy/fynla-org/.env.production.example deploy/csjones-fynla/.env.production.example
```

**Lesson recorded for the workforce:** git write operations on this repository
routinely exceed two minutes — `git status` alone times out. Any agent performing
git writes here must expect that and must not leave a lock it cannot clear. This
belongs in the Build and Quality lead charters.

## 6. Open

- Where SSH passphrases are held. `SSH_PASSPHRASE` appears as an env key, which
  implies the passphrase sits in a `.env` alongside what it protects.
- Whether a shared password manager exists across the three founders — needed
  before Azlan and Brett activate.
