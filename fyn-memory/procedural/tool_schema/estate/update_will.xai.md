---
procedure_id: 'estate.tool.update_will'
kind: tool_schema
module: estate
provider: xai
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "update_will",
    "description": "Update an existing will record. Use when the user amends their will details.",
    "parameters": {
        "type": "object",
        "properties": {
            "executor_name": {
                "type": [
                    "string",
                    "null"
                ]
            },
            "residuary_beneficiary": {
                "type": [
                    "string",
                    "null"
                ]
            },
            "guardian_for_minors": {
                "type": [
                    "string",
                    "null"
                ]
            },
            "specific_gifts": {
                "type": [
                    "string",
                    "null"
                ]
            },
            "spouse_primary_beneficiary": {
                "type": [
                    "boolean",
                    "null"
                ]
            }
        },
        "required": [
            "executor_name",
            "residuary_beneficiary",
            "guardian_for_minors",
            "specific_gifts",
            "spouse_primary_beneficiary"
        ],
        "additionalProperties": false
    },
    "strict": true
}
```
