---
procedure_id: 'campaign.tool.capture_salary_sacrifice'
kind: tool_schema
module: campaign
provider: xai
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "capture_salary_sacrifice",
    "description": "Set salary_sacrifice flag on a specific DC pension owned by the user, with an optional employer NI rebate share. Use during the SaveTax campaign occupational-scheme capture state.",
    "parameters": {
        "type": "object",
        "properties": {
            "pension_id": {
                "type": [
                    "integer",
                    "null"
                ],
                "description": "ID of the dc_pension row to update, or null when you do not have a real id from this conversation — the system then resolves the user's pension. NEVER send 0 or an invented id."
            },
            "salary_sacrifice": {
                "type": "boolean",
                "description": "true if pension contributions are made via salary sacrifice."
            },
            "employer_ni_rebate_pct": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Optional. Share of the employer National Insurance saving rebated back into the pension as a fraction between 0 and 1 (e.g. 0.5 for 50%)."
            }
        },
        "required": [
            "pension_id",
            "salary_sacrifice",
            "employer_ni_rebate_pct"
        ],
        "additionalProperties": false
    },
    "strict": true
}
```
