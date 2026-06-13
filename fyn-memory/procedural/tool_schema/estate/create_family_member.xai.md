---
procedure_id: 'estate.tool.create_family_member'
kind: tool_schema
module: estate
provider: xai
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "create_family_member",
    "description": "Add a family member. Use when the user mentions children, parents, step-children, dependents, or partners. For spouse: only use if the user explicitly asks to add their spouse — the system may already have a linked spouse account. Call this tool IMMEDIATELY. You MAY call this tool multiple times in the same turn when the user mentions multiple family members — for two children, call create_family_member TWICE in your first response (e.g. \"I have a daughter Emily age 8 and a son James age 5\" → two tool calls).",
    "parameters": {
        "type": "object",
        "properties": {
            "first_name": {
                "type": "string",
                "description": "First name of the family member"
            },
            "surname": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Surname/last name. If not mentioned, assume same as user."
            },
            "relationship": {
                "type": "string",
                "enum": [
                    "spouse",
                    "partner",
                    "child",
                    "step_child",
                    "parent",
                    "other_dependent"
                ],
                "description": "\"spouse\" for married/civil partner. \"partner\" for unmarried partner. \"child\" for biological child. \"step_child\" for step children. \"parent\" for mother/father. \"other_dependent\" for other financially dependent relatives (aunt, grandparent, sibling etc)."
            },
            "date_of_birth": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Date of birth (YYYY-MM-DD). If user gives age, calculate from today. Spouse must be 16+, child max 18 (or 22 if in education)."
            },
            "gender": {
                "anyOf": [
                    {
                        "type": "string",
                        "enum": [
                            "male",
                            "female",
                            "other",
                            "prefer_not_to_say"
                        ]
                    },
                    {
                        "type": "null"
                    }
                ],
                "description": "Gender. Infer from name/context if obvious (e.g. \"daughter\" = female, \"son\" = male)."
            },
            "is_dependent": {
                "type": [
                    "boolean",
                    "null"
                ],
                "description": "Whether financially dependent on the user. Default true for children, step_children, and other_dependents."
            },
            "education_status": {
                "anyOf": [
                    {
                        "type": "string",
                        "enum": [
                            "pre_school",
                            "primary",
                            "secondary",
                            "further_education",
                            "higher_education",
                            "graduated",
                            "not_applicable"
                        ]
                    },
                    {
                        "type": "null"
                    }
                ],
                "description": "Education status. Only for child/step_child. \"pre_school\" for nursery/pre-school. \"primary\" for primary school. \"secondary\" for secondary school. \"further_education\" for sixth form/college. \"higher_education\" for university. \"graduated\" if finished university. \"not_applicable\" if not in education."
            },
            "receives_child_benefit": {
                "type": [
                    "boolean",
                    "null"
                ],
                "description": "Whether child benefit is claimed for this child. Only for child/step_child."
            },
            "notes": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Any additional notes about this family member"
            }
        },
        "required": [
            "first_name",
            "surname",
            "relationship",
            "date_of_birth",
            "gender",
            "is_dependent",
            "education_status",
            "receives_child_benefit",
            "notes"
        ],
        "additionalProperties": false
    },
    "strict": true
}
```
