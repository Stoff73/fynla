---
procedure_id: 'handoff.tool.delegate_to_capture'
kind: tool_schema
module: handoff
version: 2
active: true
effective_from: 2026-08-19
---

```json
{
    "name": "delegate_to_capture",
    "description": "Internal. Emit this whenever the user states a fact about their own finances or household, or asks you to record, save, add, update or correct one, and whenever you cannot answer without data they have not supplied. Never shown to the user. The orchestrator will hand off to data-capture Fyn and re-invoke you once capture is complete. This is the ONLY way a write reaches the database from advice mode, so emit it rather than refusing or replying that you cannot record something.",
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
                "description": "Record types to capture. Any record type is accepted, not only assets: alongside dc_pension, savings_account, investment_account, property, mortgage, protection_policy, goal and chattel, use personal_details, spouse, dependant, work_details, expenditure, charitable_giving, state_pension or retirement_goals for household and profile facts. If none of these names fits, emit your own best description of the record type rather than omitting the call."
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
