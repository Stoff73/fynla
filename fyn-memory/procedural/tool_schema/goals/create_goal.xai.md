---
procedure_id: 'goals.tool.create_goal'
kind: tool_schema
module: goals
provider: xai
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "create_goal",
    "description": "Create a new financial goal. Use when the user wants to save for something specific. Call this tool IMMEDIATELY. You MAY call this tool multiple times in the same turn when the user mentions multiple goals.",
    "parameters": {
        "type": "object",
        "properties": {
            "name": {
                "type": "string",
                "description": "Name of the goal (e.g. \"Holiday Fund\", \"House Deposit\", \"Emergency Fund\")"
            },
            "target_amount": {
                "type": "number",
                "description": "Target amount in pounds (£)"
            },
            "target_date": {
                "type": "string",
                "description": "Target date in YYYY-MM-DD format. Must be in the future."
            },
            "priority": {
                "type": "string",
                "enum": [
                    "critical",
                    "high",
                    "medium",
                    "low"
                ],
                "description": "\"critical\" for must-have goals. \"high\" for important. \"medium\" for nice-to-have. \"low\" for aspirational."
            },
            "goal_type": {
                "type": "string",
                "enum": [
                    "emergency_fund",
                    "home_deposit",
                    "property_purchase",
                    "holiday",
                    "education",
                    "wedding",
                    "car_purchase",
                    "retirement",
                    "wealth_accumulation",
                    "debt_repayment",
                    "custom"
                ],
                "description": "\"emergency_fund\" for emergency savings. \"home_deposit\" for house deposit saving. \"property_purchase\" for buying property. \"holiday\" for holidays. \"education\" for education costs. \"wedding\" for wedding. \"car_purchase\" for buying a car. \"retirement\" for retirement. \"wealth_accumulation\" for general wealth building. \"debt_repayment\" for paying off debt. \"custom\" for anything else."
            },
            "monthly_contribution": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Monthly contribution amount (£). How much the user plans to save each month towards this goal."
            }
        },
        "required": [
            "name",
            "target_amount",
            "target_date",
            "priority",
            "goal_type",
            "monthly_contribution"
        ],
        "additionalProperties": false
    },
    "strict": true
}
```
