---
procedure_id: 'handoff.tool.delegate_to_capture'
kind: tool_schema
module: handoff
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "delegate_to_capture",
    "description": "Internal. Emit this when you (advice Fyn) cannot answer without data the user has not supplied, or when the user asks for an inline capture mid-conversation. Never shown to the user. The orchestrator will hand off to data-capture Fyn and re-invoke you once capture is complete.",
    "parameters": {
        "type": "object",
        "properties": {
            "reason": {
                "type": "string",
                "description": "Why capture is needed (e.g. \"retirement advice blocked on missing pension data\")."
            },
            "entity_types": {
                "type": "array",
                "items": {
                    "type": "string"
                },
                "description": "Record types to capture (dc_pension, savings_account, property, etc.). Drawn from data_capture persona allowed_tools."
            },
            "fields_needed": {
                "type": "array",
                "items": {
                    "type": "string"
                },
                "description": "Optional. Specific fields required to unblock the advice answer."
            }
        },
        "required": [
            "reason",
            "entity_types"
        ],
        "additionalProperties": false
    }
}
```
