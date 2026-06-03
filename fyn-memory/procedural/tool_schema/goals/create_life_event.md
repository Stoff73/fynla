---
procedure_id: 'goals.tool.create_life_event'
kind: tool_schema
module: goals
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "create_life_event",
    "description": "Create a future life event that may impact the user's financial plan. You MAY call this tool multiple times in the same turn when the user mentions multiple events.",
    "parameters": {
        "type": "object",
        "properties": {
            "event_type": {
                "type": "string",
                "description": "Type of life event (e.g., \"marriage\", \"graduation\", \"career_change\", \"property_purchase\", \"retirement\")"
            },
            "event_date": {
                "type": "string",
                "format": "date",
                "description": "Expected date in YYYY-MM-DD format"
            },
            "description": {
                "type": "string",
                "description": "Description of the event"
            },
            "estimated_cost": {
                "type": "number",
                "description": "Estimated cost in pounds (if applicable)"
            }
        },
        "required": [
            "event_type",
            "event_date",
            "description"
        ],
        "additionalProperties": false
    }
}
```
