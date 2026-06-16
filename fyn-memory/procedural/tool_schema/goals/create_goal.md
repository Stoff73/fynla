---
procedure_id: 'goals.tool.create_goal'
kind: tool_schema
module: goals
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "create_goal",
    "description": "Create a new financial goal for the user. Use this when the user says they want to save for something specific. You MAY call this tool multiple times in the same turn when the user mentions multiple goals.",
    "parameters": {
        "type": "object",
        "properties": {
            "name": {
                "type": "string",
                "description": "Name of the goal (e.g., \"Holiday Fund\", \"House Deposit\")"
            },
            "target_amount": {
                "type": "number",
                "description": "Target amount in pounds"
            },
            "target_date": {
                "type": "string",
                "format": "date",
                "description": "Target date in YYYY-MM-DD format"
            },
            "priority": {
                "type": "string",
                "enum": [
                    "critical",
                    "high",
                    "medium",
                    "low"
                ],
                "description": "Priority level of the goal"
            },
            "goal_type": {
                "type": "string",
                "enum": [
                    "emergency_fund",
                    "house_deposit",
                    "holiday",
                    "education",
                    "wedding",
                    "car",
                    "retirement_supplement",
                    "other"
                ],
                "description": "Type of goal"
            },
            "monthly_contribution": {
                "type": "number",
                "description": "Optional monthly contribution amount in pounds. If provided, Fyn will assess whether this is sufficient to reach the target by the deadline."
            }
        },
        "required": [
            "name",
            "target_amount",
            "target_date",
            "priority",
            "goal_type"
        ],
        "additionalProperties": false
    }
}
```
