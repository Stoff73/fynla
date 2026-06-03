---
procedure_id: 'property.tool.create_mortgage'
kind: tool_schema
module: property
provider: xai
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "create_mortgage",
    "description": "Add a mortgage to an existing property. Use when the user mentions a mortgage separately from a property. Call this tool IMMEDIATELY with whatever details the user provided. Set null for anything not mentioned. The form will be filled in front of the user. After filling, ask if they want to add more details before saving. You MAY call this tool multiple times in the same turn when the user mentions multiple mortgages.",
    "parameters": {
        "type": "object",
        "properties": {
            "property_address_hint": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "A hint to match the property — address, postcode, or \"my main home\". System fuzzy-matches."
            },
            "lender_name": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Mortgage lender name (e.g. \"Halifax\")."
            },
            "outstanding_balance": {
                "type": "number",
                "description": "Outstanding mortgage balance in pounds."
            },
            "interest_rate": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Current interest rate as percentage (e.g. 4.2)."
            },
            "mortgage_type": {
                "anyOf": [
                    {
                        "type": "string",
                        "enum": [
                            "repayment",
                            "interest_only",
                            "mixed"
                        ]
                    },
                    {
                        "type": "null"
                    }
                ],
                "description": "Repayment type. Default \"repayment\"."
            },
            "rate_type": {
                "anyOf": [
                    {
                        "type": "string",
                        "enum": [
                            "fixed",
                            "variable",
                            "tracker",
                            "discount",
                            "mixed"
                        ]
                    },
                    {
                        "type": "null"
                    }
                ],
                "description": "Interest rate type. Default \"fixed\"."
            },
            "monthly_payment": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Monthly payment amount in pounds."
            },
            "remaining_term_months": {
                "type": [
                    "integer",
                    "null"
                ],
                "description": "Remaining mortgage term in months."
            },
            "start_date": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Mortgage start date (YYYY-MM-DD)."
            },
            "maturity_date": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Mortgage end/maturity date (YYYY-MM-DD)."
            }
        },
        "required": [
            "property_address_hint",
            "lender_name",
            "outstanding_balance",
            "interest_rate",
            "mortgage_type",
            "rate_type",
            "monthly_payment",
            "remaining_term_months",
            "start_date",
            "maturity_date"
        ],
        "additionalProperties": false
    },
    "strict": true
}
```
