---
procedure_id: 'data.tool.update_record'
kind: tool_schema
module: data
provider: xai
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "update_record",
    "description": "Update an existing record. Use when the user wants to change details of an existing financial record. Ask the user to confirm changes before calling. The handler enforces a per-entity allowlist — only fields documented for the chosen entity_type will be accepted; others return fields_not_allowed.",
    "parameters": {
        "type": "object",
        "properties": {
            "entity_type": {
                "type": "string",
                "enum": [
                    "goal",
                    "life_event",
                    "savings_account",
                    "investment_account",
                    "dc_pension",
                    "db_pension",
                    "property",
                    "mortgage",
                    "life_insurance",
                    "critical_illness",
                    "income_protection",
                    "estate_asset",
                    "estate_liability",
                    "estate_gift",
                    "family_member",
                    "trust",
                    "business_interest",
                    "chattel"
                ],
                "description": "The type of record to update"
            },
            "entity_id": {
                "type": "integer",
                "description": "The ID of the record to update"
            },
            "fields": {
                "type": "object",
                "description": "Key-value pairs to update. Allowed field names are constrained per entity_type (e.g. goal accepts goal_name/target_amount/target_date/priority/status; mortgage accepts outstanding_balance/interest_rate/monthly_payment but NOT start_date or mortgage_type). Inventing field names returns fields_not_allowed.",
                "properties": {
                    "goal_name": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "target_amount": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "target_date": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "priority": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "status": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "monthly_contribution": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "description": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "is_essential": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "event_name": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "event_type": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "expected_date": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "amount": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "certainty": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "impact_type": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "account_name": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "account_type": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "institution": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "current_balance": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "interest_rate": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "access_type": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "provider": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "current_value": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "monthly_contribution_amount": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "scheme_name": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "current_fund_value": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "employee_contribution_percent": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "employer_contribution_percent": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "retirement_age": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "accrued_annual_pension": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "normal_retirement_age": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "pensionable_salary": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "pensionable_service_years": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "property_type": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "address_line_1": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "monthly_rental_income": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "lender_name": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "outstanding_balance": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "monthly_payment": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "remaining_term_months": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "sum_assured": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "premium_amount": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "premium_frequency": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "policy_end_date": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "benefit_amount": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "deferred_period_weeks": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "asset_name": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "asset_type": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "liability_name": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "liability_type": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "gift_value": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "gift_date": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "recipient": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "gift_type": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "first_name": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "last_name": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "date_of_birth": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "annual_income": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "gender": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "is_dependent": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "education_status": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "trust_name": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "trust_type": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "initial_value": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "business_name": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "ownership_percentage": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "current_valuation": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "annual_revenue": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "annual_profit": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "name": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    },
                    "chattel_type": {
                        "type": [
                            "string",
                            "number",
                            "boolean",
                            "null"
                        ]
                    }
                },
                "additionalProperties": false
            }
        },
        "required": [
            "entity_type",
            "entity_id",
            "fields"
        ],
        "additionalProperties": false
    }
}
```
