# Deployment - 24 January 2026

## Summary
1. **NEW**: BTL Management Agent tab — Buy-to-let properties now show a "Management Agent" tab (4th tab) with agent name, company, email, phone, monthly/annual fee, and ownership share calculation. Empty state shown when no agent data exists.
2. **FIX**: Managing agent fee now included in net rental yield calculation (`PropertyService::calculateTotalMonthlyCosts()`)
3. **FIX**: Managing agent fee flows through to User Profile Expenditure tab with correct ownership multiplier
4. **NEW**: Managing agent fee editable in the Financials tab Edit Costs modal (BTL only)
5. **NEW**: Managing agent fee shown as a row in the Financials tab Monthly Costs grid (BTL only)
6. **CLEANUP**: Removed dead `PropertyDetail.vue` component and its unused `/property/:id` route — app only uses `PropertyDetailInline.vue`

---

## Frontend Rebuild Required: YES

Vue components and router were modified.

```bash
./deploy/fynla-org/build.sh
```

---

## Files Changed

```
app/Services/Property/PropertyService.php
app/Services/UserProfile/UserProfileService.php
resources/js/components/NetWorth/Property/PropertyDetailInline.vue
resources/js/components/NetWorth/Property/PropertyFinancials.vue
resources/js/components/NetWorth/Property/PropertyDetail.vue  (DELETED)
resources/js/router/index.js
```

---

## Files to Upload

### Backend (PHP)
```
app/Services/Property/PropertyService.php
app/Services/UserProfile/UserProfileService.php
```

### Frontend (after rebuild)
```
public/build/  (entire folder)
```

---

## Files to Remove from Server

```
resources/js/components/NetWorth/Property/PropertyDetail.vue
```

---

## Post-Deployment

```bash
php artisan cache:clear
```

Cache clear required so cached property analysis regenerates with the updated cost calculation.
