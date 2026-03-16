# Patch Notes: Fynla Brain - Intelligent Financial Guidance

**Version:** v0.9.2
**Date:** 16 March 2026

---

## Fynla Brain: Intelligent Financial Guidance

Major upgrade to Fyn, your financial assistant — now powered by the Anthropic SDK with a new prerequisite system that ensures every piece of guidance is backed by your actual data. Fyn will never give vague or misleading advice when information is missing.

### What's New

- **Prerequisite Gates** — Fyn now checks your data before giving advice. If information is missing, you will see exactly what is needed, why it matters, and Fyn will navigate you directly to the right page to add it.

- **Richer Analysis** — Fyn now uses 100% of the cross-module analysis when responding — including decision traces, cashflow allocation, conflict resolution, and cross-module strategies. Responses reference your specific figures, not generalities.

- **Smarter Navigation** — Fyn can now navigate you to 26 different pages across the application — including income, expenditure, will builder, power of attorney, plans, actions, and what-if scenarios.

- **Anthropic SDK Integration** — Upgraded from raw API calls to the official Anthropic PHP SDK for more reliable streaming, better error handling, and prompt caching for faster responses.

- **Simplified Architecture** — Consolidated from 8 separate service files into a single, streamlined system. Both preview and registered users now use the same conversation engine.

- **User-Driven Conversations** — Quick reply suggestion chips have been removed. Conversations are now entirely driven by you, with Fyn responding to your specific questions.
