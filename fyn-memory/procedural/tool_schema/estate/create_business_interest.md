---
procedure_id: 'estate.tool.create_business_interest'
kind: tool_schema
module: estate
version: 1
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
