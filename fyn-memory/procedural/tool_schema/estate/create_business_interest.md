---
procedure_id: 'estate.tool.create_business_interest'
kind: tool_schema
module: estate
version: 2
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "create_business_interest",
    "description": "Record a business interest or ownership. Use when the user mentions business ownership, partnerships, or self-employment assets. You MAY call this tool multiple times in the same turn when the user mentions multiple businesses. If the user has only asked to add details without giving any specifics yet, do NOT call this tool — ask for the details first, and never invent names or values.",
    "parameters": {
        "type": "object",
        "properties": {
            "business_name": {
                "type": "string",
                "description": "Name of the business"
            },
            "business_type": {
                "type": "string",
                "enum": [
                    "sole_trader",
                    "partnership",
                    "limited_company",
                    "llp"
                ],
                "description": "Type of business entity"
            },
            "ownership_percentage": {
                "type": "number",
                "description": "Percentage owned (0-100)"
            },
            "estimated_value": {
                "type": "number",
                "description": "Estimated value of the interest (£)"
            },
            "annual_profit": {
                "type": "number",
                "description": "Annual profit/drawings (£)"
            },
            "company_number": {
                "type": "string",
                "description": "Companies House registration number for a UK-registered company or LLP — eight characters, either eight digits (e.g. \"12248522\") or two letters and six digits (e.g. \"SC123456\"). Supply it only if the user states it; never guess or derive it from the business name. Omit for a sole trader, a partnership, or a business registered outside the United Kingdom."
            }
        },
        "required": [
            "business_name",
            "business_type"
        ],
        "additionalProperties": false
    }
}
```
