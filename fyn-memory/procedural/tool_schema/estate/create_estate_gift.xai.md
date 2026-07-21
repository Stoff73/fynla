---
procedure_id: 'estate.tool.create_estate_gift'
kind: tool_schema
module: estate
provider: xai
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "create_estate_gift",
    "description": "Record a gift for Inheritance Tax planning (7-year rule). Use when the user mentions gifts they have made to family, friends, trusts, or charities. Call this tool IMMEDIATELY. You MAY call this tool multiple times in the same turn when the user mentions multiple gifts. If the user has only asked to add details without giving any specifics yet, do NOT call this tool — ask for the details first, and never invent names or values.",
    "parameters": {
        "type": "object",
        "properties": {
            "gift_date": {
                "type": "string",
                "description": "Date the gift was made (YYYY-MM-DD). Must be in the past. If user says \"last Christmas\" calculate the date. If user says \"3 years ago\" calculate from today."
            },
            "recipient": {
                "type": "string",
                "description": "Full name of the recipient (e.g. \"Emma Smith\", \"Oxfam\", \"Smith Family Trust\"). Use the person's actual name, not \"my daughter\" or \"my son\"."
            },
            "gift_type": {
                "type": "string",
                "enum": [
                    "pet",
                    "clt",
                    "exempt",
                    "small_gift",
                    "annual_exemption"
                ],
                "description": "\"pet\" for Potentially Exempt Transfer — most common, gifts to individuals (becomes tax-free after 7 years). \"clt\" for Chargeable Lifetime Transfer — gifts to trusts or companies (immediately taxable at 20%). \"exempt\" for exempt gifts — to spouse, charities, political parties, or for marriage. \"small_gift\" for Small Gift Exemption — up to £250 per person per year. \"annual_exemption\" for Annual Exemption — first £3,000 of gifts each tax year."
            },
            "gift_value": {
                "type": "number",
                "description": "Value of the gift in pounds (£)"
            },
            "notes": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Additional context about the gift (e.g. \"Cash for house deposit\", \"Wedding gift\", \"Birthday present\")"
            }
        },
        "required": [
            "gift_date",
            "recipient",
            "gift_type",
            "gift_value",
            "notes"
        ],
        "additionalProperties": false
    },
    "strict": true
}
```
