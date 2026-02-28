# Deployment Notes - 16 February 2026

**Status: DEPLOYED - 16 February 2026**

## Branch: `spouseLetter`

## Changes

### Letter to Spouse Improvements

1. **Added Informational Message**
   - Green informational banner at top of page
   - Prompts users to click Edit to add custom information boxes
   - Helps users discover the additional information feature
   - Only shows in view mode (not when editing)

2. **Removed Spouse Viewing Functionality**
   - Removed "My Letter" / "Spouse's Letter" toggle buttons
   - Removed read-only viewing of spouse's letter
   - Simplified component - users can only view/edit their own letter
   - Removed unnecessary data properties and methods
   - Cleaned up component logic

3. **Print/PDF Formatting Improvements**
   - Personalized header: "Letter to {spouse_first_name}" instead of generic "Letter to Spouse"
   - Repositioned logo to top-right corner (110px, positioned at -5px/-5px)
   - Centered title below logo with date underneath
   - Moved "Prepared by" to footer (appears on all pages)
   - Removed blue header separator line
   - Split immediate actions into two-column grid for better readability
   - Removed blue accent styling from immediate actions (border, number backgrounds)

4. **Ownership Badges**
   - Added ownership badges (Joint/Tenants in Common) to all financial assets
   - Badges appear in both app view and print/PDF version
   - Assets include: savings accounts, investments, properties, liabilities
   - Changed all acronyms to full text (e.g., "TIC" → "Tenants in Common")
   - Removed pension type badges (DC/DB) as redundant

5. **Fixed Print Hanging Bug**
   - Print button was stuck on "Preparing..." indefinitely
   - Root cause: 823KB logo not loading in new window, window.onload never fired
   - Fixed by removing window.onload dependency and explicitly waiting for logo
   - Added error handling and 3-second timeout failsafe

6. **Page Breaks**
   - Each Part section now starts on a new page in print output
   - Improves document structure and readability

7. **Removed Redundant Funeral Preference Field**
   - Removed funeral_preference dropdown from edit mode
   - Removed funeral preference card from print/PDF version
   - Information is already captured in funeral_service_details field
   - Part 4 now shows only: Funeral Service Details, Obituary Wishes, Additional Wishes

8. **Print View Formatting Fixes**
   - Added descriptive subtitles to all Part sections (Parts 1-4) to match app view
   - Fixed footer positioning with proper CSS and z-index
   - Footer now correctly appears at bottom of each printed page
   - Improved consistency between app view and print/PDF view
   - Restructured @page margins and body padding for better print layout
   - Added page-break-avoid to section titles and subtitles

9. **Print Layout Refinements**
   - Centered "Letter to {spouse}" title vertically on first page
   - Doubled Part section top spacing from 5mm to 10mm for better visual hierarchy
   - Fixed document title to show meaningful name instead of "about:blank"

10. **UI Personalization & Cleanup**
   - Tab name now shows "Letter to {spouse_name}" (e.g., "Letter to Sarah")
   - Removed redundant h2 heading from LetterToSpouse component content
   - Removed "Valuable Information" main heading from ValuableInfo view
   - Affects all tabs: Letter to Spouse, Will, Income, Expenditure, Risk Profile
   - Cleaner, more personalized user interface with less redundancy

## Technical Changes

### Frontend Changes
- Enhanced user guidance for additional information boxes
- Streamlined component by removing multi-user view logic
- Improved UX with visible hint about customization options
- Personalized tab labels and component titles with spouse names
- Updated both LetterToSpouse.vue and ValuableInfo.vue for consistency

### Code Quality
- Removed unused data properties: `viewMode`, `hasSpouse`, `spouseName`, `spouseLetterData`, `funeral_preference`
- Removed unused computed property: `isReadOnly`
- Removed unused methods: `switchToMyLetter()`, `loadSpouseLetter()`, `formatFuneralPreference()`
- Renamed methods for clarity: `loadMyLetter()` → `loadLetter()`, `checkSpouse()` → `checkMaritalStatus()`
- Simplified template conditions throughout component
- Fixed print functionality: removed window.onload dependency, added explicit image loading with timeout
- Improved print CSS: added page breaks, repositioned header/footer elements

---

## Build Required: Yes

Vue component changes require a frontend build.

```bash
./deploy/fynla-org/build.sh
```

## Files to Upload

### Frontend Build (replace entire directory)
| Local Path | Server Path |
|------------|-------------|
| `public/build/` | `~/www/fynla.org/public_html/public/build/` |

### Backend Files (none required)
No backend changes in this deployment.

---

## Post-Upload

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Clear caches
php artisan cache:clear && php artisan route:clear && php artisan config:clear
```

---

## Testing Checklist

After deployment, verify:

**General Functionality:**
- [ ] No "Valuable Information" main heading displayed on page
- [ ] Tab name shows "Letter to {spouse_first_name}" for married users
- [ ] Tab name shows "Expression of Wishes" for single/widowed/divorced users
- [ ] No h2 heading displayed within Letter to Spouse tab (only description paragraph)
- [ ] All tabs (Letter, Will, Income, Expenditure, Risk Profile) work correctly
- [ ] Green informational message appears at top of Letter to Spouse page
- [ ] Message only shows in view mode (disappears when clicking Edit)
- [ ] Edit button works correctly
- [ ] Additional information boxes can be added/removed in edit mode
- [ ] Save functionality works
- [ ] No console errors
- [ ] Spouse viewing buttons are removed
- [ ] Letter is personal to logged-in user only

**Print/PDF Functionality:**
- [ ] Print/Save button works (no hanging on "Preparing...")
- [ ] PDF document title shows "Letter to {spouse_first_name}" or "Expression of Wishes"
- [ ] First page title "Letter to {spouse}" is vertically centered on page
- [ ] Logo appears in top-right corner at correct size
- [ ] Date appears below title
- [ ] "Prepared by" appears in footer on all pages
- [ ] Footer appears at BOTTOM of each page (not at top or overlapping content)
- [ ] Each Part section starts on a new page
- [ ] Part sections (2, 3, 4) have 10mm space from top of page
- [ ] All Part headings (1-4) include descriptive subtitles below title
- [ ] Part subtitles match app view text and formatting
- [ ] Immediate actions displayed in two-column grid
- [ ] No blue accent styling on immediate actions

**Ownership Badges:**
- [ ] Savings accounts show ISA badges when applicable
- [ ] Savings accounts show Joint/Tenants in Common badges
- [ ] Investments show GIA/ISA badges when applicable
- [ ] Investments show Joint/Tenants in Common badges
- [ ] Properties show Joint/Tenants in Common badges
- [ ] Liabilities show Joint/Tenants in Common badges
- [ ] All badges use full text (no acronyms like "TIC")
- [ ] Pension cards do NOT show DC/DB badges
- [ ] Badges appear in both app view and print/PDF version

**Part 4: Funeral and Final Wishes:**
- [ ] Only 3 fields shown: Funeral Service Details, Obituary Wishes, Additional Wishes
- [ ] Funeral Preference dropdown is NOT visible in edit mode
- [ ] Funeral Preference card is NOT in print/PDF version
- [ ] 3-column grid layout in print version

---

## Rollback Plan

If issues occur:
1. Switch back to `main` branch: `git checkout main`
2. Rebuild: `./deploy/fynla-org/build.sh`
3. Upload `public/build/` directory
4. Clear caches on server

---

## Notes

- This deployment significantly improves the Letter to Spouse/Expression of Wishes feature
- **Privacy enhancement**: Removes the ability for spouses to view each other's letters
- **UX improvements**: Better guidance for custom information boxes, improved print formatting
- **Print/PDF enhancements**: Personalized headers, better layout, ownership badges, page breaks
- **Bug fixes**: Resolved print hanging issue, removed redundant fields
- **Code quality**: Removed unused code, improved maintainability
- No database changes required
- No breaking changes to existing data
- All changes are backward compatible with existing letter data

---

# UI Improvements - About & Security Pages

**Status: DEPLOYED - 16 February 2026**

## Branch: `uiFix` (merged to main via PR #70)

## Changes

### About Page Restructure

1. **Merged Hero Section with "Why We Built This"**
   - Created two-column layout in hero section
   - Left column: "About Fynla" heading with tagline
   - Right column: Complete "Why We Built This" content with all paragraphs
   - Responsive design: stacks on mobile, side-by-side on desktop
   - Improved visual hierarchy and content flow

2. **Added "About Fynla" Content Section**
   - New full-width section placed below founders section
   - Three paragraphs describing Fynla platform and features
   - Centered layout (max-w-4xl)
   - Provides comprehensive product information

### Security Page Restructure

1. **Merged Security Cards into Hero Section**
   - Created two-column layout in hero section
   - Left column: Security badge, heading, and description
   - Right column: Three security overview cards arranged vertically
   - Responsive design: stacks on mobile, side-by-side on desktop
   - Improved visual hierarchy and content flow

2. **Updated Card Styling**
   - Cards now use semi-transparent white backdrop (`bg-white/10 backdrop-blur-md`)
   - White text to match hero section aesthetic
   - Better visual integration with hero background
   - Maintains emerald, blue, and purple icon colors
   - Constrained to max-width (max-w-md) for refined appearance

## Technical Changes

### Frontend Changes
- Updated `resources/js/views/Public/AboutPage.vue`
  - Restructured hero section with grid layout
  - Removed redundant section structure
  - Added new About Fynla content section at bottom
- Updated `resources/js/views/Public/SecurityPage.vue`
  - Merged security cards into hero section
  - Created two-column hero layout
  - Updated card styling with backdrop blur

### Code Quality
- Cleaner component structure
- Better responsive layout using grid
- Improved content organization

---

## Build Required: Yes

Vue component changes require a frontend build.

```bash
./deploy/fynla-org/build.sh
```

## Files to Upload

### Frontend Build (replace entire directory)
| Local Path | Server Path |
|------------|-------------|
| `public/build/` | `~/www.fynla.org/public_html/public/build/` |

### Backend Files (none required)
No backend changes in this deployment.

---

## Post-Upload

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Clear caches
php artisan cache:clear && php artisan route:clear && php artisan config:clear
```

---

## Testing Checklist

After deployment, verify:

**About Page:**
- [ ] About page loads without errors
- [ ] Hero section displays two-column layout on desktop
- [ ] Left column shows "About Fynla" heading and tagline
- [ ] Right column shows "Why We Built This" content
- [ ] Layout stacks vertically on mobile devices
- [ ] Founders section displays correctly
- [ ] "About Fynla" section appears below founders
- [ ] "About Fynla" section spans full width (centered)
- [ ] All gradient backgrounds and animations work
- [ ] No console errors

**Security Page:**
- [ ] Security page loads without errors
- [ ] Hero section displays two-column layout on desktop
- [ ] Left column shows security badge, heading, and description
- [ ] Right column shows three security cards vertically
- [ ] Cards have semi-transparent backdrop with white text
- [ ] Layout stacks vertically on mobile devices
- [ ] All detailed security sections display correctly
- [ ] All gradient backgrounds and animations work
- [ ] No console errors

---

## Rollback Plan

If issues occur:
1. Switch back to `main` branch: `git checkout main`
2. Rebuild: `./deploy/fynla-org/build.sh`
3. Upload `public/build/` directory
4. Clear caches on server

---

## Notes

- This deployment improves website layout and content organization
- **About page**: Better visual hierarchy with merged hero section, comprehensive product information
- **Security page**: Security cards integrated into hero for improved visual flow
- Consistent two-column hero pattern across public pages
- No database changes required
- No breaking changes
