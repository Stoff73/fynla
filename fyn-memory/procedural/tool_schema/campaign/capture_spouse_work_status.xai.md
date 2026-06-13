---
procedure_id: 'campaign.tool.capture_spouse_work_status'
kind: tool_schema
module: campaign
provider: xai
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "capture_spouse_work_status",
    "description": "Set whether the user's spouse currently works. Updates household_calculation_mode (dual_earner | single_earner_couple) and marriage_allowance_eligible accordingly.",
    "parameters": {
        "type": "object",
        "properties": {
            "spouse_works": {
                "type": "boolean",
                "description": "true if spouse has earned income, false otherwise."
            }
        },
        "required": [
            "spouse_works"
        ],
        "additionalProperties": false
    },
    "strict": true
}
```
