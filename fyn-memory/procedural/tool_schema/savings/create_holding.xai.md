---
procedure_id: 'savings.tool.create_holding'
kind: tool_schema
module: savings
provider: xai
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "create_holding",
    "description": "Add a holding to an EXISTING investment account that was already created WITHOUT holdings. Use this ONLY when the user wants to add holdings to an account that already exists and has no holdings. If the user is creating a NEW account AND mentions holdings at the same time, use create_investment_account with the holdings parameter instead. Call this tool IMMEDIATELY. You MAY call this tool multiple times in the same turn when the user mentions multiple holdings (e.g. \"in my SIPP I hold Apple and Microsoft\" → two tool calls). If the user has only asked to add details without giving any specifics yet, do NOT call this tool — ask for the details first, and never invent names or values.",
    "parameters": {
        "type": "object",
        "properties": {
            "account_name": {
                "type": "string",
                "description": "Name or provider of the investment account to add the holding to (e.g. \"Vanguard ISA\", \"Hargreaves Lansdown\"). Must match an existing account."
            },
            "security_name": {
                "type": "string",
                "description": "Name of the fund, ETF, or share (e.g. \"Vanguard FTSE All-World\", \"iShares Core MSCI World\")"
            },
            "ticker": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Ticker symbol (e.g. \"VWRL\", \"SWDA\", \"VUSA\")"
            },
            "asset_type": {
                "type": "string",
                "enum": [
                    "uk_equity",
                    "us_equity",
                    "international_equity",
                    "fund",
                    "etf",
                    "bond",
                    "cash",
                    "alternative",
                    "property"
                ],
                "description": "\"fund\" for OEICs/unit trusts. \"etf\" for ETFs. \"uk_equity\" for UK shares. \"us_equity\" for US shares. \"international_equity\" for other shares. \"bond\" for fixed income. \"cash\" for cash holdings. \"alternative\" for commodities/crypto/etc. \"property\" for property funds."
            },
            "allocation_percent": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Percentage of the account this holding represents (0-100)."
            },
            "purchase_price": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Purchase price per unit (£)."
            },
            "current_price": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Current price per unit (£)."
            },
            "ocf_percent": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Ongoing Charge Figure as percentage (e.g. 0.22 for 0.22%)."
            }
        },
        "required": [
            "account_name",
            "security_name",
            "ticker",
            "asset_type",
            "allocation_percent",
            "purchase_price",
            "current_price",
            "ocf_percent"
        ],
        "additionalProperties": false
    },
    "strict": true
}
```
