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
    "description": "Create a bank account or savings product. Use for current accounts, savings accounts, Cash ISAs, premium bonds, or NS&I products. Call this tool IMMEDIATELY when the user mentions any bank account or cash savings. You MAY call this tool multiple times in the same turn when the user mentions multiple accounts (e.g. \"I have a Halifax ISA and a Nationwide saver\" → two tool calls). If the user has only asked to add details without giving any specifics yet, do NOT call this tool — ask for the details first, and never invent names or values.",
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
                "description": "Product type. DEFAULT to \"current_account\" for any everyday bank or current account, and whenever the user does NOT name a specific savings product. Only use a savings type when the user explicitly says so: \"easy_access\" or \"savings_account\" for a savings account, \"notice\" for notice accounts, \"fixed\" for fixed term, \"cash_isa\" for a Cash ISA, \"premium_bonds\" for NS&I Premium Bonds, \"nsi\" for other NS&I products."
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
                "description": "Whether this is a Cash ISA. Set true if user says \"ISA\" or \"tax-free\". Default false."
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
            "isa_subscription_amount"
        ],
        "additionalProperties": false
    },
    "strict": true
}
```
