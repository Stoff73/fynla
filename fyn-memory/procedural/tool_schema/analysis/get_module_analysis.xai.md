---
procedure_id: 'analysis.tool.get_module_analysis'
kind: tool_schema
module: analysis
provider: xai
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "get_module_analysis",
    "description": "Run a comprehensive financial analysis for a module — returns recommendations, gaps, capacity assessments, and projections. Only use this when the user needs analysis, advice, or recommendations (e.g. \"am I saving enough?\", \"analyse my estate\", \"what should I do about my pension?\"). Do NOT use for simple data lookups like account balances, interest rates, or tax allowances — use list_records or get_tax_information instead.",
    "parameters": {
        "type": "object",
        "properties": {
            "module": {
                "type": "string",
                "enum": [
                    "protection",
                    "savings",
                    "investment",
                    "retirement",
                    "estate",
                    "goals",
                    "holistic"
                ],
                "description": "The financial planning module to analyse"
            }
        },
        "required": [
            "module"
        ],
        "additionalProperties": false
    },
    "strict": true
}
```
