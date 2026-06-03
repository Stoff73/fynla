---
procedure_id: 'estate.tool.create_power_of_attorney'
kind: tool_schema
module: estate
provider: xai
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "create_power_of_attorney",
    "description": "Record a Lasting Power of Attorney. UK has two types: Property & Financial Affairs and Health & Welfare. You MAY call this tool multiple times in the same turn — if the user has BOTH a property_financial AND a health_welfare LPA, call create_power_of_attorney TWICE in your first response.\n\nSTATUS IS MANDATORY — extract it from the user's wording:\n  • \"registered\", \"in force\", \"active with OPG\", \"registered with the Office of the Public Guardian\" → status = \"registered\"\n  • \"draft\", \"signed but not registered\", \"not yet registered\", \"sent off for registration\", \"being registered\", \"pending\" → status = \"draft\"\n  • No signal at all → default to \"draft\"\n\nNEVER drop status=registered when the user said so. Example:\n  User: \"I have a registered property and financial LPA with my brother Tom\"\n  → create_power_of_attorney(lpa_type='property_financial', primary_attorney_name='Tom', status='registered').",
    "parameters": {
        "type": "object",
        "properties": {
            "lpa_type": {
                "type": "string",
                "enum": [
                    "property_financial",
                    "health_welfare"
                ],
                "description": "LPA type."
            },
            "primary_attorney_name": {
                "type": "string",
                "description": "Full name of the primary attorney."
            },
            "replacement_attorney_name": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Optional replacement attorney."
            },
            "status": {
                "type": [
                    "string",
                    "null"
                ],
                "enum": [
                    "draft",
                    "registered",
                    null
                ],
                "description": "LPA status. If user says \"registered\" / \"in force\" / \"active with OPG\" → \"registered\". If user says \"draft\" / \"not registered\" / \"pending\" / \"being registered\" → \"draft\". Default \"draft\" if not stated."
            },
            "opg_reference": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Office of the Public Guardian reference, if registered."
            }
        },
        "required": [
            "lpa_type",
            "primary_attorney_name",
            "replacement_attorney_name",
            "status",
            "opg_reference"
        ],
        "additionalProperties": false
    },
    "strict": true
}
```
