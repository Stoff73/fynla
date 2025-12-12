# Feature Specification: Main Dashboard

## Status: Live

## Executive Summary

The Main Dashboard serves as the central hub of the Fynla application, providing users with an at-a-glance overview of their complete financial picture. It displays net worth summary, profile completeness, and provides quick navigation to all five financial planning modules (Protection, Savings, Investment, Retirement, Estate).

### Elevator Pitch

Your complete financial life on one screen, with instant access to every planning module and clear visibility of what needs attention.

### Problem Statement

Users who have entered financial data across multiple areas need a single view that summarises their overall position without requiring navigation through each individual module. They also need clear pathways to the specific areas they want to review or update.

### Target Audience

- Primary: All logged-in Fynla users seeking an overview of their financial position
- Secondary: Users returning to the application who need quick orientation
- Tertiary: Financial advisers reviewing client positions at a glance

### Unique Selling Proposition

A unified view that transforms complex financial data into clear, actionable insights with one-click access to any planning area.

### Success Metrics

| Metric | Target | Measurement Method |
|--------|--------|-------------------|
| Time to first action | Under 5 seconds | Click tracking |
| Module navigation clarity | 90% reach intended module on first click | User testing |
| Dashboard load time | Under 2 seconds | Performance monitoring |
| Profile completion rate | 80% above 70% complete | User data analysis |

---

## User Personas

### Persona 1: Weekly Reviewer

**Demographics**: 40-year-old professional checking financial status regularly

**Goals**:
- See net worth at a glance
- Identify any areas needing attention
- Navigate quickly to specific module for updates

**Pain Points**:
- Wants quick overview without drilling into details
- Needs clear indication of changes since last visit

**Success Criteria**: Views dashboard, understands current position, navigates to intended module within 30 seconds.

### Persona 2: First-Time Post-Onboarding User

**Demographics**: New user who just completed onboarding

**Goals**:
- Understand what the application offers
- See initial analysis of entered data
- Know where to go next

**Pain Points**:
- May feel lost after onboarding ends
- Needs guidance on next steps

**Success Criteria**: Understands dashboard layout and successfully navigates to first module of interest.

### Persona 3: Mobile User

**Demographics**: User accessing on mobile device during commute

**Goals**:
- Quick check of key figures
- Access specific information rapidly
- Complete task before reaching destination

**Pain Points**:
- Limited screen space
- Time pressure

**Success Criteria**: Views key information and completes intended action within 2 minutes on mobile.

---

## User Stories

### US-01: View Net Worth Summary

**As a** logged-in user,
**I want to** see my total net worth prominently displayed,
**So that I** understand my overall financial position immediately.

**Acceptance Criteria**:
- Given I am on the main dashboard
- When the page loads
- Then I see my total net worth (assets minus liabilities) displayed prominently

**Additional Criteria**:
- Net worth displays in GBP currency format
- Total assets figure shown
- Total liabilities figure shown
- Calculation is current (reflects latest data)

### US-02: Access Module Quick Cards

**As a** user wanting to explore a specific area,
**I want to** see clickable cards for each planning module,
**So that I can** navigate to the area I need with one click.

**Acceptance Criteria**:
- Given I am on the main dashboard
- When I view the module cards
- Then I see cards for Protection, Savings, Investment, Retirement, and Estate

**Additional Criteria**:
- Each card displays module name clearly
- Each card is clickable and navigates to respective module
- Cards include visual icons or indicators
- Cards show summary metrics where available

### US-03: View Profile Completeness

**As a** user with incomplete profile,
**I want to** see how complete my profile is,
**So that I** know whether I need to add more information.

**Acceptance Criteria**:
- Given I have completed some onboarding steps
- When I view the dashboard
- Then I see a profile completeness indicator (percentage or progress bar)

**Additional Criteria**:
- Completeness reflects all data entry sections
- Link provided to complete missing sections
- Visual distinction between complete and incomplete states

### US-04: Navigate to User Profile

**As a** user wanting to update personal details,
**I want to** access my profile from the dashboard,
**So that I can** review and update my information.

**Acceptance Criteria**:
- Given I am on the main dashboard
- When I click on profile/settings navigation
- Then I am taken to my user profile page

### US-05: Navigate to Net Worth Detail

**As a** user wanting detailed asset breakdown,
**I want to** click through to net worth details,
**So that I can** see the full breakdown of my assets and liabilities.

**Acceptance Criteria**:
- Given I am on the main dashboard
- When I click on net worth or related link
- Then I am taken to the Net Worth module

### US-06: View Linked Spouse Information

**As a** user with linked spouse account,
**I want to** see combined household position where relevant,
**So that I** understand our joint financial picture.

**Acceptance Criteria**:
- Given I have a linked spouse account with sharing enabled
- When I view the dashboard
- Then I see indication of combined/household figures where applicable

---

## Feature Details

### Dashboard Layout

**Header Section**:
- User name greeting
- Navigation menu
- Profile/settings access
- Logout option

**Primary Summary**:
- Net Worth figure (large, prominent)
- Assets total
- Liabilities total
- Change indicator (if historical data available)

**Module Quick Cards**:
Five cards arranged for easy access:

1. **Protection Card**
   - Icon: Shield or umbrella
   - Summary: Number of policies, total cover
   - Click action: Navigate to Protection dashboard

2. **Savings Card**
   - Icon: Piggy bank or safe
   - Summary: Total savings, emergency fund status
   - Click action: Navigate to Savings dashboard

3. **Investment Card**
   - Icon: Chart or graph
   - Summary: Portfolio value, gain/loss
   - Click action: Navigate to Investment dashboard

4. **Retirement Card**
   - Icon: Sunset or pension icon
   - Summary: Pension total, years to retirement
   - Click action: Navigate to Retirement dashboard

5. **Estate Card**
   - Icon: Document or home
   - Summary: Estate value, IHT indicator
   - Click action: Navigate to Estate dashboard

**Profile Completeness**:
- Visual progress indicator
- Percentage complete
- Call-to-action for incomplete profiles
- Link to complete remaining sections

**Secondary Navigation**:
- Link to Net Worth detail
- Link to Properties
- Link to User Profile

### Data Sources

- Net worth calculated from all asset and liability records
- Module summaries pulled from respective data stores
- Profile completeness calculated from onboarding step tracking
- Spouse data included when sharing permissions granted

---

## User Flows

### Flow 1: Dashboard to Module

```
Login
    |
    v
Main Dashboard
    |
    +--> View net worth summary
    |
    v
Click module card (e.g., Retirement)
    |
    v
Retirement Dashboard
```

### Flow 2: Dashboard to Profile Completion

```
Main Dashboard
    |
    +--> Notice "Profile 65% Complete"
    |
    v
Click "Complete Your Profile"
    |
    v
Profile page with incomplete sections highlighted
    |
    v
Click incomplete section
    |
    v
Relevant form opens
```

### Flow 3: Mobile Quick Check

```
Open app on mobile
    |
    v
Dashboard loads (responsive layout)
    |
    +--> See net worth figure
    |
    +--> See module cards (scrollable)
    |
    v
Tap desired card
    |
    v
Module dashboard (mobile-optimised)
```

---

## Edge Cases

### EC-01: New User with No Data

**Scenario**: User has registered but entered no financial data.
**Expected Behaviour**: Dashboard displays "GBP 0" net worth with clear guidance to complete onboarding or add data.

### EC-02: User with Incomplete Data

**Scenario**: User has some assets but no liabilities entered.
**Expected Behaviour**: Net worth reflects assets only. Profile completeness indicates missing areas.

### EC-03: Linked Spouse with Restricted Sharing

**Scenario**: Spouse accounts linked but sharing permissions not granted.
**Expected Behaviour**: Dashboard shows individual data only. Note that spouse data not visible due to permissions.

### EC-04: Very High Net Worth Display

**Scenario**: User has net worth in millions.
**Expected Behaviour**: Currency formatting handles large numbers appropriately (e.g., "GBP 2,450,000" or "GBP 2.45M").

### EC-05: Negative Net Worth

**Scenario**: User's liabilities exceed assets.
**Expected Behaviour**: Display negative net worth clearly (e.g., "-GBP 15,000" in distinct colour).

### EC-06: Module with No Data

**Scenario**: User has no protection policies entered.
**Expected Behaviour**: Protection card shows "No policies" or "0 policies" with invitation to add.

---

## Acceptance Criteria Summary

| ID | Criterion | Testable |
|----|-----------|----------|
| AC-01 | Dashboard displays total net worth | Yes |
| AC-02 | Dashboard displays total assets | Yes |
| AC-03 | Dashboard displays total liabilities | Yes |
| AC-04 | Five module cards are visible and clickable | Yes |
| AC-05 | Each card navigates to correct module | Yes |
| AC-06 | Profile completeness indicator shown | Yes |
| AC-07 | Currency values formatted correctly (GBP) | Yes |
| AC-08 | Dashboard is responsive on mobile devices | Yes |
| AC-09 | Page loads within 2 seconds | Yes |
| AC-10 | Negative net worth displays appropriately | Yes |

---

## Dependencies

### Upstream Dependencies

- Registration and Authentication (user must be logged in)
- Onboarding Wizard (source of profile completeness data)
- All data entry features (source of financial data)

### Downstream Dependencies

- All module dashboards (navigation targets)
- User Profile (navigation target)
- Net Worth module (navigation target)

---

## Technical Constraints

1. **Performance**: Dashboard must aggregate data from multiple sources efficiently
2. **Caching**: Consider caching summary calculations to avoid recalculation on every load
3. **Real-time Updates**: Balance between data freshness and performance
4. **Responsive Design**: Must function on screens from 320px to 4K

---

## Non-Functional Requirements

### Performance

- Initial load time: Under 2 seconds
- Data refresh time: Under 1 second
- Mobile load time: Under 3 seconds on 3G

### Security

- Dashboard only shows authenticated user's data
- Spouse data only shown with appropriate permissions
- No sensitive data cached in browser beyond session

### Accessibility

- WCAG 2.1 AA compliance
- Screen reader announces key figures
- Keyboard navigation to all interactive elements
- Sufficient colour contrast for all text

### Scalability

- Dashboard performs consistently regardless of data volume
- Summary calculations optimised for users with many assets

---

## UX Considerations

1. **Visual Hierarchy**: Net worth most prominent, module cards secondary
2. **Information Density**: Balance comprehensive view with clarity
3. **Mobile First**: Ensure key information visible without scrolling on mobile
4. **Action Orientation**: Clear next steps for users at different stages
5. **Positive Framing**: Present financial position encouragingly where possible
6. **Progress Motivation**: Profile completeness encourages further engagement
7. **Quick Actions**: Common tasks accessible within 2 clicks
8. **Consistent Navigation**: Module cards in same order throughout application
