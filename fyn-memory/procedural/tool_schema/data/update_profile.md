---
procedure_id: 'data.tool.update_profile'
kind: tool_schema
module: data
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "update_profile",
    "description": "Update the user's profile information (personal details, income, expenditure, or domicile). Use when the user provides personal information like their age, income, spending, marital status, or address. Ask clarifying questions if needed to gather required fields.",
    "parameters": {
        "type": "object",
        "properties": {
            "section": {
                "type": "string",
                "enum": [
                    "personal",
                    "income_occupation",
                    "expenditure",
                    "domicile"
                ],
                "description": "Which profile section to update. personal: name, DOB, gender, marital status, address, phone. income_occupation: employment status, income, employer. expenditure: monthly spending. domicile: country of birth, UK arrival date."
            },
            "fields": {
                "type": "object",
                "description": "Key-value pairs of fields to update. For personal: first_name, surname, date_of_birth (YYYY-MM-DD), gender (male/female/other), marital_status (single/married/divorced/widowed), phone, address_line_1, city, postcode. For income_occupation: employment_status (employed/full_time/part_time/self_employed/retired/unemployed/other), occupation, employer, annual_employment_income, annual_self_employment_income. For expenditure: monthly_expenditure, annual_expenditure. For domicile: country_of_birth, uk_arrival_date.",
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
