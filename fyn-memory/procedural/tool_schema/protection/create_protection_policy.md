---
procedure_id: 'protection.tool.create_protection_policy'
kind: tool_schema
module: protection
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "create_protection_policy",
    "description": "Create a protection insurance policy for the user. Handles life insurance, critical illness cover, and income protection policies. You MAY call this tool multiple times in the same turn when the user mentions multiple policies (e.g. life insurance AND critical illness). If the user has only asked to add details without giving any specifics yet, do NOT call this tool — ask for the details first, and never invent names or values.",
    "parameters": {
        "type": "object",
        "properties": {
            "policy_type": {
                "type": "string",
                "enum": [
                    "level_term",
                    "term",
                    "whole_of_life",
                    "decreasing_term",
                    "family_income_benefit",
                    "standalone_ci",
                    "accelerated_ci",
                    "income_protection"
                ],
                "description": "Type of policy. \"level_term\" for level term life insurance, \"term\" for generic term life, \"whole_of_life\" for whole of life, \"decreasing_term\" for decreasing term (e.g., mortgage protection), \"family_income_benefit\" for family income benefit, \"standalone_ci\" for standalone critical illness, \"accelerated_ci\" for accelerated critical illness (combined with life cover), \"income_protection\" for income protection."
            },
            "provider": {
                "type": "string",
                "description": "Insurance provider (e.g., \"Aviva\", \"Legal & General\")"
            },
            "sum_assured": {
                "type": "number",
                "description": "Sum assured / cover amount in pounds. For life and critical illness policies."
            },
            "benefit_amount": {
                "type": "number",
                "description": "Monthly benefit amount in pounds. For income protection policies only."
            },
            "premium_amount": {
                "type": "number",
                "description": "Premium amount in pounds"
            },
            "premium_frequency": {
                "type": "string",
                "enum": [
                    "monthly",
                    "annually"
                ],
                "description": "How often premiums are paid. Default \"monthly\"."
            },
            "policy_term_years": {
                "type": "integer",
                "description": "Policy term in years (not applicable for whole of life)"
            },
            "policy_start_date": {
                "type": "string",
                "description": "Policy start date. Pass the user-supplied phrase verbatim (e.g. \"today\", \"26 April 2026\", \"last Monday\") — the server parses it deterministically."
            },
            "in_trust": {
                "type": "boolean",
                "description": "Whether the policy is written in trust for Inheritance Tax planning. Default false."
            }
        },
        "required": [
            "policy_type"
        ],
        "additionalProperties": false
    }
}
```
