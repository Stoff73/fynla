---
procedure_id: 'estate.tool.create_will'
kind: tool_schema
module: estate
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "create_will",
    "description": "Record the user's will details. Use when the user tells you they have a will and shares executor, beneficiaries, guardians, or specific gifts information. For existing wills only — the Will Builder UI remains the tool for drafting a new will from scratch. If the user has only asked to add details without giving any specifics yet, do NOT call this tool — ask for the details first, and never invent names or values.",
    "parameters": {
        "type": "object",
        "properties": {
            "executor_name": {
                "type": "string",
                "description": "Full name of the primary executor."
            },
            "residuary_beneficiary": {
                "type": "string",
                "description": "Named primary residuary beneficiary — who receives the bulk of the estate after specific gifts and debts."
            },
            "guardian_for_minors": {
                "type": "string",
                "description": "Named guardian for any minor children, if the user has minor dependants."
            },
            "specific_gifts": {
                "type": "string",
                "description": "Free-text description of specific gifts (item, recipient). Leave blank if the user mentions no specific gifts."
            },
            "spouse_primary_beneficiary": {
                "type": "boolean",
                "description": "Whether the user's spouse is the primary beneficiary. Defaults true if the user is married and did not specify otherwise."
            }
        },
        "required": [
            "executor_name"
        ],
        "additionalProperties": false
    }
}
```
