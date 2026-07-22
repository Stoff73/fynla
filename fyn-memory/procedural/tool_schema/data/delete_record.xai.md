---
procedure_id: 'data.tool.delete_record'
kind: tool_schema
module: data
provider: xai
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "delete_record",
    "description": "Delete an existing record. Two-phase confirmation: the first call returns a confirmation_token and preview_message — DO NOT delete on that turn; show the preview to the user and ask for confirmation. Only on a second call, echoing the exact same confirmation_token, does the deletion proceed. Tokens are bound to (user, entity_type, entity_id, today's date) and cannot be replayed across days.",
    "parameters": {
        "type": "object",
        "properties": {
            "entity_type": {
                "type": "string",
                "enum": [
                    "goal",
                    "life_event",
                    "savings_account",
                    "investment_account",
                    "dc_pension",
                    "db_pension",
                    "property",
                    "mortgage",
                    "life_insurance",
                    "critical_illness",
                    "income_protection",
                    "estate_asset",
                    "estate_liability",
                    "estate_gift",
                    "family_member",
                    "trust",
                    "business_interest",
                    "chattel"
                ],
                "description": "The type of record to delete"
            },
            "entity_id": {
                "type": "integer",
                "description": "The ID of the record to delete"
            },
            "confirmation_token": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Omit (or null) on the first call — you will receive a token. On the second call, pass the exact 64-character token from the first response. Without a matching token the call returns requires_confirmation again and does NOT delete."
            }
        },
        "required": [
            "entity_type",
            "entity_id",
            "confirmation_token"
        ],
        "additionalProperties": false
    },
    "strict": true
}
```
