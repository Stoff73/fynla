---
procedure_id: 'estate.tool.create_liability'
kind: tool_schema
module: estate
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "create_liability",
    "description": "Create a liability. Use this when the user mentions any debt: credit cards, personal loans, student loans, car finance, or any other outstanding balance owed. You MAY call this tool multiple times in the same turn when the user mentions multiple liabilities.",
    "parameters": {
        "type": "object",
        "properties": {
            "liability_name": {
                "type": "string",
                "description": "Name or description of the liability"
            },
            "liability_type": {
                "type": "string",
                "enum": [
                    "loan",
                    "personal_loan",
                    "credit_card",
                    "mortgage",
                    "student_loan",
                    "other"
                ],
                "description": "Type of liability"
            },
            "current_balance": {
                "type": "number",
                "description": "Outstanding balance in pounds"
            },
            "monthly_payment": {
                "type": "number",
                "description": "Monthly payment amount in pounds"
            },
            "interest_rate": {
                "type": "number",
                "description": "Interest rate as a percentage"
            }
        },
        "required": [
            "liability_name",
            "liability_type",
            "current_balance"
        ],
        "additionalProperties": false
    }
}
```
