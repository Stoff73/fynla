---
procedure_id: 'savings.tool.create_holding'
kind: tool_schema
module: savings
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "create_holding",
    "description": "Add a holding to an EXISTING investment account that was already created WITHOUT holdings. Use this ONLY when the user wants to add holdings to an account that already exists. If the user is creating a NEW account AND mentions holdings at the same time, use create_investment_account with the holdings parameter instead. You MAY call this tool multiple times in the same turn when the user mentions multiple holdings.",
    "parameters": {
        "type": "object",
        "properties": {
            "account_name": {
                "type": "string",
                "description": "Name or provider of the existing investment account to add the holding to."
            },
            "security_name": {
                "type": "string",
                "description": "Name of the fund, ETF, or share (e.g. \"Vanguard FTSE All-World\")."
            },
            "ticker": {
                "type": "string",
                "description": "Ticker symbol (e.g. \"VWRL\", \"SWDA\")."
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
                "description": "\"fund\" for OEICs/unit trusts, \"etf\" for ETFs, \"uk_equity\" / \"us_equity\" / \"international_equity\" for shares, \"bond\" for fixed income, \"cash\", \"alternative\" for commodities/crypto, \"property\" for property funds."
            },
            "allocation_percent": {
                "type": "number",
                "description": "Percentage of the account this holding represents (0-100)."
            },
            "current_price": {
                "type": "number",
                "description": "Current price per unit in pounds."
            },
            "ocf_percent": {
                "type": "number",
                "description": "Ongoing Charge Figure as percentage (e.g. 0.22 for 0.22%)."
            }
        },
        "required": [
            "account_name",
            "security_name",
            "asset_type"
        ],
        "additionalProperties": false
    }
}
```
