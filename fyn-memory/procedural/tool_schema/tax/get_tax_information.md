---
procedure_id: 'tax.tool.get_tax_information'
kind: tool_schema
module: tax
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "get_tax_information",
    "description": "Get current UK tax year information for a specific topic. ALWAYS use this tool when the user asks about tax thresholds, allowances, rates, or any financial product tax treatment. Never state tax values from memory — always retrieve them. Use income_definitions to get the user's detailed income breakdown including adjusted net income, threshold income, and tapered pension allowances.",
    "parameters": {
        "type": "object",
        "properties": {
            "topic": {
                "type": "string",
                "enum": [
                    "income_tax",
                    "national_insurance",
                    "capital_gains",
                    "dividend_tax",
                    "inheritance_tax",
                    "gifting_exemptions",
                    "stamp_duty",
                    "isa_allowances",
                    "pension_allowances",
                    "state_pension",
                    "benefits",
                    "savings_config",
                    "assumptions",
                    "investment_bonds",
                    "venture_capital",
                    "protection_config",
                    "retirement_config",
                    "domicile",
                    "income_definitions"
                ],
                "description": "The tax or financial configuration topic to retrieve. Use income_definitions for the user's adjusted net income, threshold income, and tapered allowances."
            }
        },
        "required": [
            "topic"
        ],
        "additionalProperties": false
    }
}
```
