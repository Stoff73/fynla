---
procedure_id: 'onboarding.tool.capture_personal_details'
kind: tool_schema
module: onboarding
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "capture_personal_details",
    "description": "Capture the user's date of birth and/or marital status from a free-text reply during onboarding. Call this once per turn. Do not call any other tool. CRITICAL: only include a field in the arguments when the user has EXPLICITLY stated it in their reply. Do not guess, infer, or default any field. If the user only gave their date of birth, include only date_of_birth. If they only gave their marital status, include only marital_status. Omit a field entirely rather than inventing a value — the onboarding flow will re-ask for anything missing.",
    "parameters": {
        "type": "object",
        "properties": {
            "date_of_birth": {
                "type": "string",
                "description": "Date of birth in YYYY-MM-DD format, parsed from natural language like \"12 January 1985\". Short formats are fine: read numeric dates as UK day-first (\"19/02/1982\" is 19 February), and expand a two-digit year to the century that gives a plausible adult age (\"19/02/82\" or \"19 Feb 82\" is 1982-02-19, not 2082). Only include this field if the user explicitly stated a date of birth."
            },
            "marital_status": {
                "type": "string",
                "enum": [
                    "single",
                    "married",
                    "civil_partnership",
                    "divorced",
                    "widowed"
                ],
                "description": "The user's marital status. Only include this field if the user explicitly stated their marital status. Map phrases: \"married\" → married, \"civil partnership\" or \"civil partner\" → civil_partnership, \"single\" or \"unmarried\" → single, \"divorced\" or \"separated\" → divorced, \"widowed\" or \"widow\" → widowed."
            }
        },
        "required": [],
        "additionalProperties": false
    }
}
```
