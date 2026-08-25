---
procedure_id: 'estate.tool.create_business_interest'
kind: tool_schema
module: estate
provider: xai
version: 2
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "create_business_interest",
    "description": "Record a business interest or ownership. Handles sole trader, partnership, limited company, LLP. Call this tool IMMEDIATELY. You MAY call this tool multiple times in the same turn when the user mentions multiple businesses. If the user has only asked to add details without giving any specifics yet, do NOT call this tool — ask for the details first, and never invent names or values.",
    "parameters": {
        "type": "object",
        "properties": {
            "business_name": {
                "type": "string",
                "description": "Name of the business (e.g. \"Acme Technologies Ltd\", \"Smith Consulting\")"
            },
            "business_type": {
                "type": "string",
                "enum": [
                    "sole_trader",
                    "partnership",
                    "limited_company",
                    "llp",
                    "other"
                ],
                "description": "\"sole_trader\" for self-employed. \"partnership\" for partnerships. \"limited_company\" for Ltd companies. \"llp\" for Limited Liability Partnerships. \"other\" for anything else."
            },
            "industry_sector": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Industry sector (e.g. \"Technology\", \"Consulting\", \"Construction\", \"Retail\")"
            },
            "ownership_percentage": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Percentage owned (0-100). Default 100 for sole owner."
            },
            "estimated_value": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Estimated current value of the business (£)."
            },
            "annual_revenue": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Annual turnover/revenue (£)."
            },
            "annual_profit": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Annual net profit (£). Can be negative for losses."
            },
            "annual_dividend_income": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Annual dividends taken from this business (£). For limited companies only."
            },
            "company_number": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Companies House registration number for a UK-registered company or LLP — eight characters, either eight digits (e.g. \"12248522\") or two letters and six digits (e.g. \"SC123456\"). Supply it only if the user states it; never guess or derive it from the business name. Leave null for a sole trader, a partnership, or a business registered outside the United Kingdom."
            },
            "employee_count": {
                "type": [
                    "integer",
                    "null"
                ],
                "description": "Number of employees including the owner."
            }
        },
        "required": [
            "business_name",
            "business_type",
            "industry_sector",
            "ownership_percentage",
            "estimated_value",
            "annual_revenue",
            "annual_profit",
            "annual_dividend_income",
            "employee_count",
            "company_number"
        ],
        "additionalProperties": false
    },
    "strict": true
}
```
