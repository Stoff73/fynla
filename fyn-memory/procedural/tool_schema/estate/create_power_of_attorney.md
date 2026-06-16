---
procedure_id: 'estate.tool.create_power_of_attorney'
kind: tool_schema
module: estate
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "create_power_of_attorney",
    "description": "Record a Lasting Power of Attorney (LPA) the user already has in place. UK has two types: Property & Financial Affairs (lpa_type=property_financial) and Health & Welfare (lpa_type=health_welfare). For each, capture the primary attorney name. Replacement attorneys are optional. You MAY call this tool multiple times in the same turn — for example if the user has BOTH a property_financial AND a health_welfare LPA, call create_power_of_attorney TWICE in your first response.\n\nSTATUS IS MANDATORY — extract it from the user's wording:\n  • \"registered\", \"in force\", \"active with OPG\", \"already registered with the Office of the Public Guardian\" → status = \"registered\"\n  • \"draft\", \"signed but not registered\", \"not yet registered\", \"in the pipeline\", \"sent off for registration\", \"being registered\", \"pending registration\" → status = \"draft\"\n  • If the user gives no signal at all, default to \"draft\".\n\nNEVER drop status=registered when the user said so. Worked example:\n  User: \"I have a registered property and financial LPA with my brother Tom\"\n  Required: create_power_of_attorney(lpa_type='property_financial', primary_attorney_name='Tom', status='registered').",
    "parameters": {
        "type": "object",
        "properties": {
            "lpa_type": {
                "type": "string",
                "enum": [
                    "property_financial",
                    "health_welfare"
                ],
                "description": "Which LPA type. property_financial covers money/property decisions. health_welfare covers medical/care decisions."
            },
            "primary_attorney_name": {
                "type": "string",
                "description": "Full name of the primary attorney (the person empowered to act for the donor)."
            },
            "replacement_attorney_name": {
                "type": "string",
                "description": "Optional. Full name of a replacement attorney who steps in if the primary is unable or unwilling to act."
            },
            "status": {
                "type": "string",
                "enum": [
                    "draft",
                    "registered"
                ],
                "description": "LPA status. If user says \"registered\" / \"in force\" / \"active with OPG\" → \"registered\". If user says \"draft\" / \"not registered\" / \"pending\" / \"being registered\" → \"draft\". Default \"draft\" if not stated."
            },
            "opg_reference": {
                "type": "string",
                "description": "Office of the Public Guardian registration reference, if the LPA is registered."
            }
        },
        "required": [
            "lpa_type",
            "primary_attorney_name"
        ],
        "additionalProperties": false
    }
}
```
