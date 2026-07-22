---
procedure_id: 'navigation.tool.navigate_to_page'
kind: tool_schema
module: navigation
provider: xai
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
                "description": "The application route path. Valid routes: MAIN: /dashboard, /profile, /settings, /settings/security, /settings/assumptions, /help. INCOME & EXPENDITURE: /valuable-info?section=income (Income tab — view and edit income sources), /valuable-info?section=expenditure (Expenditure tab — view and edit monthly spending), /valuable-info?section=letter (Expression of Wishes / Letter to Spouse), /valuable-info?section=risk (Risk Profile summary). NET WORTH: /net-worth/wealth-summary (overall wealth), /net-worth/property (properties and mortgages), /net-worth/investments (investment accounts list), /net-worth/retirement (pensions), /net-worth/cash (Bank Accounts & Savings), /net-worth/business (business interests), /net-worth/chattels (personal valuables), /net-worth/liabilities (debts). INVESTMENT DETAILS: To show a specific account's details (Monte Carlo, tax treatment, rebalancing, diversification, fees, holdings), navigate to /net-worth/investments — the user clicks into any account card to see its full detail view with per-account projections. Do NOT use /net-worth/investment-detail (that is a legacy portfolio-wide view). Other detail pages: /net-worth/fees-detail (fees breakdown), /net-worth/holdings-detail (portfolio holdings), /net-worth/tax-efficiency (tax efficiency analysis), /net-worth/strategy-detail (investment strategy). SAVINGS DETAIL: /savings (savings dashboard with analysis), /savings/account/{id} (individual savings account detail). PROTECTION: /protection (protection dashboard with coverage analysis and policies). ESTATE: /estate (Estate Planning dashboard — Inheritance Tax, gifting, will status), /estate/will-builder (Will Builder), /estate/power-of-attorney (Power of Attorney). TRUSTS: /trusts (trust list and management). GOALS & EVENTS: /goals (Goals dashboard — all goals with progress tracking), /goals?tab=events (Life Events tab — upcoming life events). RISK: /risk-profile (risk questionnaire and profile). PLANS: /plans (all plans overview), /plans/investment (investment plan), /plans/retirement (retirement plan), /plans/protection (protection plan), /plans/estate (estate plan), /holistic-plan (Holistic Financial Plan combining all modules). ACTIONS: /actions (recommended actions across all modules). PLANNING: /planning/journeys (guided planning journeys), /planning/what-if (What-If Scenarios). NEVER use /savings or /investment as standalone paths for net worth — use /net-worth/cash and /net-worth/investments instead. /savings is valid for the savings dashboard."
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
    },
    "strict": true
}
```
