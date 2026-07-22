---
procedure_id: 'data.tool.update_profile'
kind: tool_schema
module: data
provider: xai
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "update_profile",
    "description": "Update the user's profile information (personal details, income, or domicile). NEVER use this for any expenditure or spending data — use set_expenditure instead. Expenditure fields (food_groceries, transport_fuel, rent, utilities, childcare, entertainment_dining, etc.) are ALL handled exclusively by set_expenditure.",
    "parameters": {
        "type": "object",
        "properties": {
            "section": {
                "type": "string",
                "enum": [
                    "personal",
                    "income_occupation",
                    "domicile"
                ],
                "description": "Which profile section to update. Must be one of: personal, income_occupation, domicile. NEVER pass expenditure — use set_expenditure for all spending fields."
            },
            "fields": {
                "type": "object",
                "description": "Key-value pairs of fields to update. For personal: first_name, surname, date_of_birth, gender, marital_status, phone, address_line_1, city, postcode. For income_occupation: employment_status (MUST be one of: employed, full_time, part_time, self_employed, retired, unemployed, other), occupation, employer, annual_employment_income. For domicile: country_of_birth, uk_arrival_date. Do NOT include any spending or expenditure keys here.",
                "additionalProperties": true
            }
        },
        "required": [
            "section",
            "fields"
        ],
        "additionalProperties": false
    }
}
```
