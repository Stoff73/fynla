---
procedure_id: 'estate.tool.update_power_of_attorney'
kind: tool_schema
module: estate
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "update_power_of_attorney",
    "description": "Update an existing LPA record (e.g. status change from draft to registered, OPG reference added, replacement attorney added).",
    "parameters": {
        "type": "object",
        "properties": {
            "lpa_id": {
                "type": "integer",
                "description": "ID of the LPA to update."
            },
            "status": {
                "type": "string",
                "enum": [
                    "draft",
                    "registered"
                ]
            },
            "opg_reference": {
                "type": "string"
            },
            "primary_attorney_name": {
                "type": "string"
            },
            "replacement_attorney_name": {
                "type": "string"
            }
        },
        "required": [
            "lpa_id"
        ],
        "additionalProperties": false
    }
}
```
