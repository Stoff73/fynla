---
procedure_id: 'onboarding.tool.capture_dependants'
kind: tool_schema
module: onboarding
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "capture_dependants",
    "description": "Capture a list of the user's dependants (children or other dependants) from a free-text reply during onboarding. Call this once per turn with an array of all dependants mentioned. Do not call any other tool.",
    "parameters": {
        "type": "object",
        "properties": {
            "dependants": {
                "type": "array",
                "description": "One entry per dependant mentioned. Names may be omitted if the user did not provide them (use null).",
                "items": {
                    "type": "object",
                    "properties": {
                        "first_name": {
                            "type": "string",
                            "description": "The dependant's first name if mentioned, otherwise null."
                        },
                        "date_of_birth": {
                            "type": "string",
                            "format": "date",
                            "description": "The exact date of birth explicitly supplied by the user, in YYYY-MM-DD format. Never infer this from an age."
                        },
                        "relationship": {
                            "type": "string",
                            "enum": [
                                "child",
                                "parent",
                                "other_dependent"
                            ],
                            "description": "Child (son, daughter, step-child, etc.), parent (mother, father, in-law), or other_dependent (sibling, nephew, elderly relative, friend)."
                        }
                    },
                    "required": [
                        "date_of_birth",
                        "relationship"
                    ],
                    "additionalProperties": false
                }
            }
        },
        "required": [
            "dependants"
        ],
        "additionalProperties": false
    }
}
```
