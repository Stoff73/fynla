---
procedure_id: 'estate.tool.create_family_member'
kind: tool_schema
module: estate
version: 2
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "create_family_member",
    "description": "Add a family member (spouse, child, dependent). Use when the user mentions family members who affect their financial planning. You MAY call this tool multiple times in the same turn when the user mentions multiple family members — for two children, call create_family_member TWICE in your first response. If the user has only asked to add details without giving any specifics yet, do NOT call this tool — ask for the details first, and never invent names or values. SPOUSE: adding a spouse creates or links a real Fynla account for them, which is what connects the household's finances, so \"email\" is REQUIRED when relationship is \"spouse\". If the user asks to add their spouse and has not given an email address, ask for it before calling this tool — never invent one. Adding the same spouse again updates the existing record rather than creating a second one.",
    "parameters": {
        "type": "object",
        "properties": {
            "first_name": {
                "type": "string",
                "description": "First name"
            },
            "surname": {
                "type": "string",
                "description": "Surname"
            },
            "relationship": {
                "type": "string",
                "enum": [
                    "spouse",
                    "child",
                    "parent",
                    "sibling",
                    "other"
                ],
                "description": "Relationship to the user"
            },
            "email": {
                "type": "string",
                "description": "The spouse's own email address. Required when relationship is \"spouse\" — their account is created or linked with it, and without it the household is not linked. Omit for every other relationship."
            },
            "date_of_birth": {
                "type": "string",
                "description": "Date of birth (YYYY-MM-DD)"
            },
            "gender": {
                "type": "string",
                "enum": [
                    "male",
                    "female",
                    "other"
                ]
            },
            "is_dependent": {
                "type": "boolean",
                "description": "Whether this person is financially dependent on the user"
            }
        },
        "required": [
            "first_name",
            "relationship"
        ],
        "additionalProperties": false
    }
}
```
