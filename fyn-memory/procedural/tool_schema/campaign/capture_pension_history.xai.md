---
procedure_id: 'campaign.tool.capture_pension_history'
kind: tool_schema
module: campaign
provider: xai
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "capture_pension_history",
    "description": "Capture the user's gross pension contributions for each of the last 3 tax years. Used by the Pension Annual Allowance Carry-Forward strategy to compute unused AA the user could still pension-up. Pass each year individually using the canonical \"YYYY/YY\" tax-year format (e.g. \"2024/25\").",
    "parameters": {
        "type": "object",
        "properties": {
            "history": {
                "type": "array",
                "description": "List of tax_year + amount pairs. The strategy reads up to the most recent 3 entries.",
                "items": {
                    "type": "object",
                    "properties": {
                        "tax_year": {
                            "type": "string",
                            "description": "UK tax year in \"YYYY/YY\" format (e.g. \"2024/25\")."
                        },
                        "pension_input_amount": {
                            "type": "number",
                            "description": "Gross pension input for that year in pounds."
                        }
                    },
                    "required": [
                        "tax_year",
                        "pension_input_amount"
                    ],
                    "additionalProperties": false
                }
            }
        },
        "required": [
            "history"
        ],
        "additionalProperties": false
    },
    "strict": true
}
```
