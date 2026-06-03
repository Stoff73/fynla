---
procedure_id: 'estate.tool.create_family_member'
kind: tool_schema
module: estate
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "create_family_member",
    "description": "Add a family member (spouse, child, dependent). Use when the user mentions family members who affect their financial planning. You MAY call this tool multiple times in the same turn when the user mentions multiple family members — for two children, call create_family_member TWICE in your first response.",
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
