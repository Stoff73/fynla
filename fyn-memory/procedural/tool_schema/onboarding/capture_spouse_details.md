---
procedure_id: 'onboarding.tool.capture_spouse_details'
kind: tool_schema
module: onboarding
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "capture_spouse_details",
    "description": "Capture the user's spouse or civil partner details from a free-text reply during onboarding. This creates a linked spouse user account. Call this once per turn. Do not call any other tool.",
    "parameters": {
        "type": "object",
        "properties": {
            "first_name": {
                "type": "string",
                "description": "The spouse or partner's first name. Extract from phrases like \"my partner Jamie\" or \"called Sarah\"."
            },
            "last_name": {
                "type": "string",
                "description": "The spouse or partner's last name, if provided. Optional."
            },
            "date_of_birth": {
                "type": "string",
                "description": "Spouse/partner date of birth in YYYY-MM-DD format. Parse natural language dates into ISO format."
            },
            "email": {
                "type": "string",
                "description": "The spouse or partner's email address. Required — this is used to create their linked account."
            },
            "annual_income": {
                "type": "number",
                "description": "The spouse or partner's rough annual income in GBP, if mentioned. Optional. Strip currency symbols and commas; \"75k\" = 75000."
            }
        },
        "required": [
            "first_name",
            "date_of_birth",
            "email"
        ],
        "additionalProperties": false
    }
}
```
