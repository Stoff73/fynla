# TODO — Fynla

*Last updated: 22 March 2026 (dashboard UI session)*
*Previous session: 20 March 2026*

## Completed This Session — Dashboard UI Redesign (20 commits)

- [x] Dashboard header with Fyn icon, greeting, progress hero
- [x] JourneyProgressHero — 160px progress ring, responsive % text, Suggested for You panel
- [x] DashboardCard — empty prop (light-pink bg), clickable hover (pink border)
- [x] SideMenuItem — active state always horizon-500 + white
- [x] Navbar — mobile title padding
- [x] LifeTimelineCard — empty state layout, conditional "What if" + "Add event"
- [x] Empty state CTAs — all raspberry-500 buttons
- [x] Card hover standardised — hover:bg-[#EEEEEE] across 15 files
- [x] SubNavBar system — route-based tabs + CTAs, subNav Vuex store, config for all modules
- [x] Removed duplicate page headers — PensionList, PropertyList, TrustsDashboard, CurrentSituation, PortfolioOverview
- [x] Added SubNav CTAs — Upload Statement (retirement/savings), Upload Document (trusts)
- [x] Violet buttons → light-pink across dashboard + module pages
- [x] Solid light-pink add buttons (#FAD6E0) + dark grey text (text-horizon-500) on all module pages
- [x] SubNavBar gap +5px between tabs and CTAs
- [x] Dashboard module cards — pink border on hover, white bg maintained
- [x] Login/Register logo height 134px
- [x] Tailwind config — light-pink-200/300 shades added

## Carried Forward (from 20 March)

### Must Verify on Production
- [ ] Risk profile page loads for logged-in real users
- [ ] AI chat `get_tax_information` tool works with all 18 topics
- [ ] Investment knowledge nudge appears for users with investments but no knowledge_level
- [ ] Admin tax settings all 10 tabs render correctly on production

### Tech Debt (remaining)
- [ ] Journey progress calculation should use data completeness (needs product decision)

### Features (Backlog)
- [ ] PortfolioOptimization.vue:197 — rebalancing plan (coming soon toast)
- [ ] Scottish Income Tax support (placeholder tab added, needs rate bands)
- [ ] Benefits warnings in onboarding + family dashboard
- [ ] Payment/webhook feature tests
- [ ] AI chat feature tests

### Production Deployment
- [ ] Deploy dashboard branch changes (frontend-only rebuild needed, no PHP/DB changes)
- [ ] Previous deploys may still be pending: March 19 + March 20 changes

## Outstanding from This Session

### Browser Testing Needed
- [ ] Dashboard home — verify all module cards render correctly with new hover (pink border)
- [ ] Cash page — verify solid light-pink add buttons with dark grey text
- [ ] Pensions page — verify repeat header removed, SubNav CTAs work (Add Pension, Upload Statement)
- [ ] Properties page — verify header removed, SubNav "Add Property" works
- [ ] Trusts page — verify header removed, SubNav CTAs work (Add Trust, Upload Document)
- [ ] Savings page — verify buttons removed from section header, SubNav CTAs work
- [ ] Investments page — verify buttons removed from section header, SubNav CTAs work
- [ ] SubNavBar gap — verify 5px spacing between tabs and CTA row
- [ ] Empty state cards — verify light-pink bg on dashboard home

### Potential Issues
- [ ] PensionList "Risk Profile" link was in the removed header — may need adding back as SubNav tab or inline link
- [ ] Orphaned CSS classes in PensionList, PropertyList, TrustsDashboard (header button styles still in style blocks) — can clean up

## Context for Next Session

Dashboard branch has 20 commits with a major UI redesign. All changes are frontend-only (Vue + CSS + Tailwind config). No PHP or database changes. The SubNavBar system centralises navigation CTAs — module pages no longer have their own title+button headers.

Key risk: the PensionList "Risk Profile" link was part of the removed header section. Users may need another way to access it — consider adding it as a SubNav tab for retirement or as an inline link in the pension list.

All code compiles (vite build passes). Dev server running on localhost:8000. Branch is pushed to origin/dashboard.

## Files to Review
- `resources/js/components/NetWorth/PensionList.vue` — header removed, Risk Profile link lost
- `resources/js/components/SubNavBar.vue` — new component, core to navigation
- `resources/js/constants/subNavConfig.js` — route config for all modules
