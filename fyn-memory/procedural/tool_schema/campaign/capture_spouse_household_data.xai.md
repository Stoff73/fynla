---
procedure_id: 'campaign.tool.capture_spouse_household_data'
kind: tool_schema
module: campaign
provider: xai
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
                "type": [
                    "number",
                    "null"
                ],
                "description": "Spouse gross annual income in pounds."
            },
            "spouse_employment_status": {
                "anyOf": [
                    {
                        "type": "string",
                        "enum": [
                            "full_time",
                            "part_time",
                            "self_employed",
                            "retired"
                        ]
                    },
                    {
                        "type": "null"
                    }
                ],
                "description": "Spouse employment status."
            },
            "spouse_isa_balance": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Spouse current ISA balance in pounds."
            },
            "spouse_psa_band": {
                "anyOf": [
                    {
                        "type": "string",
                        "enum": [
                            "basic",
                            "higher",
                            "additional"
                        ]
                    },
                    {
                        "type": "null"
                    }
                ],
                "description": "Spouse Personal Savings Allowance band."
            },
            "spouse_unrealised_gains": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Spouse unrealised capital gains."
            },
            "spouse_annual_dividends": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Spouse annual dividend income."
            },
            "spouse_pension_input_annual": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Spouse gross annual pension contribution."
            }
        },
        "required": [
            "spouse_annual_income",
            "spouse_employment_status",
            "spouse_isa_balance",
            "spouse_psa_band",
            "spouse_unrealised_gains",
            "spouse_annual_dividends",
            "spouse_pension_input_annual"
        ],
        "additionalProperties": false
    },
    "strict": true
}
```
