---
procedure_id: 'onboarding.tool.capture_work_details'
kind: tool_schema
module: onboarding
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "capture_work_details",
    "description": "Capture the user's employer, position, and annual income from a free-text reply during onboarding. Only used when the user is employed, self-employed, or part-time. Call this once per turn. Do not call any other tool.",
    "parameters": {
        "type": "object",
        "properties": {
            "employer": {
                "type": "string",
                "description": "The company or employer name. For self-employed users this may be their trading name or \"self-employed\"."
            },
            "occupation": {
                "type": "string",
                "description": "The user's job title or role. e.g. \"Software engineer\", \"Sole trader\", \"Consultant\"."
            },
            "annual_income": {
                "type": "number",
                "description": "Gross annual income in GBP. Strip currency symbols and commas; \"75k\" = 75000. For self-employed users this is self-employment income."
            }
        },
        "required": [
            "employer",
            "occupation",
            "annual_income"
        ],
        "additionalProperties": false
    }
}
```
