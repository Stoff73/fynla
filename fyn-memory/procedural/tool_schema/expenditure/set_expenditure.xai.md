---
procedure_id: 'expenditure.tool.set_expenditure'
kind: tool_schema
module: expenditure
provider: xai
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "set_expenditure",
    "description": "Set the user's monthly expenditure by category. Call this IMMEDIATELY when the user mentions their spending, bills, or monthly outgoings. Fill in every category the user mentions and set null for anything not mentioned. The form will be opened, filled, and saved automatically. This tool captures all categories in a SINGLE call — do NOT call it multiple times per turn.",
    "parameters": {
        "type": "object",
        "properties": {
            "rent": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Monthly rent in pounds. Null if homeowner."
            },
            "utilities": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Monthly utilities (gas, electricity, water). Null if entered in property costs."
            },
            "food_groceries": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Monthly food and groceries in pounds."
            },
            "transport_fuel": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Monthly transport/fuel costs in pounds."
            },
            "healthcare_medical": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Monthly healthcare costs in pounds."
            },
            "insurance": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Monthly non-property insurance (car, medical, phone) in pounds."
            },
            "mobile_phones": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Monthly mobile phone costs in pounds."
            },
            "internet_tv": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Monthly broadband/TV costs in pounds."
            },
            "subscriptions": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Monthly subscriptions (Netflix, gym etc.) in pounds."
            },
            "clothing_personal_care": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Monthly clothing and personal care in pounds."
            },
            "entertainment_dining": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Monthly entertainment and dining out in pounds."
            },
            "holidays_travel": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Monthly average for holidays/travel in pounds."
            },
            "pets": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Monthly pet costs in pounds."
            },
            "childcare": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Monthly childcare costs in pounds."
            },
            "school_fees": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Monthly school fees in pounds."
            },
            "school_lunches": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Monthly school lunches in pounds."
            },
            "school_extras": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Monthly school extras (uniforms, trips) in pounds."
            },
            "university_fees": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Monthly university costs in pounds."
            },
            "children_activities": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Monthly children activities (sports, music) in pounds."
            },
            "gifts_charity": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Monthly gifts and presents in pounds."
            },
            "charitable_donations": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Monthly charitable donations in pounds."
            },
            "other_expenditure": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Any other monthly expenses in pounds."
            }
        },
        "required": [
            "rent",
            "utilities",
            "food_groceries",
            "transport_fuel",
            "healthcare_medical",
            "insurance",
            "mobile_phones",
            "internet_tv",
            "subscriptions",
            "clothing_personal_care",
            "entertainment_dining",
            "holidays_travel",
            "pets",
            "childcare",
            "school_fees",
            "school_lunches",
            "school_extras",
            "university_fees",
            "children_activities",
            "gifts_charity",
            "charitable_donations",
            "other_expenditure"
        ],
        "additionalProperties": false
    },
    "strict": true
}
```
