---
procedure_id: 'savings.tool.create_pension'
kind: tool_schema
module: savings
version: 3
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "create_pension",
    "description": "Create a pension for the user. Call this ONLY ONCE PER PENSION — if you have already recorded a pension in this conversation, do not record it again under a slightly different name. When the user then supplies a detail you were missing (the scheme type, the value, the retirement age), CORRECT the record you already created by calling `update_record` with entity_type \"dc_pension\" and that pension's id — never call this tool again, and never claim you have recorded something without a tool call actually succeeding. When speaking to the user, always write pension names in full — \"workplace pension\", \"final salary pension\", \"career average pension\", \"Self-Invested Personal Pension\", \"personal pension\" — and never abbreviate any of them. Omit every field the user did not state — never fill unknown fields with 0 or placeholder values, and never invent a scheme name. If the user has asked to add a pension without giving any details, ask for the provider and the current value first. INFER the scheme type rather than interrogating the user: a Self-Invested Personal Pension, a stakeholder or a plan the user pays into themselves is a personal arrangement; a scheme an employer contributes to is a workplace one; only a pension paying a guaranteed income based on salary and years of service (final salary or career average, typically NHS, Teachers, Civil Service or similar) is a defined-benefit scheme. If you genuinely cannot tell, DEFAULT to a personal pension — do not ask the user to classify it. Ask only one plain-language question, and only when the answer changes the outcome: \"Does this pension pay you a guaranteed income based on your salary and years of service, or is it a pot of money you've built up?\"",
    "parameters": {
        "type": "object",
        "properties": {
            "pension_category": {
                "type": "string",
                "enum": [
                    "dc",
                    "db"
                ],
                "description": "Internal wire value — never shown to the user. \"dc\" for a pot-of-money pension (workplace, Self-Invested Personal Pension, personal, stakeholder). \"db\" ONLY for a pension paying a guaranteed income based on salary and service (final salary, career average). Infer this; do not ask the user for it in these words. Default \"dc\"."
            },
            "scheme_name": {
                "type": "string",
                "description": "Name of the pension scheme as the user described it (e.g. \"Aviva Pension\", \"NHS Pension Scheme\"). Use the user's own words. Do not add or remove qualifiers between turns in the same conversation — a renamed scheme creates a duplicate record."
            },
            "scheme_type": {
                "type": "string",
                "enum": [
                    "workplace",
                    "sipp",
                    "personal_pension",
                    "stakeholder",
                    "final_salary",
                    "career_average"
                ],
                "description": "Internal wire value — never shown to the user. Pot-of-money pensions: \"workplace\" (employer contributes), \"sipp\" (Self-Invested Personal Pension), \"personal_pension\", \"stakeholder\". Guaranteed-income pensions: \"final_salary\", \"career_average\". Must be one of these exact lowercase values. When the user has not made the type clear, send \"personal_pension\"."
            },
            "provider": {
                "type": "string",
                "description": "Pension provider (e.g. \"Aviva\", \"Scottish Widows\"). Pot-of-money pensions only."
            },
            "current_fund_value": {
                "type": "number",
                "description": "Current fund value in pounds. Pot-of-money pensions only."
            },
            "employee_contribution_percent": {
                "type": "number",
                "description": "Employee contribution as percentage of salary (e.g. 5 for 5%). Workplace pensions only."
            },
            "employer_contribution_percent": {
                "type": "number",
                "description": "Employer contribution as percentage of salary (e.g. 3 for 3%). Workplace pensions only."
            },
            "accrued_annual_pension": {
                "type": "number",
                "description": "Accrued annual pension in pounds. Guaranteed-income pensions only."
            },
            "normal_retirement_age": {
                "type": "integer",
                "description": "Normal retirement age for the scheme. Guaranteed-income pensions only."
            },
            "pensionable_service_years": {
                "type": "number",
                "description": "Years of pensionable service. Guaranteed-income pensions only."
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
