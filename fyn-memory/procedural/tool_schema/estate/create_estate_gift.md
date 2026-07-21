---
procedure_id: 'estate.tool.create_estate_gift'
kind: tool_schema
module: estate
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "create_estate_gift",
    "description": "Record a gift for Inheritance Tax planning. Use this when the user mentions gifts they have made or plan to make, as these affect their Inheritance Tax position under the 7-year rule. You MAY call this tool multiple times in the same turn when the user mentions multiple gifts. If the user has only asked to add details without giving any specifics yet, do NOT call this tool — ask for the details first, and never invent names or values.",
    "parameters": {
        "type": "object",
        "properties": {
            "gift_date": {
                "type": "string",
                "format": "date",
                "description": "Date the gift was or will be made, in YYYY-MM-DD format"
            },
            "recipient": {
                "type": "string",
                "description": "Name of the recipient"
            },
            "gift_type": {
                "type": "string",
                "enum": [
                    "pet",
                    "clt",
                    "exempt",
                    "small_gift",
                    "annual_exemption"
                ],
                "description": "Inheritance Tax classification. \"pet\" for Potentially Exempt Transfer (most common — gifts to individuals), \"clt\" for Chargeable Lifetime Transfer (gifts to trusts), \"exempt\" for exempt gifts (e.g., to spouse or charity), \"small_gift\" for small gifts up to £250 per recipient, \"annual_exemption\" for annual exemption gifts up to £3,000 per year. Default to \"pet\" for most gifts between individuals."
            },
            "gift_value": {
                "type": "number",
                "description": "Value of the gift in pounds"
            },
            "notes": {
                "type": "string",
                "description": "Additional notes about the gift"
            }
        },
        "required": [
            "gift_date",
            "recipient",
            "gift_type",
            "gift_value"
        ],
        "additionalProperties": false
    }
}
```
