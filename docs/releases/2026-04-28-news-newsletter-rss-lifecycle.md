# Release notes — 28 April 2026

**Theme:** News hub, newsletter signup, RSS feeds, and lifecycle emails

This release adds Fynla's first proper way to follow announcements without signing up for an account, plus a long-overdue email layer that talks to people through the trial/subscribe lifecycle. Everything below is now live on the staging environment (`csjones.co/fynla`) and will roll to production after a soak window.

---

## What's new for visitors

### A News page at `/news`

- Public landing page that lists every Fynla announcement, newest first.
- Featured hero card for the latest article, with three-up cards for everything else.
- Each article opens at `/news/{slug}` with full markdown content, author byline, and published date.
- Pagination kicks in once there's more than one page of articles — invisible until then.

### Email signup for announcements

- Subscribe banner on `/news` — drop your email, hit Subscribe, and we send a confirmation email.
- Click the link in the confirmation email and you're on the list. No Fynla account required.
- A welcome email confirms the subscription and includes an Unsubscribe link in the footer of every newsletter.
- Unsubscribing is a one-click action — no login, no "are you sure" cycle.

### Confirmation modal (instead of a separate page)

When you click the Confirm or Unsubscribe link in our emails, we now drop you back on the news hub and pop a small confirmation modal on top:

- **You're subscribed** — first confirmation, raspberry header.
- **You're already subscribed** — if you click the confirm link a second time, we acknowledge it and don't double-add you.
- **You've unsubscribed** — clean confirmation with a "sign up again from the news page" prompt, horizon-blue header.

Closing the modal returns you to the news hub with a clean URL.

### RSS feeds for power users

Two public feeds, both XML, both standards-compliant:

- `https://fynla.org/feed/news.xml` — all Fynla announcements
- `https://fynla.org/feed/insights.xml` — published insights articles

Drop either URL into Feedly, NetNewsWire, or any reader and you'll get our updates the moment they go live.

### Stay-updated CTA

If you'd rather get our announcements in-app (and unlock everything else Fynla does), the news hub still has a "Register for free" call to action at the bottom of the page.

---

## What's new for trial and subscribed users

A full lifecycle email system, replacing the previous ad-hoc one-off welcome message. Each email follows our visual brand and is built from a shared layout — they look and feel like one product, not eleven.

| Stage | Email | Purpose |
|---|---|---|
| Trial day 1 | Get started | Welcome + first-five-things checklist |
| Trial day 7 | Insights | Personal insights generated from your data |
| Trial day 21 | Countdown | Trial-ending warning with extension options |
| Trial day 28 | End of trial | Trial expired, here's what subscribing unlocks |
| Trial day 35 | Don't miss out | Reminder + limited-time discount |
| Trial day 60 | Subscribe (max discount) | Final discount push before lapsing |
| New subscriber | Welcome | Subscription confirmed, here's what's available |
| Subscriber day 7 | Great job | Engagement check-in with usage tips |
| Subscriber day 30 | Well done | One-month milestone |
| Inactive 30 days | We haven't seen you | Re-engagement |
| Subscribe in progress | Subscribe in progress | Receipts and next steps after upgrade |

All email links land on signed-token routes — no risk of the wrong account ending up in the wrong inbox if the link is forwarded.

---

## What's new for admins

A new admin page at `/admin/news-subscribers`:

- Sortable table of every newsletter subscriber, with status (pending, confirmed, unsubscribed) and source (which page they signed up from).
- Filter chips for status, search by email, and pagination.
- One-click CSV export of the entire subscriber list — for sending broadcasts via your existing email-marketing tool.
- Rate-limited (3 exports per hour) to prevent accidental download loops.

---

## What changed under the hood

Visible to no one, but worth noting:

- Two new database tables: `news_articles` and `news_subscribers`. Both have been live on staging since today.
- A new `marketing@fynla.org` from-address for newsletter and lifecycle email sends, separate from the `support@fynla.org` transactional from-address.
- The newsletter confirm and unsubscribe URLs no longer render dedicated Blade pages — the controller redirects to `/news?subscribed=1` (or `=already`, `unsubscribed=1`) and the news hub opens the modal client-side.
- Public news API at `/api/news` and `/api/news/{slug}` — JSON payload for any future mobile / third-party use.
- Admin API at `/api/admin/news-subscribers` and `/api/admin/news-subscribers/export`.

---

## Privacy and compliance

- Subscriber emails are stored hashed only in the URL token, not in cookies or local storage. The 48-character random token IS the secret — there's no separate password to remember or reset.
- Unsubscribe is honoured immediately and recorded with a timestamp. Re-subscribing later is a fresh confirmation flow — we never silently re-add a previously-unsubscribed address.
- Subscribers who are already registered Fynla users are politely redirected to sign in instead of being added to the list — your in-app preferences are the source of truth, not the public newsletter list.

---

## Known gaps to close in the next sprint

- No public broadcast UI yet — admins still send via the CSV export and an external tool. A "compose and send to all confirmed subscribers" admin screen is on the backlog.
- The signup banner only appears on the `/news` page. Adding it to the homepage and the bottom of insights articles is next.
- We have lifecycle template Blade files for all eleven stages; the cron schedules that fire them are still being audited before we turn them on for production.

---

## Where to test

| Environment | URL | Status |
|---|---|---|
| Staging | https://csjones.co/fynla | Live as of this release |
| Production | https://fynla.org | Pending the dev → main release PR |

Smoke-test recipe is in `April/April28Updates/deployNewsletter.md` §7.
