---
procedure_id: 'estate.tool.create_asset'
kind: tool_schema
module: estate
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "create_asset",
    "description": "Create an asset. Use this for assets not covered by other tools — such as collectibles, artwork, or other valuable items the user wants to track. You MAY call this tool multiple times in the same turn when the user mentions multiple assets.",
    "parameters": {
        "type": "object",
        "properties": {
            "asset_name": {
                "type": "string",
                "description": "Name or description of the asset"
            },
            "asset_type": {
                "type": "string",
                "enum": [
                    "property",
                    "pension",
                    "investment",
                    "business",
                    "other"
                ],
                "description": "Type of estate asset. Use \"other\" for cash, collectibles, and similar."
            },
            "current_value": {
                "type": "number",
                "description": "Current estimated value in pounds"
            },
            "is_iht_exempt": {
                "type": "boolean",
                "description": "Whether the asset is exempt from Inheritance Tax (e.g., business property relief). Default false."
            },
            "exemption_reason": {
                "type": "string",
                "description": "Reason for Inheritance Tax exemption, if applicable"
            }
        },
        "required": [
            "asset_name",
            "asset_type",
            "current_value"
        ],
        "additionalProperties": false
    }
}
```
