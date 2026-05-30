# Semantic memory — *facts* (Phase 1, future)

Durable, distilled knowledge about a user — the facts that outlive any single
episode ("retires 2041", "risk-averse", "two dependants"). Where episodic memory
is the diary, semantic memory is the dossier.

**Not built yet.** This is the Phase 1 store. When it lands it will be populated
by *consolidating* episodic memories (the retention sweep in
`episodic/RUBRIC.md` §4 promotes durable facts here and deletes the raw episode),
and read by the loop's `retrieve` action alongside episodic recall.

Planned layout (subject to design):

```
semantic/
  <user_id>/
    profile.md      distilled durable facts
    preferences.md  durable preferences / constraints
```

Until then the loop's `retrieve` over semantic memory is a no-op — see the
root `fyn-memory/README.md`.
