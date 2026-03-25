# Family Member Form Algorithm — Complete Field-by-Field Map

**Date:** 25 March 2026
**Source:** `resources/js/components/UserProfile/FamilyMemberFormModal.vue`
**Parent:** `resources/js/components/UserProfile/FamilyMembers.vue`
**Route:** `/profile` (Family tab)
**Entity type:** `family_member`
**API:** `POST /api/user/family-members`

## Form Structure — Single Step

One modal form. Some fields are conditional on relationship type (email for spouse/partner, education for children).

## AI Tool → Form Field Map

### AI Tool: `create_family_member`

| AI param | formData key | Type | Required | Notes |
|----------|-------------|------|----------|-------|
| `first_name` | `first_name` | string | Yes | |
| `surname` | `last_name` | string | No | Maps AI `surname` → form `last_name` |
| `relationship` | `relationship` | string enum | Yes | See types below |
| `date_of_birth` | `date_of_birth` | string (YYYY-MM-DD) | No | Validated: spouse 16+, child max 18 (or 22 if in education) |
| `gender` | `gender` | string enum | No | male, female, other, prefer_not_to_say |
| `is_dependent` | `is_dependent` | boolean | No | Default true for children |
| `education_status` | `education_status` | string enum | No | Only for child/step_child |
| `receives_child_benefit` | `receives_child_benefit` | boolean | No | Only for child/step_child |
| `notes` | `notes` | string | No | |

## Relationship Types

| Value | Label | Conditional Fields |
|-------|-------|-------------------|
| `spouse` | Spouse | Email required (account linking) |
| `partner` | Partner | Email (account linking). Shows tax warning. |
| `child` | Child | education_status, receives_child_benefit. is_dependent defaults true. |
| `step_child` | Step Child | education_status, receives_child_benefit. is_dependent defaults true. |
| `parent` | Parent | None |
| `other_dependent` | Other Dependent | None |

## Education Status Options (child/step_child only)

| Value | Label |
|-------|-------|
| `pre_school` | Pre-School/Nursery |
| `primary` | Primary |
| `secondary` | Secondary |
| `further_education` | Further Education (Sixth Form/College) |
| `higher_education` | Higher Education (University) |
| `graduated` | Graduated |
| `not_applicable` | Not in Education |

## Validation

### Frontend
- `relationship` — required (select)
- `first_name` — required
- `last_name` — required
- `date_of_birth` — optional, validated: spouse must be 16+, child max 18 (or 22 if in education)
- `email` — required for spouse/partner
- `gender` — optional

### Backend (StoreFamilyMemberRequest)
- `relationship` — in: spouse, partner, child, step_child, parent, other_dependent
- `first_name` — required, string, max 255
- `last_name` — required, string, max 255
- `email` — nullable, email (required for spouse)
- `date_of_birth` — nullable, date
- `gender` — nullable, in: male, female, other, prefer_not_to_say
- `is_dependent` — boolean
- `education_status` — nullable, in: pre_school, primary, secondary, further_education, higher_education, graduated, not_applicable
- `receives_child_benefit` — nullable, boolean

## Pre-set Requirements

`relationship` must be pre-set before `beginFieldSequence` — it controls visibility of email field (spouse/partner) and education fields (child/step_child).

## Test Scenarios for Grok

### Scenario 1: Child
"I have a daughter called Emma, she was born on 20 March 2015. She's at primary school."

### Scenario 2: Son (teenager)
"My son James is 16, born 10 September 2009. He's in sixth form college."

### Scenario 3: Parent
"My mother Margaret is 72, born 5 January 1954."

### Scenario 4: Step child
"My step daughter Sophie is 8, born 14 July 2017. She lives with us and we receive child benefit for her."

### Scenario 5: Multiple children
"I have three children: Tom aged 12, Emily aged 9, and baby Oliver who was born last month"

### Scenario 6: Other dependent
"My elderly aunt Dorothy lives with us and is financially dependent on us. She's 81."

## Files Involved

| File | Role |
|------|------|
| `app/Services/AI/XaiToolDefinitions.php` | Tool definition — `create_family_member` |
| `app/Agents/CoordinatingAgent.php` | `handleCreateFamilyMember()` — validation + field mapping |
| `app/Http/Requests/StoreFamilyMemberRequest.php` | Backend validation |
| `app/Http/Controllers/Api/FamilyMembersController.php` | `store()` — handles spouse linking |
| `resources/js/components/UserProfile/FamilyMemberFormModal.vue` | Form with AI fill watchers |
| `resources/js/components/UserProfile/FamilyMembers.vue` | Parent — opens modal, handles save |
