---
procedure_id: 'campaign.tool.capture_salary_sacrifice'
kind: tool_schema
module: campaign
version: 3
active: true
effective_from: 2026-09-01
---

```json
{
    "name": "capture_salary_sacrifice",
    "description": "Set salary_sacrifice flag on a specific pot-of-money pension owned by the user, with an optional employer National Insurance rebate share. Use during the SaveTax campaign occupational-scheme capture state.",
    "parameters": {
        "type": "object",
        "properties": {
            "pension_id": {
                "type": "integer",
                "description": "ID of the dc_pension row to update. Omit when you do not have a real id from this conversation — the system then resolves the user's pension. NEVER send 0 or an invented id."
            },
            "salary_sacrifice": {
                "type": "boolean",
                "description": "true if pension contributions are made via salary sacrifice."
            },
            "employer_ni_rebate_pct": {
                "type": "number",
                "description": "Optional. Share of the employer National Insurance saving rebated back into the pension as a fraction between 0 and 1 (e.g. 0.5 for 50%)."
            },
            "employment_income_basis": {
                "type": "string",
                "enum": [
                    "gross",
                    "post_sacrifice"
                ],
                "description": "Only when salary_sacrifice is true: whether the employment income already on file is \"gross\" (the full salary, including the pay given up) or \"post_sacrifice\" (what actually reaches the payslip). Ask the user \"Is that figure before or after the pay you give up?\" and send their answer. It decides whether their Annual Allowance is reduced. Omit if they have not said, or if salary_sacrifice is false."
            }
        },
        "required": [
            "salary_sacrifice"
        ],
        "additionalProperties": false
    }
}
```
