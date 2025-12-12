# Feature Specification: Net Worth Module - Chattels and Valuables

## Status: Planned (Coming Soon)

## Executive Summary

The Chattels and Valuables feature will enable users to record personal possessions of significant value including vehicles, art, antiques, jewellery, and collectibles. Users will be able to track valuations, purchase history, and understand how these assets affect their net worth and estate planning.

### Elevator Pitch

Track your valuable possessions from cars to art collections, including them in your complete financial picture and estate planning.

### Problem Statement

High-value personal possessions (chattels) can represent significant wealth but are often excluded from financial planning. For estate planning purposes, these items form part of the taxable estate and should be tracked. Some chattels also have specific CGT treatment.

### Target Audience

- Primary: Users with valuable collectibles, art, or antiques
- Secondary: Vehicle owners wanting comprehensive asset tracking
- Tertiary: Users needing complete estate inventory for IHT planning

### Unique Selling Proposition

UK-specific chattel tracking integrated with estate planning, including understanding of the "chattels exemption" for CGT and treatment for IHT purposes.

### Success Metrics

| Metric | Target | Measurement Method |
|--------|--------|-------------------|
| Feature adoption | 25% of users record chattels | Data analysis |
| Complete data entry | 60% include valuations | Data completeness |
| Category usage | 80% use appropriate category | Data validation |
| IHT integration | 70% understand estate implications | Content tracking |

---

## User Personas

### Persona 1: Margaret - Art Collector

**Demographics**: 62-year-old with valuable art collection

**Goals**:
- Track art collection value
- Document for insurance
- Include in estate planning

**Pain Points**:
- Values change over time
- Provenance documentation
- Estate implications unclear

**Success Criteria**: Art pieces recorded with values, shows in net worth and IHT.

### Persona 2: James - Classic Car Owner

**Demographics**: 55-year-old with classic and modern vehicles

**Goals**:
- Track vehicle values
- Understand CGT on classics
- Include in overall wealth

**Pain Points**:
- Classic car value appreciation
- Multiple vehicles
- CGT rules unclear

**Success Criteria**: Vehicles recorded, CGT treatment noted.

### Persona 3: Susan - Jewellery Owner

**Demographics**: 50-year-old with inherited jewellery collection

**Goals**:
- Document inherited pieces
- Track current values
- Plan for passing to children

**Pain Points**:
- Values from inheritance unclear
- Sentimental vs financial value
- Want organised inventory

**Success Criteria**: Jewellery recorded with descriptions and values.

---

## Planned User Stories

### US-01: View Chattels List

**As a** user with valuables,
**I want to** see all my chattels listed,
**So that I** have complete picture.

**Acceptance Criteria**:
- Given I am on Net Worth dashboard
- When I view Chattels tab
- Then I see all recorded items

### US-02: Add Chattel Item

**As a** user,
**I want to** add a valuable item,
**So that** it is included in my net worth.

**Acceptance Criteria**:
- Given I am on Chattels tab
- When I click "Add Item"
- Then I can enter item details

**Planned Fields**:
- Item name/description
- Category
- Purchase date
- Purchase price
- Current value
- Valuation date
- Location
- Insurance status
- Notes

### US-03: Select Chattel Category

**As a** user adding item,
**I want to** specify category,
**So that** correct treatment applies.

**Planned Categories**:
| Category | Description | CGT Note |
|----------|-------------|----------|
| vehicle | Vehicles (cars, motorcycles, boats) | Wasting asset exempt* |
| art | Art and paintings | Chattel exemption may apply |
| antique | Antiques | Chattel exemption may apply |
| jewellery | Jewellery | Chattel exemption may apply |
| watch | Watches | Chattel exemption may apply |
| wine | Wine collection | Wasting asset |
| collectible | Other collectibles | Depends on type |
| furniture | Valuable furniture | Chattel exemption may apply |
| other | Other valuable items | Varies |

*Classic/vintage cars may not be wasting assets

### US-04: Track Purchase History

**As a** user,
**I want to** record purchase details,
**So that** gains can be calculated.

**Acceptance Criteria**:
- Given I am adding chattel
- When I enter purchase details
- Then cost basis is recorded

### US-05: Update Valuation

**As a** user with valuable items,
**I want to** update current values,
**So that** net worth is accurate.

**Acceptance Criteria**:
- Given I have chattel recorded
- When I update valuation
- Then current value changes

### US-06: Understand CGT Treatment

**As a** user with appreciating chattels,
**I want to** understand CGT rules,
**So that I** know tax on sale.

**Acceptance Criteria**:
- Given I view chattel
- When I see CGT information
- Then I understand exemptions

**Chattel CGT Rules**:
- Sale proceeds under GBP 6,000: Exempt
- Sale proceeds over GBP 6,000: Special marginal relief calculation
- Wasting assets (predicted life under 50 years): Exempt

### US-07: Track Insurance Status

**As a** user with valuable items,
**I want to** note insurance status,
**So that** I know what is covered.

### US-08: Edit Chattel

**As a** user,
**I want to** update item details,
**So that** records stay current.

### US-09: Delete Chattel

**As a** user,
**I want to** remove sold items,
**So that** records are current.

---

## Feature Details (Planned)

### Category Details

**Vehicles**:
- Cars, motorcycles, boats, caravans
- Usually wasting assets (CGT exempt)
- Classic/vintage cars may appreciate
- Include in net worth

**Art**:
- Paintings, sculptures, prints
- May appreciate significantly
- Chattel exemption under GBP 6,000
- Important for IHT

**Antiques**:
- Furniture, clocks, silverware
- Generally chattel treatment
- May have heritage exemptions
- Often inherited

**Jewellery**:
- Rings, necklaces, watches
- Personal adornment items
- Chattel exemption applies
- Often inherited or sentimental

**Watches**:
- Luxury/collector watches
- Can appreciate significantly
- Chattel exemption applies
- Popular collectible

**Wine**:
- Wine collections
- Wasting asset (CGT exempt)
- Can be valuable
- Storage requirements

**Collectibles**:
- Coins, stamps, memorabilia
- Depends on specific type
- May have specialist markets
- Valuation can be complex

### Data Fields (Planned)

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| item_name | string | Yes | Non-empty |
| category | enum | Yes | Valid category |
| description | text | No | - |
| purchase_date | date | No | Past date |
| purchase_price | decimal | No | Non-negative |
| current_value | decimal | Yes | Non-negative |
| valuation_date | date | No | Past/present |
| location | string | No | - |
| is_insured | boolean | No | Default false |
| insurance_value | decimal | No | Non-negative |
| notes | text | No | - |

### CGT Chattel Rules

**Under GBP 6,000 Proceeds**:
- Fully exempt from CGT
- No reporting required

**Over GBP 6,000 Proceeds**:
- Marginal relief calculation
- Maximum gain = 5/3 x (Proceeds - GBP 6,000)
- Compare to actual gain, use lower

**Wasting Assets**:
- Predicted life under 50 years
- Generally exempt from CGT
- Cars typically qualify
- Antiques do not (indefinite life)

### Integration Points

- Net Worth: Include in total assets
- IHT Planning: Part of taxable estate
- Insurance inventory: Track coverage
- Estate inventory: Complete listing

---

## Implementation Considerations

### Valuation Challenges

Chattel valuation is subjective:
- Art market fluctuates
- Specialist valuations expensive
- Insurance value vs sale value
- User enters estimates

### Photo Storage (Future Enhancement)

Consider ability to:
- Upload photos of items
- Store for insurance purposes
- Help with identification
- Note: Storage implications

### Inheritance Tracking

Items may be inherited:
- Date acquired by inheritance
- Value at inheritance (base cost)
- Probate value reference
- Sentimental vs financial

---

## Acceptance Criteria (Planned)

| ID | Criterion | Testable |
|----|-----------|----------|
| AC-01 | Chattel items can be added | Yes |
| AC-02 | All categories available | Yes |
| AC-03 | Purchase details recorded | Yes |
| AC-04 | Current value tracked | Yes |
| AC-05 | Shows in net worth | Yes |
| AC-06 | CGT treatment explained | Yes |
| AC-07 | Insurance status tracked | Yes |
| AC-08 | Edit and delete work | Yes |

---

## Dependencies

### Upstream Dependencies

- User authentication
- Net worth framework

### Downstream Dependencies

- Net worth total
- IHT calculation (estate value)
- Insurance inventory

---

## Current State

The Chattels & Valuables tab exists in the Net Worth module and displays a "Coming Soon" watermark in the amber box style. No data entry or storage is currently implemented.

### Planned Implementation Priority

This feature is planned for a future release. Priority factors:
- User demand for comprehensive tracking
- Integration with IHT calculations
- Potential for insurance inventory use
- Photo storage infrastructure needs
