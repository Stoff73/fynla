---
procedure_id: 'analysis.tool.list_records'
kind: tool_schema
module: analysis
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "list_records",
    "description": "List existing records of a given type with IDs, key details, balances, interest rates, and values. Use this BEFORE calling update_record to find the correct entity_id. Use this for factual questions about the user's accounts — balances, interest rates, providers, policy details. For example: \"how much interest will I earn?\" → list_records(savings_account). \"What pensions do I have?\" → list_records(dc_pension). Returns raw data; for full module analysis use get_module_analysis instead.",
    "parameters": {
        "type": "object",
        "properties": {
            "entity_type": {
                "type": "string",
                "enum": [
                    "savings_account",
                    "investment_account",
                    "dc_pension",
                    "db_pension",
                    "property",
                    "mortgage",
                    "life_insurance",
                    "critical_illness",
                    "income_protection",
                    "trust",
                    "business_interest",
                    "chattel",
                    "estate_liability",
                    "estate_gift",
                    "family_member"
                ],
                "description": "The type of record to list."
            }
        },
        "required": [
            "entity_type"
        ],
        "additionalProperties": false
    }
}
```
