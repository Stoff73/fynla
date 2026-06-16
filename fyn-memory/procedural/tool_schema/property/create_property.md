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
    "description": "Create a property for the user. If they also mention a mortgage, include the outstanding mortgage amount and it will be created automatically. You MAY call this tool multiple times in the same turn when the user mentions multiple properties — the frontend queue saves them in order. Do NOT call navigate_to_page or get_module_analysis in the same turn as create_property — those interrupt the form fill.",
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
            "current_value"
        ],
        "additionalProperties": false
    }
}
```
