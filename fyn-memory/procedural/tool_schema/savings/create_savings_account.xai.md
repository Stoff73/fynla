---
procedure_id: 'savings.tool.create_savings_account'
kind: tool_schema
module: savings
provider: xai
version: 2
active: true
effective_from: 2026-06-11
---

```json
{
    "name": "create_savings_account",
    "description": "Create a bank account or savings product only after the user explicitly states its type and whether it is owned individually or jointly. Never infer Cash ISA from a bare ISA. Joint records also require the joint owner and the primary owner's percentage share. You MAY call this tool multiple times when every record has those facts.",
    "parameters": {
        "type": "object",
        "properties": {
            "account_name": {
                "type": "string",
                "description": "Name of the account (e.g. \"Nationwide Cash ISA\", \"HSBC Current Account\", \"Marcus Savings\")"
            },
            "account_type": {
                "anyOf": [
                    {
                        "type": "string",
                        "enum": [
                            "savings_account",
                            "current_account",
                            "easy_access",
                            "instant_access",
                            "notice",
                            "fixed",
                            "cash_isa",
                            "junior_isa",
                            "premium_bonds",
                            "nsi"
                        ]
                    },
                    {
                        "type": "null"
                    }
                ],
                "description": "Product type explicitly stated by the user. Cash ISA and Junior ISA are distinct; never default a bare ISA to either subtype."
            },
            "institution": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Bank or building society name (e.g. \"HSBC\", \"Nationwide\", \"Marcus\")"
            },
            "current_balance": {
                "type": "number",
                "description": "Current balance in pounds"
            },
            "interest_rate": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Annual interest rate as a percentage (e.g. 4.5). Use 0 for premium bonds."
            },
            "is_isa": {
                "type": [
                    "boolean",
                    "null"
                ],
                "description": "Whether this is an explicitly confirmed ISA wrapper. Set true only after the user confirms Cash ISA or Junior ISA; never infer it from a bare or negated ISA reference."
            },
            "is_emergency_fund": {
                "type": [
                    "boolean",
                    "null"
                ],
                "description": "Whether this forms part of the emergency fund. Set true if user says \"emergency fund\" or \"rainy day\". Default false."
            },
            "regular_contribution_amount": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Monthly contribution amount in pounds, if any"
            },
            "isa_subscription_amount": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "For ISAs only: amount the user has already put into this ISA in the CURRENT tax year, when they state it (e.g. \"about £100 this year\" → 100). Leave null if not mentioned."
            },
            "ownership_type": {
                "type": "string",
                "enum": ["individual", "joint", "tenants_in_common", "trust"],
                "description": "Ownership explicitly confirmed by the user. Never default a missing answer to individual. ISAs must be individual."
            },
            "joint_owner_id": {
                "type": ["integer", "null"],
                "description": "User ID of the confirmed joint owner. Required when ownership is joint or tenants_in_common; otherwise null."
            },
            "trust_id": {
                "type": ["integer", "null"],
                "description": "ID of the trust already linked to the authenticated user's household. Required for trust ownership; otherwise null."
            },
            "ownership_percentage": {
                "type": ["number", "null"],
                "description": "Primary owner's confirmed percentage share. Required when ownership is joint or tenants_in_common; otherwise 100 for individual."
            }
        },
        "required": [
            "account_name",
            "account_type",
            "institution",
            "current_balance",
            "interest_rate",
            "is_isa",
            "is_emergency_fund",
            "regular_contribution_amount",
            "isa_subscription_amount",
            "ownership_type",
            "joint_owner_id",
            "trust_id",
            "ownership_percentage"
        ],
        "additionalProperties": false
    },
    "strict": true
}
```
