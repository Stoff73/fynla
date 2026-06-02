---
procedure_id: 'handoff.tool.delegate_to_capture'
kind: tool_schema
module: handoff
provider: xai
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "delegate_to_capture",
    "description": "Internal. Emit when you (advice Fyn) cannot answer without data the user has not supplied, or when the user asks for an inline capture. Never shown to the user.",
    "parameters": {
        "type": "object",
        "properties": {
            "reason": {
                "type": "string",
                "description": "Why capture is needed."
            },
            "entity_types": {
                "type": "array",
                "items": {
                    "type": "string"
                },
                "description": "Record types to capture."
            },
            "fields_needed": {
                "type": [
                    "array",
                    "null"
                ],
                "items": {
                    "type": "string"
                },
                "description": "Optional specific fields required."
            }
        },
        "required": [
            "reason",
            "entity_types",
            "fields_needed"
        ],
        "additionalProperties": false
    },
    "strict": true
}
```
