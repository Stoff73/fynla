---
procedure_id: 'protection.tool.create_protection_policy'
kind: tool_schema
module: protection
provider: xai
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "create_protection_policy",
    "description": "Create a protection insurance policy. Handles life insurance, critical illness, and income protection. Call this tool IMMEDIATELY. You MAY call this tool multiple times in the same turn when the user mentions multiple policies (e.g. \"Aviva life insurance £300k and Vitality critical illness £100k\" → two tool calls).",
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
                "description": "Type of policy. \"level_term\" for level term life. \"term\" for generic term life. \"whole_of_life\" for whole of life. \"decreasing_term\" for decreasing/mortgage protection. \"family_income_benefit\" for family income benefit. \"standalone_ci\" for standalone critical illness. \"accelerated_ci\" for accelerated critical illness. \"income_protection\" for income protection."
            },
            "provider": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Insurance provider (e.g. \"Aviva\", \"Legal & General\")."
            },
            "sum_assured": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Lump sum cover amount (£). For life insurance and critical illness policies. NOT for income protection or family income benefit."
            },
            "benefit_amount": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Monthly benefit amount (£). For income_protection AND family_income_benefit only."
            },
            "premium_amount": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Premium amount (£)."
            },
            "premium_frequency": {
                "anyOf": [
                    {
                        "type": "string",
                        "enum": [
                            "monthly",
                            "annually"
                        ]
                    },
                    {
                        "type": "null"
                    }
                ],
                "description": "How often premiums are paid. Default \"monthly\"."
            },
            "policy_term_years": {
                "type": [
                    "integer",
                    "null"
                ],
                "description": "Policy term in years (not for whole of life)."
            },
            "policy_start_date": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Policy start date. Pass the user-supplied phrase verbatim (e.g. \"today\", \"26 April 2026\", \"last Monday\") — the server parses it deterministically."
            },
            "in_trust": {
                "type": [
                    "boolean",
                    "null"
                ],
                "description": "Whether written in trust for IHT. Default false."
            }
        },
        "required": [
            "policy_type",
            "provider",
            "sum_assured",
            "benefit_amount",
            "premium_amount",
            "premium_frequency",
            "policy_term_years",
            "policy_start_date",
            "in_trust"
        ],
        "additionalProperties": false
    },
    "strict": true
}
```
