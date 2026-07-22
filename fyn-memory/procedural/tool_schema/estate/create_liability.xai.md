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
    "description": "Create a liability only after the user explicitly confirms whether it is owned individually or jointly. Joint records also require the joint owner and primary owner's percentage share. Never infer ownership.",
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
            "ownership_type": {
                "type": "string",
                "enum": ["individual", "joint", "tenants_in_common", "trust"],
                "description": "Ownership explicitly confirmed by the user."
            },
            "joint_owner_id": {
                "type": ["integer", "null"],
                "description": "User ID of the confirmed joint owner. Required for joint or tenants_in_common ownership; otherwise null."
            },
            "trust_id": {
                "type": ["integer", "null"],
                "description": "ID of the trust already linked to the authenticated user's household. Required for trust ownership; otherwise null."
            },
            "ownership_percentage": {
                "type": ["number", "null"],
                "description": "Primary owner's confirmed percentage share. Required for joint or tenants_in_common ownership; otherwise 100 for individual."
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
            "ownership_type",
            "joint_owner_id",
            "trust_id",
            "ownership_percentage",
            "monthly_payment",
            "interest_rate"
        ],
        "additionalProperties": false
    },
    "strict": true
}
```
