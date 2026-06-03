---
procedure_id: 'billing.tool.list_invoices'
kind: tool_schema
module: billing
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "list_invoices",
    "description": "List the user's invoices in reverse chronological order (most recent first). Each row includes the invoice number, issued date, amount in pounds, currency, status, plan name, billing cycle, and a PDF download URL. Use when the user asks for their billing history, past invoices, or wants to download a receipt.",
    "parameters": {
        "type": "object",
        "properties": {},
        "required": [],
        "additionalProperties": false
    }
}
```
