---
procedure_id: 'onboarding.tool.capture_monthly_expenditure'
kind: tool_schema
module: onboarding
version: 1
active: true
effective_from: 2026-09-16
---

```json
{
    "name": "capture_monthly_expenditure",
    "description": "Record the user's total monthly spending as a single figure (simple entry) during onboarding. Use when the user gives one ballpark monthly outgoings figure rather than category amounts. Call this once per turn. Do not call any other tool.",
    "parameters": {
        "type": "object",
        "properties": {
            "monthly_total": {
                "type": "number",
                "description": "Everything that goes out each month in GBP — rent or mortgage, bills, food, transport. Strip currency symbols and commas; \"2.4k\" = 2400."
            }
        },
        "required": [
            "monthly_total"
        ],
        "additionalProperties": false
    }
}
```
