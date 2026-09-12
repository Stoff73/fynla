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
                "description": "Spouse gross annual pension contribution in pounds. A monthly figure (\"she contributes 500 per month\") must be converted to annual (x12 = 6000). Always set it when the user states what the spouse pays in."
            },
            "spouse_existing_pension_balance": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Current total value of the pension pot(s) the spouse already holds, in pounds — set when the user states a pot value (e.g. \"an Aviva pension with 75,680 in it\"). Distinct from spouse_pension_input_annual (what they pay in each year)."
            },
            "spouse_isa_provider": {
                "type": [
                "string",
                "null"
            ],
                "description": "Name of the provider holding the spouse's ISA (e.g. \"Halifax\" from \"an ISA with Halifax\"), when stated."
            },
            "spouse_pension_provider": {
                "type": [
                "string",
                "null"
            ],
                "description": "Name of the spouse's pension provider or scheme (e.g. \"Aviva\" from \"an Aviva pension\"), when stated."
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
