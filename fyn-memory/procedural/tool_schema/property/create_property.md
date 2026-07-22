---
procedure_id: 'property.tool.create_property'
kind: tool_schema
module: property
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "create_property",
    "description": "Create a property only after the user explicitly confirms whether it is owned individually, jointly, as tenants in common, or in trust. Joint and tenants-in-common records also require the joint owner and primary owner's percentage share. Never infer ownership from my or our.",
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
                "description": "Type of property. Default \"main_residence\" if this is their home."
            },
            "current_value": {
                "type": "number",
                "description": "Current estimated value in pounds"
            },
            "ownership_type": {
                "type": "string",
                "enum": ["individual", "joint", "tenants_in_common", "trust"],
                "description": "Ownership explicitly confirmed by the user."
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
            },
            "purchase_price": {
                "type": "number",
                "description": "Original purchase price in pounds"
            },
            "purchase_date": {
                "type": "string",
                "format": "date",
                "description": "Purchase date in YYYY-MM-DD format (approximate year is fine, e.g., \"2018-01-01\")"
            },
            "address_line_1": {
                "type": "string",
                "description": "Street address or description"
            },
            "postcode": {
                "type": "string",
                "description": "UK postcode"
            },
            "outstanding_mortgage": {
                "type": "number",
                "description": "Outstanding mortgage balance in pounds. If provided, a linked mortgage will be created automatically."
            },
            "mortgage_rate": {
                "type": "number",
                "description": "Mortgage interest rate as a percentage (e.g., 4.2 for 4.2%). Only used if outstanding_mortgage is provided."
            },
            "mortgage_lender": {
                "type": "string",
                "description": "Mortgage lender name. Only used if outstanding_mortgage is provided."
            },
            "monthly_rental_income": {
                "type": "number",
                "description": "Monthly rental income in pounds. For buy-to-let properties."
            }
        },
        "required": [
            "property_type",
            "current_value",
            "ownership_type"
        ],
        "additionalProperties": false
    }
}
```
