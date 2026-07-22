---
procedure_id: 'goals.tool.create_life_event'
kind: tool_schema
module: goals
provider: xai
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "create_life_event",
    "description": "Create a future life event that impacts the user's financial plan. Use for expected income (inheritance, bonus, property sale) or expenses (large purchase, wedding, home improvement). Call this tool IMMEDIATELY. You MAY call this tool multiple times in the same turn when the user mentions multiple life events. If the user has only asked to add details without giving any specifics yet, do NOT call this tool — ask for the details first, and never invent names or values.",
    "parameters": {
        "type": "object",
        "properties": {
            "event_name": {
                "type": "string",
                "description": "Short name for the event (e.g. \"Parents' Estate\", \"Kitchen Renovation\", \"Work Bonus\")"
            },
            "event_type": {
                "type": "string",
                "enum": [
                    "inheritance",
                    "gift_received",
                    "bonus",
                    "redundancy_payment",
                    "property_sale",
                    "business_sale",
                    "pension_lump_sum",
                    "lottery_windfall",
                    "custom_income",
                    "large_purchase",
                    "home_improvement",
                    "wedding",
                    "education_fees",
                    "gift_given",
                    "medical_expense",
                    "custom_expense"
                ],
                "description": "Income events: \"inheritance\", \"gift_received\", \"bonus\", \"redundancy_payment\", \"property_sale\", \"business_sale\", \"pension_lump_sum\", \"lottery_windfall\", \"custom_income\". Expense events: \"large_purchase\" for car/boat/etc, \"home_improvement\" for renovation/extension, \"wedding\", \"education_fees\" for school/uni, \"gift_given\", \"medical_expense\", \"custom_expense\"."
            },
            "event_date": {
                "type": "string",
                "description": "Expected date in YYYY-MM-DD format. Must be in the future."
            },
            "estimated_amount": {
                "type": "number",
                "description": "Estimated amount (£). How much money is expected to come in or go out."
            },
            "certainty": {
                "anyOf": [
                    {
                        "type": "string",
                        "enum": [
                            "confirmed",
                            "likely",
                            "possible",
                            "speculative"
                        ]
                    },
                    {
                        "type": "null"
                    }
                ],
                "description": "\"confirmed\" if definitely happening. \"likely\" if probably. \"possible\" if might. \"speculative\" if uncertain. Default \"likely\"."
            },
            "description": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Optional description with more details."
            }
        },
        "required": [
            "event_name",
            "event_type",
            "event_date",
            "estimated_amount",
            "certainty",
            "description"
        ],
        "additionalProperties": false
    },
    "strict": true
}
```
