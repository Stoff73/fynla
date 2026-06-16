---
procedure_id: 'campaign.tool.capture_charitable_giving'
kind: tool_schema
module: campaign
provider: xai
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "capture_charitable_giving",
    "description": "Capture the user's annual charitable donations covered by Gift Aid. Used by the Gift Aid Higher-Rate Relief strategy to compute the personal tax relief the user can reclaim via Self Assessment when they donate at the higher or additional rate.",
    "parameters": {
        "type": "object",
        "properties": {
            "annual_donations": {
                "type": "number",
                "description": "Total annual Gift-Aid-eligible donations in pounds."
            }
        },
        "required": [
            "annual_donations"
        ],
        "additionalProperties": false
    },
    "strict": true
}
```
