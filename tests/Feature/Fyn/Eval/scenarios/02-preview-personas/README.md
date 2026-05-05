# 02 — Preview personas

6 scenarios — one basic smoke per preview persona (`young_family`, `peak_earners`, `entrepreneur`, `young_saver`, `retired_couple`, `student`).

Each scenario asserts:

- Persona seed loads cleanly (`is_preview_user = true`).
- `PreviewWriteInterceptor` blocks any write tool call attempted by the LLM.
- A representative advice query for the persona returns a sensible factual or recommendation response.
- No real-user data leaks across personas.

Source: `fyn-rubrics.md §B` coverage table — "Preview personas (6 personas × one basic smoke)".
