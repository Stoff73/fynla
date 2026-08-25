# Subscription & Checkout Update

**Released:** 23 April 2026
**Applies to:** Fynla web (fynla.org)

We've rolled out a set of improvements to subscription, checkout, and plan selection. These changes address a small number of frustrating edge cases around trial renewal and clean up how plans are presented across the app and on the pricing page.

---

## What's fixed

### Trial renewal no longer gets stuck

If your free trial had ended and you tried to subscribe, the "Your Subscription Has Expired" overlay and the plan-selection popup could both show at the same time and end up layered over the Revolut payment widget — so you'd click through but never actually reach checkout. It felt like a loop.

Now the flow is linear and predictable:

1. You log in after your trial has ended and see a single prompt: **"Your Subscription Has Expired"** with a countdown to data deletion and two clear options.
2. Click **Subscribe Now** → the plan chooser opens with a close button so you can back out if you change your mind.
3. Pick a plan → the checkout page loads with the Revolut payment widget visible and ready, no overlays in the way.

If you'd rather not subscribe, **Delete All Data & Start Again** gives you a clean exit.

---

## What's new

### Student plan now requires a UK university email

The **Student plan** is now reserved for UK university students. You'll see it in the plan chooser if — and only if — your account email ends with `.ac.uk` (e.g. `name@manchester.ac.uk`, `name@student.ox.ac.uk`). Everyone else sees Standard, Family, and Pro.

The backend enforces this too, so a shared or bookmarked Student checkout link won't let a non-student complete the purchase. The public **/pricing** page still shows all four plans so students researching options can compare openly.

### Plan descriptions are clearer

- **Standard plan card**: if the Student plan isn't on offer for you, the Standard card now lists all its features directly — no more "Everything in Student" reference to a plan you don't see.
- **Family plan card**: now highlights two benefits that were already true but weren't called out — **Parents included** and **Children for free**. Visible on the in-app plan chooser and on **/pricing**.

### One place for discount codes

The plan chooser no longer has its own "Have a discount code?" field. Discount codes live on the checkout page only — less chance of typing a code into the wrong box, and the checkout page is already where we validate and apply them.

---

## What hasn't changed

- Pricing is unchanged. Launch pricing (for the first 500 subscribers) is still active.
- Existing subscribers keep their subscription and renewal schedule as-is.
- The 30-day data retention period after a trial or subscription ends is unchanged.
- Auto-renewal and invoice downloads from your profile are unchanged.
- The public **/pricing** page still shows all four plans for marketing purposes.

---

## Reporting issues

If anything subscription-related worked for you before today and doesn't now, please tell us:

- In-app: click the **Bug** button in the navbar.
- Email: the support address on the **Help** page.

Include the time you noticed it and which plan you were on — that helps us line it up with the deploy.

---

*Thanks for using Fynla.*
