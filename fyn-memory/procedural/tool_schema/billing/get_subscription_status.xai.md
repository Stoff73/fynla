---
procedure_id: 'billing.tool.get_subscription_status'
kind: tool_schema
module: billing
provider: xai
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "get_subscription_status",
    "description": "Get the user's current subscription status — plan, billing cycle, current period end, trial end, next charge, and whether they have cancelled. Use when the user asks about their subscription, billing, when they will be charged next, whether their trial has ended, or whether their subscription is still active.",
    "parameters": {
        "type": "object",
        "properties": {},
        "required": [],
        "additionalProperties": false
    },
    "strict": true
}
```
