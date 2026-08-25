# Deploy Guide — Hide Fyn CTA + Fix aiChat State Leak

**DEPLOYED** 9 April 2026

**Date:** 9 April 2026
**Branch:** `fynStart`
**PR:** #204

---

## Summary

- Hidden "Quick start with Fyn" CTA on landing page until new-user Fyn flow is fixed
- Fixed aiChat Vuex state leak — conversations from a prior user session could carry over to a new login/registration
- Removed Feedback nav link, Wishlist links, Beta warning (from earlier commit on this branch)

---

## Files to Upload

### Frontend Build (required)

```
public/build/ --> ~/www/fynla.org/public_html/public/build/
```

No PHP changes. No migrations. No server-side files.

---

## SSH Commands (post-upload)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear
```

---

## Notes

- The Fyn Quick Start flow has underlying issues (see `fynQuickStartBugs.md`) — CTA hidden until fixed
- aiChat state leak: Login.vue and Register.vue bypass auth store actions (call API directly), so `aiChat/reset` was never firing on login/register. Now added to all 4 completion paths.
