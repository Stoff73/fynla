---
procedure_id: 'data.tool.update_record'
kind: tool_schema
module: data
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "update_record",
    "description": "Update an existing record. Use when the user wants to change details of an existing goal, account, property, pension, policy, or other financial record. Ask the user to confirm the changes before calling this tool. You MAY call this tool multiple times in the same turn when the user retracts or amends multiple records in one message. The schema restricts which fields are editable per entity_type — invented field names will be rejected.",
    "parameters": {
        "$allowlist": "update_record"
    }
}
```
