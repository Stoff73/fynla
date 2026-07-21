---
procedure_id: 'analysis.tool.list_life_events'
kind: tool_schema
module: analysis
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "list_life_events",
    "description": "List all of the user's life events with dates, amounts, and IDs. Use this when the user asks about their life events, upcoming events, or before updating/deleting a specific event. This is a lightweight call — use it instead of get_module_analysis(goals) when you just need the event list.",
    "parameters": {
        "type": "object",
        "properties": {},
        "additionalProperties": false
    }
}
```
