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
    "description": "Create a liability only after the user explicitly confirms whether it is owned individually or jointly. Joint records also require the joint owner and primary owner's percentage share. Never infer ownership.",
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
            "ownership_type": {
                "type": "string",
                "enum": ["individual", "joint", "tenants_in_common", "trust"],
                "description": "Ownership explicitly confirmed by the user."
            },
            "joint_owner_id": {
                "type": "integer",
                "description": "User ID of the confirmed joint owner. Required for joint or tenants_in_common ownership."
            },
            "trust_id": {
                "type": "integer",
                "description": "ID of the trust already linked to the authenticated user's household. Required for trust ownership."
            },
            "ownership_percentage": {
                "type": "number",
                "description": "Primary owner's confirmed percentage share. Required for joint or tenants_in_common ownership."
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
            "current_balance",
            "ownership_type"
        ],
        "additionalProperties": false
    }
}
```
