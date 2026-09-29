---
procedure_id: 'onboarding.tool.capture_employer_benefits'
kind: tool_schema
module: onboarding
version: 1
active: true
effective_from: 2026-09-29
---

```json
{
    "name": "capture_employer_benefits",
    "description": "Record the protection cover the user's employer provides: death in service, group income protection, group critical illness cover and private medical insurance, or that it provides none of these. Every call replaces the user's employer benefits, so include every benefit the user has mentioned. Call this once per turn. Do not call any other tool.",
    "parameters": {
        "type": "object",
        "properties": {
            "provides": {
                "type": "string",
                "enum": ["yes", "no"],
                "description": "\"no\" when the user says their job gives them none of these benefits; \"yes\" otherwise."
            },
            "employer_name": {
                "type": "string",
                "description": "The employer's name, when the user gives it. Omit otherwise."
            },
            "death_in_service_multiple": {
                "type": "number",
                "description": "Death in service as a multiple of salary, for example 4 for four times salary. Omit when not mentioned."
            },
            "group_ip_benefit_percent": {
                "type": "number",
                "description": "Group income protection as a percentage of salary, for example 50. Omit when not mentioned."
            },
            "group_ip_benefit_months": {
                "type": "integer",
                "description": "How many months the group income protection pays for. Omit when not mentioned."
            },
            "group_ip_definition": {
                "type": "string",
                "enum": ["own", "any"],
                "description": "\"own\" when it pays if the user cannot do their own job, \"any\" when only if they cannot do any job. Omit when not mentioned."
            },
            "group_ci_amount": {
                "type": "number",
                "description": "The lump sum of group critical illness cover in GBP. Strip currency symbols and commas. Omit when not mentioned."
            },
            "has_employer_pmi": {
                "type": "boolean",
                "description": "True when the employer provides private medical insurance, false when the user says it does not. Omit when not mentioned."
            }
        },
        "required": [
            "provides"
        ],
        "additionalProperties": false
    }
}
```
