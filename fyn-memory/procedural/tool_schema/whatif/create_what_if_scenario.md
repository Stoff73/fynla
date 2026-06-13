---
procedure_id: 'whatif.tool.create_what_if_scenario'
kind: tool_schema
module: whatif
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "create_what_if_scenario",
    "description": "Create a persistent what-if scenario showing how changes would affect the user's financial plan. The scenario is saved and the user is navigated to the What If dashboard to see the comparison. Use this when the user asks \"what if\" questions about their finances.",
    "parameters": {
        "type": "object",
        "properties": {
            "name": {
                "type": "string",
                "description": "Short descriptive name for the scenario (e.g. \"Retire at 55\", \"Sell Main Residence\")"
            },
            "scenario_type": {
                "type": "string",
                "enum": [
                    "retirement",
                    "property",
                    "family",
                    "income",
                    "custom"
                ],
                "description": "Category of the what-if scenario"
            },
            "parameters": {
                "type": "object",
                "description": "The what-if parameter overrides. Keys: retirement_age, pension_contribution, sell_property, buy_property, divorce, marriage, new_child, income_change, job_loss, inheritance"
            },
            "description": {
                "type": "string",
                "description": "Your explanation of what this scenario models and the key assumptions"
            }
        },
        "required": [
            "name",
            "scenario_type",
            "parameters",
            "description"
        ],
        "additionalProperties": false
    }
}
```
