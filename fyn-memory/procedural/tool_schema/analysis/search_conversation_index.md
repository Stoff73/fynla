---
procedure_id: 'analysis.tool.search_conversation_index'
kind: tool_schema
module: analysis
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "search_conversation_index",
    "description": "Search the user's prior conversations for context on a topic or entity. Returns up to 10 prior conversations matching the supplied keywords/entity types, ordered by recency. Use ONLY when the `<known_facts>` block is silent on the field you need and you need to know what the user has discussed in earlier sessions (e.g. they say \"as we talked about last time\" — search for the relevant topic to recover the thread). Do NOT use this as a substitute for list_records or get_module_analysis — those return current authoritative data; this returns historical conversational context.",
    "parameters": {
        "type": "object",
        "properties": {
            "topic_keywords": {
                "type": "array",
                "items": {
                    "type": "string"
                },
                "description": "Module-level topic tags to match against the conversation index `topics` field. Allowed values: protection, savings, investment, retirement, estate_planning, goals_life_events, tax_optimisation, family, property, mortgage, billing, general."
            },
            "entity_types": {
                "type": "array",
                "items": {
                    "type": "string"
                },
                "description": "Entity types to match against the `entities_mentioned` field. Allowed values: life_insurance_policy, dc_pension, db_pension, isa, gia, savings_account, property, mortgage, credit_card, family_member, goal, life_event, will, trust, business_interest, chattel."
            }
        },
        "required": [],
        "additionalProperties": false
    }
}
```
