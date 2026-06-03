---
procedure_id: 'expenditure.tool.set_expenditure'
kind: tool_schema
module: expenditure
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "set_expenditure",
    "description": "Set the user's monthly expenditure by category. Call this IMMEDIATELY when the user mentions their spending, bills, or monthly outgoings. Fill in every category the user mentions and omit anything not mentioned. The form will be opened, filled, and saved. This tool captures all categories in a SINGLE call — do NOT call it multiple times per turn.",
    "parameters": {
        "type": "object",
        "properties": {
            "rent": {
                "type": "number",
                "description": "Monthly rent in pounds."
            },
            "utilities": {
                "type": "number",
                "description": "Monthly utilities (gas, electricity, water) in pounds."
            },
            "food_groceries": {
                "type": "number",
                "description": "Monthly food and groceries in pounds."
            },
            "transport_fuel": {
                "type": "number",
                "description": "Monthly transport/fuel costs in pounds."
            },
            "healthcare_medical": {
                "type": "number",
                "description": "Monthly healthcare costs in pounds."
            },
            "insurance": {
                "type": "number",
                "description": "Monthly non-property insurance in pounds."
            },
            "mobile_phones": {
                "type": "number",
                "description": "Monthly mobile phone costs in pounds."
            },
            "internet_tv": {
                "type": "number",
                "description": "Monthly broadband/TV costs in pounds."
            },
            "subscriptions": {
                "type": "number",
                "description": "Monthly subscriptions in pounds."
            },
            "clothing_personal_care": {
                "type": "number",
                "description": "Monthly clothing and personal care in pounds."
            },
            "entertainment_dining": {
                "type": "number",
                "description": "Monthly entertainment and dining in pounds."
            },
            "holidays_travel": {
                "type": "number",
                "description": "Monthly holidays/travel in pounds."
            },
            "pets": {
                "type": "number",
                "description": "Monthly pet costs in pounds."
            },
            "childcare": {
                "type": "number",
                "description": "Monthly childcare costs in pounds."
            },
            "school_fees": {
                "type": "number",
                "description": "Monthly school fees in pounds."
            },
            "school_lunches": {
                "type": "number",
                "description": "Monthly school lunches in pounds."
            },
            "school_extras": {
                "type": "number",
                "description": "Monthly school extras in pounds."
            },
            "university_fees": {
                "type": "number",
                "description": "Monthly university costs in pounds."
            },
            "children_activities": {
                "type": "number",
                "description": "Monthly children activities in pounds."
            },
            "gifts_charity": {
                "type": "number",
                "description": "Monthly gifts in pounds."
            },
            "charitable_donations": {
                "type": "number",
                "description": "Monthly charitable donations in pounds."
            },
            "other_expenditure": {
                "type": "number",
                "description": "Other monthly expenses in pounds."
            }
        },
        "required": [],
        "additionalProperties": false
    }
}
```
