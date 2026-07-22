---
procedure_id: 'campaign.tool.capture_spouse_household_data'
kind: tool_schema
module: campaign
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "capture_spouse_household_data",
    "description": "Capture working-spouse data for dual_earner households (spouse_works=yes path). Writes to tax_strategy_household_inputs.",
    "parameters": {
        "type": "object",
        "properties": {
            "spouse_annual_income": {
                "type": "number",
                "description": "Spouse gross annual income in pounds."
            },
            "spouse_employment_status": {
                "type": "string",
                "enum": [
                    "full_time",
                    "part_time",
                    "self_employed",
                    "retired"
                ],
                "description": "Spouse employment status."
            },
            "spouse_isa_balance": {
                "type": "number",
                "description": "Spouse current ISA balance in pounds."
            },
            "spouse_psa_band": {
                "type": "string",
                "enum": [
                    "basic",
                    "higher",
                    "additional"
                ],
                "description": "Spouse Personal Savings Allowance band."
            },
            "spouse_unrealised_gains": {
                "type": "number",
                "description": "Spouse unrealised capital gains in pounds."
            },
            "spouse_annual_dividends": {
                "type": "number",
                "description": "Spouse annual dividend income in pounds."
            },
            "spouse_pension_input_annual": {
                "type": "number",
                "description": "Spouse gross annual pension contribution in pounds."
            }
        },
        "required": [],
        "additionalProperties": false
    }
}
```
