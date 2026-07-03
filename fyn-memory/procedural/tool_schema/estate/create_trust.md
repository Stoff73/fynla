---
procedure_id: 'estate.tool.create_trust'
kind: tool_schema
module: estate
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "create_trust",
    "description": "Record a trust for estate planning. Use when the user mentions trusts they have set up or want to document. You MAY call this tool multiple times in the same turn when the user mentions multiple trusts. If the user has only asked to add details without giving any specifics yet, do NOT call this tool — ask for the details first, and never invent names or values.",
    "parameters": {
        "type": "object",
        "properties": {
            "trust_name": {
                "type": "string",
                "description": "Name of the trust"
            },
            "trust_type": {
                "type": "string",
                "enum": [
                    "discretionary",
                    "bare",
                    "interest_in_possession",
                    "life_insurance",
                    "loan",
                    "discounted_gift",
                    "accumulation_maintenance"
                ],
                "description": "Type of trust"
            },
            "current_value": {
                "type": "number",
                "description": "Current value of assets in trust (£)"
            },
            "date_established": {
                "type": "string",
                "description": "Date trust was established (YYYY-MM-DD)"
            },
            "settlor": {
                "type": "string",
                "description": "Who settled the trust"
            }
        },
        "required": [
            "trust_name",
            "trust_type"
        ],
        "additionalProperties": false
    }
}
```
