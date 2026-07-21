# Episodic memory — *what happened*

Salient episodes from real interactions, written by **the Fyn agent** at the end
of a turn (the loop's `learn` action) — but only on **your** terms. The agent
applies `RUBRIC.md` (which **you** author) to decide whether a turn is worth
recording, what to capture, and how to summarise it.

## The contract

- **You set the rules** → `RUBRIC.md`. What counts as salient, what to capture,
  how to score it, how long to keep it.
- **Fyn writes the episodes** → `episodes/…`, one markdown file per recorded
  episode, shaped like `_TEMPLATE.md`, only when the rubric says so.
- **Fyn reads them back** → the loop's `retrieve` action recalls a user's
  relevant episodes into working memory before the planner runs.

## Layout

```
episodic/
  RUBRIC.md            ← you author: the salience rubric + capture/retention rules
  _TEMPLATE.md         the shape of an episode file
  episodes/
    <user_id>/<YYYY>/<YYYY-MM-DD>-<slug>.md     ← agent-written (gitignored)
```

Episodes are partitioned by user (recall is always user-scoped) then by year
(keeps directories small and retention sweeps cheap). The runtime episode files
are **gitignored** — they are user data, never committed; PII lives only in the
configured store path, subject to the same retention/erasure rights as the rest
of the user's data.

## Guard-rails (non-negotiable, enforced in code not the rubric)

- An episode is **only** ever recalled for the user it belongs to.
- Episodes are **read-only memory** — recalling one never grants a write.
- Erasure / GDPR deletion removes a user's whole episode tree.

The rubric governs *salience and content*; these guard-rails govern *safety* and
stay in code.
