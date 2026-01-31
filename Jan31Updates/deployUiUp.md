# Deployment Notes - January 31, 2026 (uiUp Branch)

**Branch:** uiUp

**Deployment Status:** Ready for deployment

**Rebuild Required:** YES - Frontend build required

---

## Persona Selection Modal - Add Register Button

Added a register button to the persona selection modal on the landing page, encouraging users to explore the demo personas before creating an account.

### Changes Made

| Change | Description |
|--------|-------------|
| Register section | Added new section below persona grid with register button |
| Encouragement message | "We strongly encourage you to explore the personas above first to see what Fynla can do." |
| Register button | "Create Your Account" button that closes modal and navigates to /register |

### User Flow
1. User clicks "Try Demo" on landing page
2. Persona selection modal opens
3. User sees personas to explore
4. Below personas, user sees encouragement message and register button
5. Clicking register closes modal and navigates to registration page

### Files Changed
```
resources/js/components/Preview/PersonaSelectionModal.vue
```

---

## Persona Card Order - Reorder in Selection Modal

Reordered the persona cards in the selection modal to display in a more logical order.

### New Order
1. **Carters** (young_family) - James & Emily Carter
2. **Mitchells** (peak_earners) - David & Sarah Mitchell
3. **Chen** (entrepreneur) - Alex Chen
4. **Morgan** (young_saver) - John Morgan
5. **Williams** (retired_couple) - Robert & Patricia Williams
6. **Thompson** (widow) - Margaret Thompson

### Files Changed
```
resources/js/store/modules/preview.js
```

---

## Preview Banner - Change Register Button Text

Changed the register button text in the demo preview banner from "Register to Save Your Data" to "Signup Now".

### Files Changed
```
resources/js/components/Preview/PreviewBanner.vue
```

---

## Deployment Steps

### 1. Build Frontend (Required)
```bash
./deploy/fynla-org/build.sh
```

### 2. Upload to Server

**Upload the entire build directory:**
```
public/build/
```

### 3. Clear Caches (SSH)
```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear
```

---

## Testing Checklist

- [ ] Landing page - click "Try Demo" and verify register button appears in modal
- [ ] Verify encouragement message displays correctly
- [ ] Click register button - verify modal closes and navigates to /register
- [ ] Verify persona cards display in correct order: Carters, Mitchells, Chen, Morgan, Williams, Thompson
- [ ] In demo mode, verify preview banner button says "Signup Now"
