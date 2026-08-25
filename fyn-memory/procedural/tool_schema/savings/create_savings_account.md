---
procedure_id: 'savings.tool.create_savings_account'
kind: tool_schema
module: savings
version: 2
active: true
effective_from: 2026-06-11
---

```json
{
    "name": "create_savings_account",
    "description": "Create a savings account only after the user explicitly states its type and whether it is owned individually or jointly. Never infer Cash ISA from a bare ISA. Joint records also require the joint owner and the primary owner's percentage share. You MAY call this tool multiple times in the same turn when every record has those facts.",
    "parameters": {
        "type": "object",
        "properties": {
            "account_name": {
                "type": "string",
                "description": "Name of the account (e.g., \"Nationwide Cash ISA\", \"Halifax Easy Saver\")"
            },
            "account_type": {
                "type": "string",
                "enum": [
                    "easy_access",
                    "notice",
                    "fixed_term",
                    "regular_saver",
                    "current_account",
                    "cash_isa",
                    "junior_isa",
                    "premium_bonds",
                    "nsi"
                ],
                "description": "Type explicitly stated by the user. Cash ISA and Junior ISA are distinct; never default a bare ISA to either subtype."
            },
            "institution": {
                "type": "string",
                "description": "Bank or building society name (e.g., \"Nationwide\", \"Halifax\")"
            },
            "current_balance": {
                "type": "number",
                "description": "Current balance in pounds"
            },
            "interest_rate": {
                "type": "number",
                "description": "Annual interest rate as a percentage (e.g., 4.5 for 4.5%)"
            },
            "is_isa": {
                "type": "boolean",
                "description": "Whether this is an explicitly confirmed ISA wrapper, including a distinct Cash ISA or Junior ISA. Never infer this from a bare or negated ISA reference."
            },
            "is_emergency_fund": {
                "type": "boolean",
                "description": "Whether this forms part of the emergency fund. Default false."
            },
            "regular_contribution_amount": {
                "type": "number",
                "description": "Monthly contribution amount in pounds, if any"
            },
            "isa_subscription_amount": {
                "type": "number",
                "description": "For ISAs only: amount the user has already put into this ISA in the CURRENT tax year, when they state it (e.g. \"about £100 this year\" → 100). Leave null if not mentioned."
            },
            "ownership_type": {
                "type": "string",
                "enum": ["individual", "joint", "tenants_in_common", "trust"],
                "description": "Ownership explicitly confirmed by the user. Never default a missing answer to individual. ISAs must be individual."
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
            }
        },
        "required": [
            "account_name",
            "current_balance",
            "ownership_type"
        ],
        "additionalProperties": false
    }
}
```
