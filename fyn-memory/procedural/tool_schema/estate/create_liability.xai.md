---
procedure_id: 'estate.tool.create_liability'
kind: tool_schema
module: estate
provider: xai
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "create_liability",
    "description": "Create a liability. Use for any debt: credit cards, loans, student loans, car finance, overdrafts. Call this tool IMMEDIATELY. You MAY call this tool multiple times in the same turn when the user mentions multiple liabilities.",
    "parameters": {
        "type": "object",
        "properties": {
            "liability_name": {
                "type": "string",
                "description": "Name of the liability (e.g. \"Barclays Visa\", \"Halifax Personal Loan\", \"BMW Car Finance\")"
            },
            "liability_type": {
                "type": "string",
                "enum": [
                    "personal_loan",
                    "credit_card",
                    "student_loan",
                    "hire_purchase",
                    "secured_loan",
                    "overdraft",
                    "business_loan",
                    "other"
                ],
                "description": "Type. \"hire_purchase\" for car finance/HP. \"personal_loan\" for bank loans. \"credit_card\" for credit cards. \"student_loan\" for student loans. \"overdraft\" for bank overdrafts."
            },
            "current_balance": {
                "type": "number",
                "description": "Outstanding balance (£)"
            },
            "monthly_payment": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Monthly payment (£)"
            },
            "interest_rate": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Interest rate as percentage"
            }
        },
        "required": [
            "liability_name",
            "liability_type",
            "current_balance",
            "monthly_payment",
            "interest_rate"
        ],
        "additionalProperties": false
    },
    "strict": true
}
```
