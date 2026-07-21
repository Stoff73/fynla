---
procedure_id: 'estate.tool.create_trust'
kind: tool_schema
module: estate
provider: xai
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "create_trust",
    "description": "Record a trust for estate planning. Use for discretionary trusts, bare trusts, life insurance trusts, loan trusts, discounted gift trusts, interest in possession trusts, and other UK trust types. Call this tool IMMEDIATELY. You MAY call this tool multiple times in the same turn when the user mentions multiple trusts. If the user has only asked to add details without giving any specifics yet, do NOT call this tool — ask for the details first, and never invent names or values.",
    "parameters": {
        "type": "object",
        "properties": {
            "trust_name": {
                "type": "string",
                "description": "Name of the trust (e.g. \"Smith Family Discretionary Trust\")"
            },
            "trust_type": {
                "type": "string",
                "enum": [
                    "discretionary",
                    "bare",
                    "interest_in_possession",
                    "life_insurance",
                    "loan",
                    "discounted_gift",
                    "accumulation_maintenance",
                    "mixed",
                    "settlor_interested"
                ],
                "description": "Type of trust. \"discretionary\" for family discretionary trusts. \"bare\" for bare/absolute trusts. \"interest_in_possession\" for life interest trusts. \"life_insurance\" for trusts holding life policies. \"loan\" for loan trusts. \"discounted_gift\" for DGTs. \"accumulation_maintenance\" for A&M trusts. \"mixed\" for combined trust types. \"settlor_interested\" when settlor/spouse can benefit."
            },
            "initial_value": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Amount originally settled into the trust (£)"
            },
            "current_value": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Current value of assets in trust (£)"
            },
            "trust_creation_date": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Date trust was established (YYYY-MM-DD)"
            },
            "beneficiaries": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Comma-separated list of beneficiaries (e.g. \"James Smith, Emily Smith\")"
            },
            "trustees": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Comma-separated list of trustees (e.g. \"John Smith, ABC Trustee Services Ltd\")"
            },
            "purpose": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Purpose of the trust (e.g. \"Estate planning and IHT mitigation\")"
            }
        },
        "required": [
            "trust_name",
            "trust_type",
            "initial_value",
            "current_value",
            "trust_creation_date",
            "beneficiaries",
            "trustees",
            "purpose"
        ],
        "additionalProperties": false
    },
    "strict": true
}
```
