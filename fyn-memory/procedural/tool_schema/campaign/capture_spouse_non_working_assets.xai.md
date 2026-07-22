---
procedure_id: 'campaign.tool.capture_spouse_non_working_assets'
kind: tool_schema
module: campaign
provider: xai
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "capture_spouse_non_working_assets",
    "description": "Capture standalone assets owned by a non-working spouse (single_earner_couple path). Used to compute available capacity for asset-shifting strategies and to size a non-earner spouse pension contribution.",
    "parameters": {
        "type": "object",
        "properties": {
            "spouse_existing_isa_balance": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Spouse's existing standalone ISA balance."
            },
            "spouse_existing_savings_balance": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Spouse's existing standalone bank/savings balance."
            },
            "spouse_existing_investment_balance": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Spouse's existing standalone investment account balance."
            },
            "spouse_existing_dividend_holdings_value": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Value of spouse's dividend-paying holdings."
            },
            "spouse_existing_pension_balance": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Spouse's existing personal-pension pot value."
            }
        },
        "required": [
            "spouse_existing_isa_balance",
            "spouse_existing_savings_balance",
            "spouse_existing_investment_balance",
            "spouse_existing_dividend_holdings_value",
            "spouse_existing_pension_balance"
        ],
        "additionalProperties": false
    },
    "strict": true
}
```
