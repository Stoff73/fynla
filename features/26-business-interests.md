# Feature Specification: Net Worth Module - Business Interests

## Status: Planned (Coming Soon)

## Executive Summary

The Business Interests feature will enable users to record ownership stakes in businesses including sole trader operations, partnerships, limited companies, and LLPs. Users will be able to track valuations, ownership percentages, and understand how business assets affect their net worth and estate planning.

### Elevator Pitch

Track your business ownership to include it in your complete financial picture, from sole trader to company director, with understanding of estate planning implications.

### Problem Statement

Business owners often struggle to include their business interests in personal financial planning. Business assets may be significant parts of their net worth but are typically not tracked alongside personal assets. Understanding business property relief for IHT is also important for estate planning.

### Target Audience

- Primary: Business owners wanting to include business value in net worth
- Secondary: Company shareholders tracking share ownership
- Tertiary: Partnership members tracking partnership interest

### Unique Selling Proposition

UK business interest tracking integrated with net worth and estate planning, including Business Property Relief (BPR) consideration for IHT planning.

### Success Metrics

| Metric | Target | Measurement Method |
|--------|--------|-------------------|
| Feature adoption | 30% of business owners record interest | Data analysis |
| Complete data entry | 70% include valuation | Data completeness |
| BPR awareness | 50% understand IHT implications | Content tracking |
| Integration usage | 60% view in net worth context | Feature tracking |

---

## User Personas

### Persona 1: John - Limited Company Director

**Demographics**: 48-year-old with 100% ownership of trading company

**Goals**:
- Track company value
- Include in net worth
- Understand BPR for estate planning

**Pain Points**:
- Business value fluctuates
- Separate from personal accounts
- IHT implications unclear

**Success Criteria**: Business recorded with value, shows in net worth, BPR noted.

### Persona 2: Sarah - Partnership Member

**Demographics**: 42-year-old partner in professional services firm

**Goals**:
- Track partnership share
- Understand partnership value
- Include in financial planning

**Pain Points**:
- Partnership valuation complex
- Share of profits vs capital
- Net worth incomplete without it

**Success Criteria**: Partnership interest recorded with percentage.

### Persona 3: Michael - Sole Trader

**Demographics**: 35-year-old self-employed consultant

**Goals**:
- Track business assets
- Understand business value
- Separate personal and business

**Pain Points**:
- Business has limited tangible value
- Goodwill hard to value
- Wants consolidated view

**Success Criteria**: Sole trader business recorded with estimated value.

---

## Planned User Stories

### US-01: View Business Interests

**As a** business owner,
**I want to** see all my business interests listed,
**So that I** have complete financial picture.

**Acceptance Criteria**:
- Given I am on Net Worth dashboard
- When I view Business Interests tab
- Then I see all recorded businesses

### US-02: Add Business Interest

**As a** user,
**I want to** add a business interest,
**So that** it is included in my net worth.

**Acceptance Criteria**:
- Given I am on Business Interests tab
- When I click "Add Business"
- Then I can enter business details

**Planned Fields**:
- Business name
- Business structure
- Ownership percentage
- Estimated value
- Industry sector
- Trading status
- BPR qualifying status

### US-03: Select Business Structure

**As a** user adding business,
**I want to** specify business structure,
**So that** correct treatment applies.

**Planned Business Structures**:
| Type | Description |
|------|-------------|
| sole_trader | Sole Trader |
| partnership | Partnership |
| limited_company | Limited Company |
| llp | Limited Liability Partnership |
| plc | Public Limited Company |

### US-04: Track Ownership Percentage

**As a** user with partial ownership,
**I want to** record my ownership share,
**So that** my share is calculated correctly.

**Acceptance Criteria**:
- Given I am adding business interest
- When I enter ownership percentage
- Then my share of value is calculated

### US-05: Record Business Valuation

**As a** business owner,
**I want to** record business value,
**So that** net worth includes it.

**Acceptance Criteria**:
- Given I am adding business
- When I enter estimated value
- Then my share contributes to net worth

### US-06: Understand BPR Eligibility

**As a** business owner,
**I want to** understand BPR eligibility,
**So that I** know estate planning implications.

**Acceptance Criteria**:
- Given I have business recorded
- When I view BPR information
- Then I see eligibility guidance

**Business Property Relief**:
- 100% relief for trading businesses
- 50% for land/buildings used by business
- Must be held 2+ years
- Significant IHT planning opportunity

### US-07: Edit Business Interest

**As a** user,
**I want to** update business details,
**So that** values stay current.

### US-08: Delete Business Interest

**As a** user,
**I want to** remove sold businesses,
**So that** records are current.

---

## Feature Details (Planned)

### Business Structures

**Sole Trader**:
- Individual trading as themselves
- Personal liability for debts
- Business income taxed as personal income
- Simple structure

**Partnership**:
- Multiple individuals trading together
- Shared liability (unless LLP)
- Profit share according to agreement
- Partnership interest has value

**Limited Company**:
- Separate legal entity
- Shareholders own company
- Directors run company
- Shares have value

**LLP (Limited Liability Partnership)**:
- Partnership with limited liability
- Members not personally liable
- Common for professional services
- Membership interest has value

**PLC (Public Limited Company)**:
- Shares publicly tradeable
- More complex structure
- Listed share value available
- Less common in personal planning

### Data Fields (Planned)

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| business_name | string | Yes | Non-empty |
| business_structure | enum | Yes | Valid type |
| ownership_percent | decimal | Yes | 0-100 |
| total_value | decimal | No | Non-negative |
| your_share_value | decimal | No | Calculated |
| industry | string | No | - |
| trading_status | enum | No | trading/dormant |
| bpr_eligible | boolean | No | Default false |
| held_since | date | No | Past date |
| notes | text | No | - |

### Net Worth Integration

**Calculation**:
```
Your Business Value = Total Business Value x (Ownership % / 100)
```

**Display**:
- Separate line in net worth breakdown
- Included in total assets
- Note about valuation uncertainty

### IHT and BPR Integration

**Business Property Relief**:
- 100% relief on qualifying trading businesses
- Effectively excludes from estate for IHT
- Must be held 2 years minimum
- Not available for investment companies

**System Treatment**:
- Flag business as BPR qualifying
- IHT calculation shows business value
- Show with BPR relief applied
- Note conditions for relief

---

## Implementation Considerations

### Valuation Challenges

Business valuation is complex and subjective:
- Multiple valuation methods exist
- Professional valuation recommended
- User enters estimate
- Note uncertainty in display

### Integration Points

- Net Worth: Include in total assets
- IHT Planning: Consider BPR eligibility
- Financial Statements: Include in assets
- User Profile: Link to self-employment income

### UI Considerations

- Coming Soon banner currently displayed
- Clear guidance on valuation
- BPR education prominent
- Structure explanations available

---

## Acceptance Criteria (Planned)

| ID | Criterion | Testable |
|----|-----------|----------|
| AC-01 | Business interests can be added | Yes |
| AC-02 | All business structures available | Yes |
| AC-03 | Ownership percentage recorded | Yes |
| AC-04 | Value calculation correct | Yes |
| AC-05 | Shows in net worth | Yes |
| AC-06 | BPR eligibility can be indicated | Yes |
| AC-07 | IHT calculation considers BPR | Yes |
| AC-08 | Edit and delete work | Yes |

---

## Dependencies

### Upstream Dependencies

- User authentication
- Net worth framework
- IHT calculation service

### Downstream Dependencies

- Net worth total
- IHT calculation
- Estate planning recommendations

---

## Current State

The Business Interests tab exists in the Net Worth module and displays a "Coming Soon" watermark in the amber box style. No data entry or storage is currently implemented.

### Planned Implementation Priority

This feature is planned for a future release. Priority factors:
- User demand for business tracking
- Complexity of valuation guidance
- Integration with IHT/BPR calculations
