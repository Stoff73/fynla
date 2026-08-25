# What's New in Fynla — 23 April 2026

A major update rolled out to `fynla.org` tonight, bundling several weeks of improvements across Investments, Pensions, Net Worth, and the overall navigation. A handful of long-running issues are fixed at the root, and a few screens have been redesigned to work better for households.

Everything in this release was tested on the development site first, then shipped to production together.

---

## What's fixed

### Your Investment page loads properly

Some customers were seeing errors in parts of the Investment module where the **Tax-Loss Harvesting** analysis is calculated. Behind the scenes, the analysis was crashing before it could return any data. That's fixed — the Investment dashboard now loads cleanly, the harvesting opportunities panel populates, and the projection charts render from the first click.

### Your pension projection works from the moment you add a pension

Previously, if you added your first pension — either manually or through Fyn — the dashboard would show the pension's value correctly, but the projected-growth chart stayed at £0. You'd see your pot but the "Projected Value" card and Monte Carlo bands sat flat at zero.

Fixed at the root. The projection now updates as soon as you add or change a pension, and the chart shows realistic probability bands from day one.

### Investment account projection loads

Clicking into an individual investment account used to show "Failed to load projection data" where the projection chart should be. The chart now loads correctly with current value, projected value at 80% confidence, and 5 / 10 / 20 / 30-year probability bands.

### Logout is instant

Clicking **Log Out** used to pop a success modal that held you on the dashboard until you dismissed it. Logging out now takes you straight to the sign-in screen — no extra click.

### Partner and spouse names render consistently

A recurring bug had "Partner" or "Spouse" appearing in places where your partner's actual name should have been (on donut chart titles, beneficiary labels, the Letter to Spouse page, and so on). The root cause has been fixed — your spouse's name now comes from a single reliable source across the whole app.

### Browser tab always reads "Fynla"

The browser tab label sometimes got stuck as "Sign In — Fynla" even after you'd signed in. It now reads "Fynla" on every page, every time.

---

## What's new

### Net Worth redesigned for households

If you're married or have a linked partner account, the **Net Worth → Wealth Summary** page now puts you and your partner side-by-side:

- Two per-person allocation donuts inline at the top (instead of one on top of the other)
- A full-width **Assets vs Liabilities** bar chart underneath that hovers to show the split between the two of you for each category (e.g. "Pensions: £405,000 — you £230,000 / partner £175,000")
- Single users are unchanged

This makes it much easier to see how the household looks together without losing who owns what.

### Simpler Add Pension form

When you click **Add Pension** you now go straight to a single form instead of picking between three tiles first. The pension type is the first dropdown and covers everything:

- Occupational (Workplace)
- Self-Invested Personal Pension (SIPP)
- Personal Pension
- Stakeholder Pension
- Final Salary (Defined Benefit)
- State Pension

The form shows the right fields for whichever type you pick. One less click and the same flow regardless of pension type.

### Pension and Investment forms — cleaner default view

The Add / Edit forms for pensions and investment accounts had grown a lot of optional fields (fees, expected return, lump sum plans, beneficiaries, platform fees, holdings). All of that now lives behind a single **"Additional information"** toggle at the bottom of the form. The common fields stay visible; advanced fields are a click away.

If you're editing an account that already has those optional fields filled in, the section auto-expands so nothing is hidden.

### A calmer, more consistent navigation

- **Top nav is pinned** as you scroll. No more scrolling back up to reach the menu.
- **The duplicated tabs row is gone.** Each module's sub-navigation now lives only in the left sidebar, removing a redundant second layer.
- **Add / Upload buttons are next to the data they act on.** On Retirement and Investments they sit under the account list next to the projection chart. On Property, Liabilities, Personal Valuables, Business, Trusts and Goals they're at the top-right of each list.

### Progress overview on the dashboard for everyone

The dashboard "Good evening, [name]" section at the top now shows your **Scenario Completeness**, **Profile Completeness**, and **Recommended Actions** for every user — not just users who signed up via a specific journey. Customers who skipped to the dashboard or onboarded through Fyn AI were seeing a blank top of page; you now see the same overview as everyone else.

### Quick Start with Fyn on the landing page

The **"Quick start with Fyn"** call-to-action is now visible on the public landing page for visitors who'd rather set up their account via conversation with Fyn than a traditional form.

---

## Behind the scenes

A few improvements you won't see directly but that make the app faster and more reliable:

- **The platform is paced for our email provider.** Our lifecycle email system (welcome-back emails, renewal reminders, re-engagement nudges) now sends at a steady rate instead of all at once. This prevents a handful of customers from missing their email when a daily batch is larger than the email provider's burst limit.
- **Image processing pipeline updated** for compatibility with the production server configuration.
- **Database indexes added** to keep subscription queries fast as the customer base grows.
- **Extra tests added** so these kinds of issues are caught before they reach production.

---

## Nothing has changed about your data

Your pensions, investments, properties, goals, protection policies, and every other record are stored and displayed exactly as before. This release is a mix of bug fixes, UI improvements, and internal reliability work — no records have been moved, renamed, or deleted, and no data migration was required on your end.

---

## If something still looks odd

- **Pension projection still £0 after this update:** open the pension card, make any small edit and save — that triggers a fresh recalculation. It will sort itself out within 24 hours on its own regardless.
- **Investment dashboard error:** a single hard refresh of your browser will pick up the new version. If you use Incognito/Private windows, close the tab and open a new one.
- **Anything else:** use the Fyn chat in the bottom right of any page.

---

_Questions or feedback? Reply to this email or open the Fyn chat from inside Fynla._
