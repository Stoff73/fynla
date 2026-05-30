---
user_id: <int>
conversation_id: <int>
recorded_at: <ISO-8601>          # set by the agent at write time
salience: <0-5>                  # internal recall/retention signal — never user-facing
signals:                         # which RUBRIC §1 triggers fired
  - <trigger>
references:                      # optional — module/record ids this episode touches
  - <type>:<id>
procedural_version: <string>     # which procedure was active (provenance)
---

## Summary

<One or two sentences. Third person. Factual. Minimal PII — capture the signal,
not the transcript.>

## Detail

<Optional — a little more context if the summary alone loses meaning. Still
third-person, still minimal PII.>
