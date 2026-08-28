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
    "description": "Create a new financial goal for the user. Use this when the user says they want to save for something specific. You MAY call this tool multiple times in the same turn when the user mentions multiple goals. If the user has only asked to add details without giving any specifics yet, do NOT call this tool — ask for the details first, and never invent names or values.",
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
            "is_essential": {
                "type": "boolean",
                "description": "Whether the household cannot go without this goal, as opposed to one they would defer if money were short. Only when the user says so."
            },
            "ownership_type": {
                "type": "string",
                "enum": ["individual", "joint"],
                "description": "Whether the goal belongs to the user alone or is shared with their linked spouse. Only when the user says so."
            },
            "joint_owner_id": {
                "type": "integer",
                "description": "User ID of the linked spouse the goal is shared with. Required when ownership_type is joint. A shared goal is ONE goal both see whole, not two halves, so there is no share to state."
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
