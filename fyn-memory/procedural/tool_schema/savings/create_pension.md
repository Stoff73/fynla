---
procedure_id: 'savings.tool.create_pension'
kind: tool_schema
module: savings
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "create_pension",
    "description": "Create a pension for the user. Handles both Defined Contribution (workplace, Self-Invested Personal Pension, personal) and Defined Benefit (final salary, career average) pensions. Omit every field the user did not state — never fill unknown fields with 0 or placeholder values. If the user has only asked to add a pension without giving any details yet, do NOT call this tool — ask for the scheme, provider, and value first; never invent a scheme name. You MAY call this tool multiple times in the same turn when the user mentions multiple pensions.",
    "parameters": {
        "type": "object",
        "properties": {
            "pension_category": {
                "type": "string",
                "enum": [
                    "dc",
                    "db"
                ],
                "description": "Whether this is a Defined Contribution (dc) or Defined Benefit (db) pension. Default \"dc\" for workplace/SIPP/personal pensions. Use \"db\" for final salary or career average schemes."
            },
            "scheme_name": {
                "type": "string",
                "description": "Name of the pension scheme (e.g., \"Aviva Workplace Pension\", \"NHS Pension Scheme\")"
            },
            "scheme_type": {
                "type": "string",
                "description": "For DC: \"workplace\", \"sipp\", or \"personal_pension\". For DB: \"final_salary\", \"career_average\", or \"public_sector\"."
            },
            "provider": {
                "type": "string",
                "description": "Pension provider (e.g., \"Aviva\", \"Scottish Widows\"). DC pensions only."
            },
            "current_fund_value": {
                "type": "number",
                "description": "Current fund value in pounds. DC pensions only."
            },
            "employee_contribution_percent": {
                "type": "number",
                "description": "Employee contribution as percentage of salary (e.g., 5 for 5%). DC pensions only."
            },
            "employer_contribution_percent": {
                "type": "number",
                "description": "Employer contribution as percentage of salary (e.g., 3 for 3%). DC pensions only."
            },
            "accrued_annual_pension": {
                "type": "number",
                "description": "Accrued annual pension in pounds. DB pensions only."
            },
            "normal_retirement_age": {
                "type": "integer",
                "description": "Normal retirement age for the scheme. DB pensions only."
            },
            "pensionable_service_years": {
                "type": "number",
                "description": "Years of pensionable service. DB pensions only."
            }
        },
        "required": [
            "pension_category",
            "scheme_name"
        ],
        "additionalProperties": false
    }
}
```
