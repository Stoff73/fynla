---
procedure_id: 'estate.tool.create_chattel'
kind: tool_schema
module: estate
provider: xai
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "create_chattel",
    "description": "Record a personal valuable item. Use this for jewellery, art, fine art, wine, fine wine, antiques, collectibles, vehicles, watches, handbags, and other physical valuables. Do NOT use this for gold, silver, cryptocurrency, or financial investments — use create_investment_account with type \"other\" instead. You MAY call this tool multiple times in the same turn when the user mentions multiple items. If the user has only asked to add details without giving any specifics yet, do NOT call this tool — ask for the details first, and never invent names or values.",
    "parameters": {
        "type": "object",
        "properties": {
            "description": {
                "type": "string",
                "description": "Description of the item"
            },
            "category": {
                "type": "string",
                "enum": [
                    "jewellery",
                    "art",
                    "antiques",
                    "collectibles",
                    "vehicles",
                    "other"
                ],
                "description": "Category of item"
            },
            "estimated_value": {
                "type": "number",
                "description": "Estimated current value (£)"
            },
            "purchase_value": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Original purchase value (£)"
            },
            "is_insured": {
                "type": [
                    "boolean",
                    "null"
                ],
                "description": "Whether the item is insured"
            }
        },
        "required": [
            "description",
            "category",
            "estimated_value",
            "purchase_value",
            "is_insured"
        ],
        "additionalProperties": false
    },
    "strict": true
}
```
