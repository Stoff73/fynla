---
procedure_id: 'billing.tool.get_current_plan'
kind: tool_schema
module: billing
provider: xai
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "get_current_plan",
    "description": "Get the details of the user's current subscription plan — name, tier slug, billing cycle, price in pounds, and the list of features included. Use when the user asks what plan they are on, what features they have, or what they are paying.",
    "parameters": {
        "type": "object",
        "properties": {},
        "required": [],
        "additionalProperties": false
    },
    "strict": true
}
```
