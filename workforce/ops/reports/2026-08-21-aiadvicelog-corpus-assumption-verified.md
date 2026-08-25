# Verifying my own assumption: can `AiAdviceLog` carry the `Q-02` corpus?

**Agent:** compliance-lead · **Date:** 2026-08-21
**Verifying:** the assumption flagged in `2026-08-21-live-risk-five-what-answering-requires.md` —
*"That `AiAdviceLog` records enough of each response to constitute a corpus. Read from
`05-perimeter.md` §2's description, not verified against the table."*

> **Read-only throughout.** Schema and call sites read from files; row counts from read-only
> `SELECT`s against the **local dev** database. **Nothing generated, no conversation run, no
> production query, nothing written.** Two Pest processes were running; nothing here touches a
> test database.

---

## The answer: it does not hold. Three ways, and the second one is the one that matters.

**"The corpus is a query, not an assembly job" was wrong.** I took it from a document describing
the table rather than from the table — the exact shape I spent the day finding in other people's
work, flagged as an assumption, and then had to be told to finish checking.

---

## 1. `ai_advice_logs` does not store what Fyn said

`database/migrations/2026_04_01_150000_create_ai_advice_log_table.php` — every column:

`user_id` · `conversation_id` · `message_id` · `query_type` · `classification` · `kyc_status` ·
`recommendations` · `tools_called` · `user_data_snapshot`

**There is no response-text column.** `recommendations` stores only `title`, `module` and
`estimated_saving`; `tools_called` stores tool *names*. **The log records that advice was given
and what it was classified as. It does not record what was said.**

**The text does exist** — `ai_messages.content`, a `text` column
(`2026_02_27_200002_create_ai_messages_table.php:17`) — linked by `ai_advice_logs.message_id`.

⚠️ **But that link is by convention, not constraint.** `message_id` is
`unsignedBigInteger()->nullable()` — **not** `foreignId()->constrained()`, unlike
`user_id` and `conversation_id` on the same table. **Nothing guarantees it resolves.**

**On its own this is the smaller correction:** the text is queryable, just from a different
table. If that were all, the plan would survive with one join.

---

## 2. The log only fires for what Fynla has already classified as advice — which makes it circular for `Q-02`

`app/Traits/HasAiChat.php:1416-1417`:

```php
if ($classification !== null
    && QuerySchemas::isAdviceType($classification['primary'])) {
```

**So `ai_advice_logs` is not "one Advice Case per substantive response". It is one per response
Fynla's own classifier called advice.**

**`Q-02` asks whether Fynla's guidance posture stays outside the regulated-advice perimeter.**
A corpus drawn from this table is **filtered by Fynla's own answer to that question.** It would
contain the responses Fynla already agrees are advice, and **exclude precisely the ones where
the question is live** — a response classified as guidance that a lawyer might read as advice
would never appear in the sample.

**That is a sampling defect, not a legal one, so it is squarely within competence to call it:
the corpus would be selected on the variable under test.** A lawyer given it could only confirm
that things Fynla labels advice look like advice.

**It is also the same failure as the "Compliant" badge, one level up.** The badge let the
product certify itself; this would let the product select the evidence for its own assessment.

### The correct basis

**`ai_messages` where `role = 'assistant'`, unfiltered**, with `ai_advice_logs` joined **as
metadata where it exists**. The classification becomes **a field the reviewer examines** — is
this one Fynla called guidance? — rather than a filter that decides what they see.

**That inverts the plan rather than adjusting it**, and it is strictly better: the disagreements
between Fynla's label and a lawyer's reading are the whole point, and only the unfiltered basis
can show them.

---

## 3. The write is best-effort, so coverage is not guaranteed even within advice types

`HasAiChat.php:1437-1440` wraps the insert in `try { … } catch (\Exception $e) { Log::warning(…) }`.
**A failed log is a warning and the turn proceeds.** Correct for the user — an audit write should
never break a conversation — but it means **absence of a row is not evidence of absence of
advice**, and nobody should compute a coverage rate from this table.

---

## 4. Preview personas: the proposed mechanism does not exist as a query

**This was the load-bearing part of my recommendation and it is the part that fails hardest.**

Read-only, local dev:

| Query | Result |
|---|---|
| `ai_advice_logs` rows | **0** |
| Conversations by `is_preview_user` | **90 conversations, all `is_preview_user = 0`. Zero belong to preview personas** |
| `ai_messages` where `role='assistant'` | 7, across 3 conversations |

**Preview personas have generated no conversations at all**, so there is nothing to query. **The
corpus cannot be produced from them by selecting; it has to be produced by driving conversations
against them and capturing the output.** That is a run task, not a query.

⚠️ **These row counts are local dev and nothing else.** They say **nothing** about production
volumes. **I did not query production and did not ask to** — the schema findings above are
durable, the counts are not.

---

## 5. The disclosure question — confirmed, and it is why §4 matters

**`user_data_snapshot` embeds the user's financial position in every row**
(`HasAiChat.php:1432-1436`): income (employment plus self-employment, summed), monthly
expenditure, employment status, marital status.

And **`ai_messages.content` is the conversation itself** — for a real user, their household's
financial position in prose.

**So handing real rows to an external firm is a disclosure question in its own right**, which is
what the persona route was proposed to avoid. **The route is right and it is more expensive than
I said** — the personas are the correct source and the material has to be generated rather than
found.

---

## 6. What would have to be built instead, and roughly how big

**Not a query. Probably not new infrastructure either — and somebody should check that before
scoping it as a build.**

What is needed: drive a fixed set of questions through the **real HTTP chat endpoint** against
each of the six preview personas, with real authentication, and keep the transcripts.

**`CLAUDE.md` already mandates that exact shape for evals** — *"eval = full HTTP journey"*, and
the memory `feedback_eval_must_drive_full_user_journey` records *"Eval drives HTTP with real
Sanctum auth"*. **So the harness that does this may already exist.**

**I have not verified that**, and I am not going to assert it after the day I have had. **The
first task for whoever takes this is to check the eval harness before scoping anything**, because
the difference between *"point the existing harness at a question set and keep the transcripts"*
and *"build a capture harness"* is most of the cost.

**Rough shape either way:** a question set (design work — it should deliberately include the
borderline guidance-vs-advice cases, which is the point), a run against six personas, and
transcript capture. **The question set is the part that needs compliance input; the harness is
not mine.**

---

## Done

- Verified the assumption **against the schema and the call site**, not the description.
- Found the response text is **not** in the table, and that the link to it is **unconstrained**.
- Found the **circularity**: the log is filtered by Fynla's own classification of advice, which
  is the variable `Q-02` tests. **This is the finding.**
- Established the correct basis — unfiltered assistant messages, classification as a field.
- Confirmed **preview personas have zero conversations**, so the proposed mechanism does not
  exist as a query.
- Confirmed the disclosure problem is real (`user_data_snapshot`), and that the persona route is
  right but costlier than stated.
- Sized the alternative and identified the one check that determines most of its cost.

## Not done, and why

- **No production query.** Not needed, not requested, not run. **The counts here are local dev
  and must not be read as production.**
- **Did not verify the eval harness can drive personas.** Named as the first task rather than
  assumed — asserting it would repeat the mistake this report exists to correct.
- **Did not read `QuerySchemas::isAdviceType()`'s list** of which types count as advice. The
  circularity holds whatever is on it, so it does not change the finding — **but whoever designs
  the question set should read it**, because it tells them which cases the current classifier
  already treats as advice.
- **Nothing generated, no conversation run, nothing written.**

## Needs

- **Assignment:** check whether the eval harness can drive scripted questions against preview
  personas and capture transcripts. **That single check decides most of the cost.**
- **Mine, on request:** the question set, once the mechanism is known. It needs compliance input
  precisely because it must include the borderline cases the classifier would otherwise exclude.
