---
procedure_id: 'savings.tool.create_pension'
kind: tool_schema
module: savings
provider: xai
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "create_pension",
    "description": "Create a pension for the user. Handles both Defined Contribution (DC: workplace, SIPP, personal) and Defined Benefit (DB: final salary, career average). Call this tool IMMEDIATELY when the user mentions a pension. Fill in every field you can. You MAY call this tool multiple times in the same turn when the user mentions multiple pensions (e.g. \"I have a workplace DC and a SIPP\" → two tool calls). If the user mentions a pension without specifying DC or DB, ask: \"Is this a workplace pension where your employer contributes, or a final salary/career average scheme?\"",
    "parameters": {
        "type": "object",
        "properties": {
            "pension_category": {
                "type": "string",
                "enum": [
                    "dc",
                    "db"
                ],
                "description": "\"dc\" for Defined Contribution (workplace, SIPP, personal). \"db\" for Defined Benefit (final salary, career average)."
            },
            "scheme_name": {
                "type": "string",
                "description": "Name of the pension scheme (e.g. \"Aviva Workplace Pension\", \"NHS Pension Scheme\")."
            },
            "scheme_type": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "DC: \"workplace\" (employer pension), \"sipp\" (Self-Invested Personal Pension), \"personal_pension\", \"stakeholder\". DB: \"final_salary\", \"career_average\"."
            },
            "provider": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Pension provider (e.g. \"Aviva\", \"Scottish Widows\"). DC only."
            },
            "current_fund_value": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Current fund value in pounds. DC only."
            },
            "employee_contribution_percent": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Employee contribution as % of salary (e.g. 5 for 5%). DC workplace only."
            },
            "employer_contribution_percent": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Employer contribution as % of salary (e.g. 3 for 3%). DC workplace only."
            },
            "annual_salary": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Annual salary in pounds. DC workplace only — needed to calculate contribution amounts."
            },
            "monthly_contribution_amount": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Fixed monthly contribution in pounds. DC personal/SIPP only."
            },
            "retirement_age": {
                "type": [
                    "integer",
                    "null"
                ],
                "description": "Planned access age (min 55). DC personal/SIPP only."
            },
            "accrued_annual_pension": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Projected annual pension at retirement in pounds. DB only."
            },
            "pensionable_service_years": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Years of pensionable service. DB only."
            },
            "normal_retirement_age": {
                "type": [
                    "integer",
                    "null"
                ],
                "description": "Normal retirement age for the scheme. DB only."
            },
            "scheme_status": {
                "anyOf": [
                    {
                        "type": "string",
                        "enum": [
                            "Active",
                            "Deferred",
                            "In Payment"
                        ]
                    },
                    {
                        "type": "null"
                    }
                ],
                "description": "DB pension status. \"Active\" if still contributing, \"Deferred\" if left employer, \"In Payment\" if retired. Default \"Active\"."
            },
            "final_salary": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Pensionable salary in pounds. DB only."
            },
            "accrual_rate": {
                "type": [
                    "integer",
                    "null"
                ],
                "description": "Accrual rate denominator (e.g. 60 for 1/60th). DB only. Common: 60 (public sector), 80 (older schemes)."
            }
        },
        "required": [
            "pension_category",
            "scheme_name",
            "scheme_type",
            "provider",
            "current_fund_value",
            "employee_contribution_percent",
            "employer_contribution_percent",
            "annual_salary",
            "monthly_contribution_amount",
            "retirement_age",
            "accrued_annual_pension",
            "pensionable_service_years",
            "normal_retirement_age",
            "scheme_status",
            "final_salary",
            "accrual_rate"
        ],
        "additionalProperties": false
    },
    "strict": true
}
```
