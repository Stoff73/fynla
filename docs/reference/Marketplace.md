# Marketplace

[[Home]] > Marketplace

The **Fynla Marketplace** distributes Claude Code plugins, agents, and skills to the development team.

---

## Setup

```bash
# Add the marketplace (one-time)
/plugin → Add Marketplace → Stoff73/fynla

# Install plugins
/plugin install fynla-dev-skills@fynla-marketplace
/plugin install fynla-compliance@fynla-marketplace
/plugin install fynla-design@fynla-marketplace
/plugin install fynla-ops@fynla-marketplace
```

## Plugins

### fynla-dev-skills
Core development workflow skills.

| Skill | Purpose |
|-------|---------|
| systematic-debugging | 4-phase debugging framework — root cause before fixes |
| scaffold-feature | Scaffold all files for a new feature (model, controller, service, Vue, tests) |
| tech-debt-session | Audit changed files for tech debt after each session |
| tech-debt-full | Full codebase tech debt audit (weekly/monthly) |
| deploy-checklist | Generate deployment checklist with file list and SSH commands |
| cost-estimate | Estimate development cost from lines of code and complexity |
| skill-creator | Create, test, and benchmark new skills |

### fynla-compliance
Tax and security review agents.

| Agent | Purpose |
|-------|---------|
| tax-compliance-reviewer | Verify tax calculations use TaxConfigService, check HMRC compliance |
| security-reviewer | Audit auth flows, input validation, data exposure, financial data protection |

### fynla-design
UI/UX agents.

| Agent | Purpose |
|-------|---------|
| premium-ui-designer | Elevate interfaces with animations, micro-interactions, premium polish |
| ux-writing-expert | Improve error messages, empty states, button labels, microcopy |

### fynla-ops
Operations agents.

| Agent | Purpose |
|-------|---------|
| laravel-stack-deployer | Deploy Laravel + Vue.js + Vite to production/staging |
| database-optimizer | MySQL query optimisation, index design, schema scaling |
| product-manager | Transform ideas into structured product plans and user stories |

## Structure

```
.claude-plugin/marketplace.json     ← Catalog
plugins/
├── fynla-dev-skills/skills/        ← 7 skills
├── fynla-compliance/agents/        ← 2 agents
├── fynla-design/agents/            ← 2 agents
└── fynla-ops/agents/               ← 3 agents
```

## Deploy Notes

See [[March/March9Update/marketplaceDeploy]] for full setup history.
