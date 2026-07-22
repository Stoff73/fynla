---
procedure_id: 'analysis.tool.list_goals'
kind: tool_schema
module: analysis
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "list_goals",
    "description": "List all of the user's financial goals with their current progress, status, and IDs. Use this when the user asks about their goals, wants to see progress, or before updating/deleting a specific goal. This is a lightweight call — use it instead of get_module_analysis(goals) when you just need the goal list.",
    "parameters": {
        "type": "object",
        "properties": {},
        "additionalProperties": false
    }
}
```
