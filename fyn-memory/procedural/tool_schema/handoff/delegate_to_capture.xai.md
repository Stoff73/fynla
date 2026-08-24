---
procedure_id: 'handoff.tool.delegate_to_capture'
kind: tool_schema
module: handoff
provider: xai
version: 2
active: true
effective_from: 2026-08-19
---

```json
{
    "name": "delegate_to_capture",
    "description": "Internal. Emit whenever the user states a fact about their own finances or household, or asks you to record, save, add, update or correct one, and whenever you cannot answer without data they have not supplied. Never shown to the user. This is the ONLY way a write reaches the database from advice mode, so emit it rather than refusing or saying you cannot record something.",
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
                "description": "Record types to capture. Any type is accepted, not only assets: dc_pension, savings_account, investment_account, property, mortgage, protection_policy, goal, chattel, and also personal_details, spouse, dependant, work_details, expenditure, charitable_giving, state_pension, retirement_goals. If none fits, emit your own best description rather than omitting the call."
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
