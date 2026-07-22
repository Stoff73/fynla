---
procedure_id: 'analysis.tool.get_recommendations'
kind: tool_schema
module: analysis
version: 2
active: true
effective_from: 2026-06-11
---

```json
{
    "name": "get_recommendations",
    "description": "Get the user's personalised, ranked financial recommendations across all modules, plus a composed tax plan (composed_tax_plan) ordered by what to do first with conflicts resolved and a combined annual saving. Call this whenever the user asks what they should do, wants strategies, or asks about saving tax. Present the top 3 to 5 items in sequence order: state each title with its pound saving, quote the working for mechanical-tier items directly, hedge judgement-tier items (\"you may want to consider\"). If composed_tax_plan.locked is non-empty, tell the user how many further strategies unlock and what single data point each needs. Offer to go through the remaining items rather than dumping the full list. Before presenting, check this conversation for strategies you have already surfaced this session; when re-surfacing one, acknowledge the earlier discussion and build on it rather than pitching it as new.",
    "parameters": {
        "type": "object",
        "properties": {},
        "additionalProperties": false
    }
}
```
