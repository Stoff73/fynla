---
procedure_id: 'navigation.tool.navigate_to_page'
kind: tool_schema
module: navigation
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
    "name": "navigate_to_page",
    "description": "Navigate the user to a specific page in the application. Use this when the user asks to go somewhere or when showing them relevant information would be helpful.",
    "parameters": {
        "type": "object",
        "properties": {
            "route_path": {
                "type": "string",
                "description": "The application route path. Valid routes: MAIN: /dashboard, /profile, /settings, /settings/security, /settings/assumptions, /help. INCOME & EXPENDITURE: /valuable-info?section=income (Income tab), /valuable-info?section=expenditure (Expenditure tab), /valuable-info?section=letter (Letter to Spouse tab), /valuable-info?section=risk (Risk Profile summary tab). NET WORTH: /net-worth/wealth-summary, /net-worth/property, /net-worth/investments, /net-worth/retirement, /net-worth/cash (Bank Accounts & Savings), /net-worth/business, /net-worth/chattels, /net-worth/liabilities. PROTECTION: /protection. ESTATE: /estate (Estate Planning dashboard), /estate/will-builder (Will Builder), /estate/power-of-attorney (Power of Attorney). TRUSTS: /trusts. GOALS: /goals (Goals & Life Events), /goals?tab=events (Life Events tab). RISK: /risk-profile. PLANS: /plans (all plans), /plans/investment, /plans/retirement, /plans/protection, /plans/estate, /holistic-plan (Holistic Financial Plan). ACTIONS: /actions. PLANNING: /planning/journeys, /planning/what-if (What-If Scenarios). NEVER use /savings or /investment — these are legacy redirects. Use /net-worth/cash and /net-worth/investments instead."
            },
            "description": {
                "type": "string",
                "description": "Brief explanation of why navigating here is helpful"
            }
        },
        "required": [
            "route_path",
            "description"
        ],
        "additionalProperties": false
    }
}
```
