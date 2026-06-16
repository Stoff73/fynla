---
procedure_id: 'handoff.tool.capture_complete'
kind: tool_schema
module: handoff
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "capture_complete",
    "description": "Internal. Emit this when you (data-capture Fyn) have finished capturing the records the user described. The orchestrator will return control to advice Fyn.",
    "parameters": {
        "type": "object",
        "properties": {
            "summary": {
                "type": "string",
                "description": "Short user-facing recap (e.g. \"Added Scottish Widows SIPP £50k\")."
            },
            "records_created": {
                "type": "array",
                "items": {
                    "type": "object",
                    "properties": {
                        "type": {
                            "type": "string"
                        },
                        "id": {
                            "type": [
                                "integer",
                                "string"
                            ]
                        }
                    },
                    "required": [
                        "type",
                        "id"
                    ],
                    "additionalProperties": false
                },
                "description": "Structured list of records created or updated this sub-conversation."
            }
        },
        "required": [
            "summary",
            "records_created"
        ],
        "additionalProperties": false
    }
}
```
