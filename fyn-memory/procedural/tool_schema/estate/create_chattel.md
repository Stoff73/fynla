---
procedure_id: 'estate.tool.create_chattel'
kind: tool_schema
module: estate
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "create_chattel",
    "description": "Record a personal valuable item (jewellery, art, collectibles, vehicles). Use when the user mentions valuable personal possessions. You MAY call this tool multiple times in the same turn when the user mentions multiple items.",
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
                "type": "number",
                "description": "Original purchase value (£)"
            },
            "is_insured": {
                "type": "boolean",
                "description": "Whether the item is insured"
            }
        },
        "required": [
            "description",
            "estimated_value"
        ],
        "additionalProperties": false
    }
}
```
