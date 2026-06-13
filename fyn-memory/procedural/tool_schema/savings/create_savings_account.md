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
    "description": "Create a savings account for the user. Use this when the user mentions a savings account, Cash Individual Savings Account, or cash deposit. You MAY call this tool multiple times in the same turn when the user mentions multiple accounts.",
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
                    "regular_saver"
                ],
                "description": "Type of savings account. Default to \"easy_access\" if not specified."
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
                "description": "Whether this is a Cash Individual Savings Account. Default false."
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
            }
        },
        "required": [
            "account_name",
            "current_balance"
        ],
        "additionalProperties": false
    }
}
```
