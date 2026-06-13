---
procedure_id: 'estate.tool.update_will'
kind: tool_schema
module: estate
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "update_will",
    "description": "Update an existing will record. Use when the user amends their will details (new executor, new beneficiary, updated specific gifts).",
    "parameters": {
        "type": "object",
        "properties": {
            "executor_name": {
                "type": "string",
                "description": "New executor name."
            },
            "residuary_beneficiary": {
                "type": "string",
                "description": "New residuary beneficiary."
            },
            "guardian_for_minors": {
                "type": "string",
                "description": "New guardian for minors."
            },
            "specific_gifts": {
                "type": "string",
                "description": "New specific gifts description."
            },
            "spouse_primary_beneficiary": {
                "type": "boolean",
                "description": "Spouse as primary beneficiary flag."
            }
        },
        "required": [],
        "additionalProperties": false
    }
}
```
