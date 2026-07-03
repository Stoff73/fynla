---
procedure_id: 'property.tool.create_property'
kind: tool_schema
module: property
provider: xai
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "create_property",
    "description": "Create a property record and optionally a linked mortgage. Call this tool IMMEDIATELY when the user mentions a property — do not ask questions first. Fill in every field you can from what the user said and set null for anything not mentioned. The form will be opened, filled, and saved automatically. After saving, confirm what was added and ask if they want to update any details (postcode, monthly costs, etc.) or add another property. Infer sensible values: if they say \"my house\" assume main_residence, if they say \"our house\" assume joint ownership. You MAY call this tool multiple times in the same turn when the user mentions multiple properties (e.g. \"main residence and a buy-to-let\" → two tool calls) — the frontend queue saves them in order. Do NOT call navigate_to_page or get_module_analysis in the same turn as create_property — those interrupt the form fill. If the user has only asked to add details without giving any specifics yet, do NOT call this tool — ask for the details first, and never invent names or values.",
    "parameters": {
        "type": "object",
        "properties": {
            "property_type": {
                "type": "string",
                "enum": [
                    "main_residence",
                    "secondary_residence",
                    "buy_to_let"
                ],
                "description": "Type of property. \"main_residence\" for their primary home, \"secondary_residence\" for holiday homes, \"buy_to_let\" for rental properties."
            },
            "current_value": {
                "type": "number",
                "description": "Current estimated market value of the full property in pounds (e.g. 450000). Always the FULL value, not the user's share."
            },
            "address_line_1": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Street address (e.g. \"42 Oak Lane\"). Try to extract from what user said."
            },
            "address_line_2": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Second address line if needed."
            },
            "city": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "City or town. Infer from address if user mentions a place name (e.g. \"house in Guildford\" → city is \"Guildford\")."
            },
            "county": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "County."
            },
            "postcode": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "UK postcode (e.g. \"SW1A 1AA\"). Include if the user mentions it."
            },
            "purchase_price": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Original purchase price in pounds."
            },
            "purchase_date": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Purchase date (YYYY-MM-DD). If only year known, use Jan 1st (e.g. \"2015-01-01\")."
            },
            "valuation_date": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Date of most recent valuation (YYYY-MM-DD). Null if current_value is an estimate."
            },
            "ownership_type": {
                "anyOf": [
                    {
                        "type": "string",
                        "enum": [
                            "individual",
                            "joint",
                            "tenants_in_common",
                            "trust"
                        ]
                    },
                    {
                        "type": "null"
                    }
                ],
                "description": "How the property is owned. \"individual\" = sole owner. \"joint\" = joint tenancy (equal shares, passes to survivor). \"tenants_in_common\" = can have unequal shares, passes via will. \"trust\" = held in a trust. Default to \"individual\" if not specified."
            },
            "ownership_percentage": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Primary owner's percentage share (0-100). Individual=100, joint=50 typically, tenants_in_common=whatever they specify."
            },
            "joint_owner_name": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Name of joint owner. Only if joint or tenants_in_common. Use spouse name if mentioned."
            },
            "tenure_type": {
                "anyOf": [
                    {
                        "type": "string",
                        "enum": [
                            "freehold",
                            "leasehold"
                        ]
                    },
                    {
                        "type": "null"
                    }
                ],
                "description": "Freehold (owns the land) or leasehold (owns for a fixed term, common for flats). Null defaults to freehold."
            },
            "lease_remaining_years": {
                "type": [
                    "integer",
                    "null"
                ],
                "description": "Years remaining on the lease. Only if leasehold."
            },
            "lease_expiry_date": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Lease expiry date (YYYY-MM-DD). Only if leasehold."
            },
            "has_mortgage": {
                "type": "boolean",
                "description": "Whether the property has a mortgage. True if the user mentions any mortgage, balance, or lender."
            },
            "mortgage_lender": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Mortgage lender name (e.g. \"Halifax\", \"Nationwide\")."
            },
            "mortgage_outstanding_balance": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Outstanding mortgage balance in pounds. The full balance, not the user's share."
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
                "description": "\"repayment\" = capital + interest (most common). \"interest_only\" = only pay interest. \"mixed\" = part repayment, part interest-only."
            },
            "mortgage_rate_type": {
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
                "description": "Interest rate type. \"fixed\" = locked rate. \"variable\" = lender SVR. \"tracker\" = follows base rate. \"discount\" = discount off SVR. \"mixed\" = split."
            },
            "mortgage_interest_rate": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Current interest rate as percentage (e.g. 4.2)."
            },
            "mortgage_monthly_payment": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Monthly mortgage payment in pounds."
            },
            "mortgage_start_date": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Mortgage start date (YYYY-MM-DD)."
            },
            "mortgage_maturity_date": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Mortgage end/maturity date (YYYY-MM-DD)."
            },
            "monthly_council_tax": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Monthly council tax (£)."
            },
            "monthly_gas": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Monthly gas bill (£)."
            },
            "monthly_electricity": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Monthly electricity bill (£)."
            },
            "monthly_water": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Monthly water bill (£)."
            },
            "monthly_building_insurance": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Monthly building insurance (£)."
            },
            "monthly_contents_insurance": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Monthly contents insurance (£)."
            },
            "monthly_service_charge": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Monthly service charge (£). Common for leasehold."
            },
            "monthly_maintenance_reserve": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Monthly maintenance reserve (£)."
            },
            "other_monthly_costs": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Any other monthly property costs (£)."
            },
            "monthly_rental_income": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Monthly rental income (£). Only for buy_to_let."
            },
            "tenant_name": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Current tenant name. Only for buy_to_let."
            },
            "managing_agent_name": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Letting/managing agent name. Only for buy_to_let."
            }
        },
        "required": [
            "property_type",
            "current_value",
            "address_line_1",
            "address_line_2",
            "city",
            "county",
            "postcode",
            "purchase_price",
            "purchase_date",
            "valuation_date",
            "ownership_type",
            "ownership_percentage",
            "joint_owner_name",
            "tenure_type",
            "lease_remaining_years",
            "lease_expiry_date",
            "has_mortgage",
            "mortgage_lender",
            "mortgage_outstanding_balance",
            "mortgage_type",
            "mortgage_rate_type",
            "mortgage_interest_rate",
            "mortgage_monthly_payment",
            "mortgage_start_date",
            "mortgage_maturity_date",
            "monthly_council_tax",
            "monthly_gas",
            "monthly_electricity",
            "monthly_water",
            "monthly_building_insurance",
            "monthly_contents_insurance",
            "monthly_service_charge",
            "monthly_maintenance_reserve",
            "other_monthly_costs",
            "monthly_rental_income",
            "tenant_name",
            "managing_agent_name"
        ],
        "additionalProperties": false
    },
    "strict": true
}
```
