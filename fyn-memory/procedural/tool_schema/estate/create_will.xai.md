---
procedure_id: 'estate.tool.create_will'
kind: tool_schema
module: estate
provider: xai
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "create_will",
    "description": "Record the user's will details. Use for existing wills only — the Will Builder UI remains the tool for drafting a new will from scratch. If the user has only asked to add details without giving any specifics yet, do NOT call this tool — ask for the details first, and never invent names or values.",
    "parameters": {
        "type": "object",
        "properties": {
            "executor_name": {
                "type": "string",
                "description": "Full name of the primary executor."
            },
            "residuary_beneficiary": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Named primary residuary beneficiary."
            },
            "guardian_for_minors": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Named guardian for minor children, if any."
            },
            "specific_gifts": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Free-text description of specific gifts (item, recipient)."
            },
            "spouse_primary_beneficiary": {
                "type": [
                    "boolean",
                    "null"
                ],
                "description": "Whether the spouse is the primary beneficiary."
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
