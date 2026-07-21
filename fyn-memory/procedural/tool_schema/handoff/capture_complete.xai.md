---
procedure_id: 'handoff.tool.capture_complete'
kind: tool_schema
module: handoff
provider: xai
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "capture_complete",
    "description": "Internal. Emit when you (data-capture Fyn) have finished capturing. Orchestrator returns control to advice Fyn.",
    "parameters": {
        "type": "object",
        "properties": {
            "summary": {
                "type": "string",
                "description": "Short user-facing recap."
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
                "description": "Records created or updated."
            }
        },
        "required": [
            "summary",
            "records_created"
        ],
        "additionalProperties": false
    },
    "strict": true
}
```
