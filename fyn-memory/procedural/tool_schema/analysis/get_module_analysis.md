---
procedure_id: 'analysis.tool.get_module_analysis'
kind: tool_schema
module: analysis
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "get_module_analysis",
    "description": "Get detailed financial analysis for a specific module. Returns personalised analysis based on the user's actual financial data.",
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
    }
}
```
