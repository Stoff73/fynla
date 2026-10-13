---
procedure_id: 'analysis.tool.search_conversation_index'
kind: tool_schema
module: analysis
provider: xai
version: 2
active: true
effective_from: 2026-10-12
---

```json
{
    "name": "search_conversation_index",
    "description": "Search the user's prior conversations for context on a topic or entity. Returns up to 10 prior conversations matching the supplied keywords/entity types, ordered by recency. Use ONLY when the <known_facts> block is silent on the field you need and you need to know what the user has discussed in earlier sessions (e.g. they say \"as we talked about last time\" — search for the relevant topic to recover the thread). Do NOT use this as a substitute for list_records or get_module_analysis — those return current authoritative data; this returns historical conversational context. With `words`, it also returns what the user wrote in THIS conversation's earlier messages that are out of your view (`this_conversation`, their exact words): use it whenever the user refers to something they told you earlier that you cannot see, and quote it.",
    "parameters": {
        "type": "object",
        "properties": {
            "topic_keywords": {
                "type": "array",
                "items": {
                    "type": "string"
                },
                "description": "Module-level topic tags to match against the conversation index topics field. Allowed values: protection, savings, investment, retirement, estate_planning, goals_life_events, tax_optimisation, family, property, mortgage, billing, general."
            },
            "words": {
                "type": "array",
                "items": {
                    "type": "string"
                },
                "description": "Words to find in this conversation's earlier messages, for example [\"bonus\"] when the user says \"the bonus I mentioned\". Returns the user's own messages that contain any of them, exactly as written."
            },
            "entity_types": {
                "type": "array",
                "items": {
                    "type": "string"
                },
                "description": "Entity types to match against the entities_mentioned field. Allowed values: life_insurance_policy, dc_pension, db_pension, isa, gia, savings_account, property, mortgage, credit_card, family_member, goal, life_event, will, trust, business_interest, chattel."
            }
        },
        "required": [],
        "additionalProperties": false
    },
    "strict": true
}
```
