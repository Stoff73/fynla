---
procedure_id: 'campaign.tool.capture_retirement_goals'
kind: tool_schema
module: campaign
version: 1
active: true
effective_from: 2026-07-04
---

```json
{
    "name": "capture_retirement_goals",
    "description": "Record the user's retirement goals. Call when the user states a target retirement age, a desired yearly retirement income, or the care costs they plan for in retirement. Ages are whole years between 55 and 75; amounts are yearly figures in pounds. Never guess a value the user did not state — omit the parameter instead.",
    "parameters": {
        "type": "object",
        "properties": {
            "target_retirement_age": {
                "type": "integer",
                "description": "The age at which the user wants to retire. Whole years only, 55 to 75 inclusive."
            },
            "target_retirement_income": {
                "type": "number",
                "description": "The gross annual income the user wants in retirement, in pounds."
            },
            "care_cost_annual": {
                "type": "number",
                "description": "The yearly amount, in pounds, the user plans for care in later life. 0 when they say they plan for none. Omit if they did not say."
            },
            "care_start_age": {
                "type": "integer",
                "description": "The age at which the user expects care costs might start. Omit if they did not say."
            }
        },
        "required": [],
        "additionalProperties": false
    }
}
```
