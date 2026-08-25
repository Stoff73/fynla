---
procedure_id: 'property.tool.create_mortgage'
kind: tool_schema
module: property
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "create_mortgage",
    "description": "Create a standalone mortgage linked to an existing property. Use this when the user mentions a mortgage separately from a property, or wants to add a mortgage to an existing property. You MAY call this tool multiple times in the same turn when the user mentions multiple mortgages. If the user has only asked to add details without giving any specifics yet, do NOT call this tool — ask for the details first, and never invent names or values.",
    "parameters": {
        "type": "object",
        "properties": {
            "property_address_hint": {
                "type": "string",
                "description": "A hint to match the property — can be address, postcode, or description like \"my main home\". The system will fuzzy-match against existing properties."
            },
            "lender_name": {
                "type": "string",
                "description": "Mortgage lender (e.g., \"Halifax\", \"Nationwide\")"
            },
            "outstanding_balance": {
                "type": "number",
                "description": "Outstanding mortgage balance in pounds"
            },
            "interest_rate": {
                "type": "number",
                "description": "Current interest rate as a percentage (e.g., 4.2 for 4.2%)"
            },
            "mortgage_type": {
                "type": "string",
                "enum": [
                    "repayment",
                    "interest_only",
                    "mixed"
                ],
                "description": "Mortgage repayment type. Default \"repayment\"."
            },
            "rate_type": {
                "type": "string",
                "enum": [
                    "fixed",
                    "variable",
                    "tracker"
                ],
                "description": "Interest rate type. Default \"fixed\"."
            },
            "monthly_payment": {
                "type": "number",
                "description": "Monthly payment amount in pounds"
            },
            "remaining_term_months": {
                "type": "integer",
                "description": "Remaining mortgage term in months"
            }
        },
        "required": [
            "outstanding_balance"
        ],
        "additionalProperties": false
    }
}
```
