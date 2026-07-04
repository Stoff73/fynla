---
procedure_id: 'campaign.tool.capture_state_pension'
kind: tool_schema
module: campaign
provider: xai
version: 1
active: true
effective_from: 2026-07-04
---

```json
{
    "name": "capture_state_pension",
    "description": "Record the user's State Pension forecast. Call when the user gives a forecast amount and/or National Insurance qualifying years. The forecast is a yearly figure in pounds (convert a weekly figure by multiplying by 52 and say so in your reply). Omit anything the user did not state. If the user does not know their forecast or is unsure, do NOT call this tool at all — never pass 0 for a figure the user did not give. Never infer the State Pension age from another pension (a workplace or NHS scheme's normal retirement age is NOT the State Pension age). If the user only knows their State Pension age and not the forecast or qualifying years, do NOT call this tool.",
    "parameters": {
        "type": "object",
        "properties": {
            "forecast_annual": {
                "type": "number",
                "description": "The user's annual State Pension forecast in pounds. If the user gives a weekly figure, multiply by 52 and confirm the conversion in your reply."
            },
            "ni_years_completed": {
                "type": "integer",
                "description": "The number of full National Insurance qualifying years the user has completed. 0 to 60."
            },
            "state_pension_age": {
                "type": "integer",
                "description": "The age at which the user expects to receive their State Pension."
            }
        },
        "required": [],
        "additionalProperties": false
    },
    "strict": true
}
```
