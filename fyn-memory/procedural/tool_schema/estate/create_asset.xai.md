---
procedure_id: 'estate.tool.create_asset'
kind: tool_schema
module: estate
provider: xai
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "create_asset",
    "description": "Create an asset not covered by other tools — collectibles, artwork, or other valuable items. If the user has only asked to add details without giving any specifics yet, do NOT call this tool — ask for the details first, and never invent names or values.",
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
                "description": "Type of estate asset."
            },
            "current_value": {
                "type": "number",
                "description": "Current estimated value (£)"
            },
            "is_iht_exempt": {
                "type": [
                    "boolean",
                    "null"
                ],
                "description": "Whether exempt from IHT. Default false."
            },
            "exemption_reason": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Reason for IHT exemption, if applicable."
            }
        },
        "required": [
            "asset_name",
            "asset_type",
            "current_value",
            "is_iht_exempt",
            "exemption_reason"
        ],
        "additionalProperties": false
    },
    "strict": true
}
```
