---
procedure_id: 'estate.tool.update_power_of_attorney'
kind: tool_schema
module: estate
provider: xai
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "update_power_of_attorney",
    "description": "Update an existing LPA record — status change, OPG reference, attorney amendments.",
    "parameters": {
        "type": "object",
        "properties": {
            "lpa_id": {
                "type": "integer",
                "description": "ID of the LPA."
            },
            "status": {
                "type": [
                    "string",
                    "null"
                ],
                "enum": [
                    "draft",
                    "registered",
                    null
                ]
            },
            "opg_reference": {
                "type": [
                    "string",
                    "null"
                ]
            },
            "primary_attorney_name": {
                "type": [
                    "string",
                    "null"
                ]
            },
            "replacement_attorney_name": {
                "type": [
                    "string",
                    "null"
                ]
            }
        },
        "required": [
            "lpa_id",
            "status",
            "opg_reference",
            "primary_attorney_name",
            "replacement_attorney_name"
        ],
        "additionalProperties": false
    },
    "strict": true
}
```
