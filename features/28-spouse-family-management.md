# Feature Specification: Cross-Cutting Features - Spouse and Family Management

## Status: Live

## Executive Summary

The Spouse and Family Management feature enables comprehensive management of family relationships including spouse account linking, data sharing permissions, joint asset tracking, and family member records. When spouse accounts are linked, users can share financial data, view combined positions, and automatically synchronise joint assets with full audit trail.

### Elevator Pitch

Link your spouse's account to see your combined financial picture, share data with controlled permissions, and automatically keep joint assets in sync between both accounts.

### Problem Statement

Financial planning for couples requires visibility into combined positions. Without account linking, each spouse sees only their individual data, joint assets may be recorded inconsistently, and household-level planning is impossible.

### Target Audience

- Primary: Married couples wanting combined financial visibility
- Secondary: Couples with joint assets needing synchronisation
- Tertiary: Users managing family member records for estate planning

### Unique Selling Proposition

Comprehensive spouse linking with granular permission controls, automatic synchronisation of joint assets with audit trail, and integrated family member management for protection and estate planning needs.

### Success Metrics

| Metric | Target | Measurement Method |
|--------|--------|-------------------|
| Spouse linking rate | 60% of married users link accounts | Data analysis |
| Permission usage | 80% configure permissions | Feature tracking |
| Joint asset accuracy | 95% joint assets sync correctly | Data validation |
| Family member recording | 70% of users with family record members | Data analysis |

---

## User Personas

### Persona 1: Sarah and James - New to Linking

**Demographics**: Married couple both using Fynla separately

**Goals**:
- Link accounts to see combined view
- Share data selectively
- Keep joint property in sync

**Pain Points**:
- Currently see only individual data
- Joint house recorded separately
- Want household planning

**Success Criteria**: Accounts linked, combined view available, joint assets synced.

### Persona 2: Michael - Spouse Account Creator

**Demographics**: User whose spouse needs a Fynla account

**Goals**:
- Create account for spouse
- Link accounts automatically
- Share relevant data

**Pain Points**:
- Spouse not yet registered
- Wants streamlined process
- Need to control sharing

**Success Criteria**: Spouse account created, automatically linked.

### Persona 3: Emma - Parent Recording Family

**Demographics**: Parent with children needing estate planning

**Goals**:
- Record all children
- Track ages for planning
- Ensure beneficiaries documented

**Pain Points**:
- Multiple children to track
- Ages important for calculations
- Estate planning needs complete family

**Success Criteria**: All family members recorded with dates of birth.

---

## User Stories

### US-01: Link Existing Spouse Account

**As a** married user,
**I want to** link my spouse's existing Fynla account,
**So that** we can share financial data.

**Acceptance Criteria**:
- Given my spouse has a Fynla account
- When I enter their email address
- Then a link request is sent for their approval

**Process**:
1. Enter spouse email
2. System finds existing account
3. Link request sent to spouse
4. Spouse accepts request
5. Accounts are linked

### US-02: Create Spouse Account

**As a** user whose spouse has no account,
**I want to** create an account for them,
**So that** we can link accounts.

**Acceptance Criteria**:
- Given my spouse has no Fynla account
- When I enter their details
- Then an account is created and invitation sent

**Process**:
1. Enter spouse name and email
2. System creates account with random password
3. Welcome email sent to spouse
4. Accounts automatically linked
5. Spouse sets own password on first login

### US-03: Accept Link Request

**As a** user receiving link request,
**I want to** accept or decline the request,
**So that I** control account linking.

**Acceptance Criteria**:
- Given I receive a link request
- When I view the request
- Then I can accept or decline

### US-04: Configure Sharing Permissions

**As a** user with linked spouse,
**I want to** control what data is shared,
**So that I** maintain appropriate privacy.

**Acceptance Criteria**:
- Given accounts are linked
- When I configure permissions
- Then sharing is controlled accordingly

**Permission Types**:
- View permissions (can spouse see my data?)
- Edit permissions (can spouse change my data?)

### US-05: View Combined Financial Position

**As a** user with linked spouse,
**I want to** see combined household position,
**So that I** understand total family wealth.

**Acceptance Criteria**:
- Given accounts linked with view permission
- When I view net worth/IHT
- Then combined figures are shown

### US-06: Create Joint Asset

**As a** user with joint ownership,
**I want to** create joint asset,
**So that** it appears for both owners.

**Acceptance Criteria**:
- Given I am adding joint asset
- When I select joint ownership
- Then asset appears in both accounts

**Joint Asset Types**:
- Properties
- Savings accounts
- Investment accounts
- Mortgages

### US-07: Edit Joint Asset

**As a** user with joint asset,
**I want** changes to sync to spouse,
**So that** both see consistent data.

**Acceptance Criteria**:
- Given I edit joint asset
- When I save changes
- Then spouse's record updates

### US-08: View Joint Account History

**As a** user with joint assets,
**I want to** see change history,
**So that I** know who made changes.

**Acceptance Criteria**:
- Given joint assets have been modified
- When I view Joint History tab
- Then I see audit trail

**History Display**:
- Date and time
- What was changed
- Who made the change
- Previous and new values

### US-09: Add Family Member

**As a** user with family,
**I want to** add family members,
**So that** planning accounts for them.

**Acceptance Criteria**:
- Given I am on Family Members section
- When I add a family member
- Then they are recorded

**Required Fields**:
- Name
- Relationship
- Date of birth

### US-10: Edit Family Member

**As a** user,
**I want to** update family member details,
**So that** information stays current.

### US-11: Remove Family Member

**As a** user,
**I want to** remove family members,
**So that** records stay accurate.

### US-12: Unlink Spouse Account

**As a** user,
**I want to** unlink spouse account,
**So that** accounts become independent.

**Acceptance Criteria**:
- Given accounts are linked
- When I request unlinking
- Then accounts are separated

**Unlinking Effects**:
- Combined views no longer available
- Joint assets remain but no longer sync
- Each keeps their own data

---

## Feature Details

### Account Linking Process

**Scenario 1: Both Have Accounts**
```
User enters spouse email
    |
    v
System finds existing account
    |
    v
Link request created
    |
    v
Email sent to spouse
    |
    v
Spouse logs in, sees request
    |
    v
Spouse accepts
    |
    v
Accounts linked
    |
    v
marital_status set to 'married' for both
```

**Scenario 2: Spouse Has No Account**
```
User enters spouse email (not found)
    |
    v
System offers to create account
    |
    v
User confirms
    |
    v
Account created with random password
    |
    v
Welcome email sent with login link
    |
    v
Accounts automatically linked
    |
    v
marital_status set to 'married' for both
```

### Sharing Permissions

**Permission Structure**:
```
spouse_permissions {
    user_id: owner
    spouse_id: linked spouse
    can_view: boolean
    can_edit: boolean
}
```

**Bidirectional Permissions**:
- Each user sets their own permissions
- User A can allow User B to view
- User B can allow User A to view
- Permissions are independent

### Joint Asset Synchronisation

**On Creation**:
1. User creates asset with joint ownership
2. System creates reciprocal record for spouse
3. Both records linked via joint_asset_id
4. Audit log entry created

**On Edit**:
1. User edits joint asset
2. System syncs to spouse's record
3. Both records updated
4. Audit log entry records who edited

**Ownership Split**:
- Joint (default): 50/50 split
- Tenants in Common: Custom percentage split
- Each sees their share in net worth

### Joint Account Audit Trail

**joint_account_logs table**:
```
{
    id
    joint_asset_type (property, savings, etc.)
    joint_asset_id
    action (created, updated, deleted)
    changed_by_user_id
    changes (JSON of before/after)
    created_at
}
```

### Family Member Relationships

| Relationship | Description | Impact on Planning |
|--------------|-------------|-------------------|
| spouse | Husband/Wife | Account linking, IHT spouse exemption |
| child | Son/Daughter | Protection needs, RNRB eligibility |
| step_child | Step-child | RNRB eligibility |
| parent | Mother/Father | Estate planning |
| other_dependent | Other dependant | Protection needs |

### Date of Birth Requirement

**Why Required**:
- Children's ages affect protection needs calculations
- Ages determine when dependants cease being dependants
- Spouse age affects retirement planning
- Critical for accurate planning

---

## User Flows

### Flow 1: Link Existing Spouse Account

```
User Profile or Onboarding
    |
    v
Family Members Section
    |
    v
Add/Edit Spouse
    |
    v
Enter spouse email: existing@email.com
    |
    v
System finds account
    |
    v
Click "Send Link Request"
    |
    v
Request sent
    |
    v
[Spouse logs in]
    |
    v
Spouse sees pending request
    |
    v
Spouse clicks "Accept"
    |
    v
Accounts linked
    |
    v
Combined views available
```

### Flow 2: Create Spouse Account

```
User Profile or Onboarding
    |
    v
Add Spouse
    |
    v
Enter email: new@email.com
    |
    v
"Account not found - Create account for spouse?"
    |
    v
Enter spouse name
    |
    v
Click "Create and Link"
    |
    v
Account created
    |
    v
Email sent to spouse
    |
    v
Accounts linked automatically
```

### Flow 3: Configure Permissions

```
Linked Account
    |
    v
Settings or Profile
    |
    v
Spouse Permissions
    |
    v
Toggle view permission: On/Off
    |
    v
Toggle edit permission: On/Off
    |
    v
Save
    |
    v
Spouse access updated immediately
```

### Flow 4: Create Joint Property

```
Properties Section
    |
    v
Add Property
    |
    v
Select Ownership: "Joint"
    |
    v
Select Joint Owner: [Linked Spouse]
    |
    v
Enter property details
    |
    v
Save
    |
    v
Property appears in both accounts
    |
    v
Audit log entry created
```

---

## Edge Cases

### EC-01: Same Email Entered

**Scenario**: User enters their own email as spouse.
**Expected Behaviour**: Validation error - cannot link to own account.

### EC-02: Already Linked

**Scenario**: User tries to link when already linked to different spouse.
**Expected Behaviour**: Must unlink current spouse first. Warn about implications.

### EC-03: Link Request Declined

**Scenario**: Spouse declines link request.
**Expected Behaviour**: Request removed. User notified. Can try again later.

### EC-04: Spouse Changes Their Data

**Scenario**: Spouse edits their own (non-joint) data.
**Expected Behaviour**: Changes visible to linked user if view permission granted.

### EC-05: Joint Asset Deleted by One Party

**Scenario**: One owner deletes joint asset.
**Expected Behaviour**: Confirmation required. Removed from both accounts. Audit logged.

### EC-06: Unlink with Joint Assets

**Scenario**: Accounts unlinked but joint assets exist.
**Expected Behaviour**: Joint assets remain in both accounts but no longer sync. Each becomes independent copy.

### EC-07: Family Member Already Exists

**Scenario**: User adds spouse as family member then tries to add again.
**Expected Behaviour**: Prevent duplicate. Allow edit of existing.

---

## Acceptance Criteria Summary

| ID | Criterion | Testable |
|----|-----------|----------|
| AC-01 | Existing spouse account can be linked | Yes |
| AC-02 | New spouse account can be created | Yes |
| AC-03 | Link requests can be accepted/declined | Yes |
| AC-04 | Permissions can be configured | Yes |
| AC-05 | Combined view shows when permitted | Yes |
| AC-06 | Joint assets appear in both accounts | Yes |
| AC-07 | Joint asset edits sync | Yes |
| AC-08 | Audit trail records changes | Yes |
| AC-09 | Family members can be managed | Yes |
| AC-10 | Accounts can be unlinked | Yes |

---

## Dependencies

### Upstream Dependencies

- User authentication
- Email service (for invitations and requests)

### Downstream Dependencies

- Net Worth (combined views)
- IHT Planning (spouse data)
- Protection (family for needs calculation)
- Estate Planning (beneficiaries)

---

## Technical Constraints

1. **Bidirectional Links**: Must maintain link in both directions
2. **Permission Security**: Strict permission checking on all data access
3. **Sync Atomicity**: Joint asset changes must be atomic
4. **Audit Integrity**: All changes must be logged
5. **Email Delivery**: Reliable email for invitations

---

## Non-Functional Requirements

### Performance

- Link operations: Under 2 seconds
- Sync operations: Under 1 second
- Permission check: Under 100ms

### Security

- Permissions enforced at API level
- Cannot access spouse data without permission
- Audit trail tamper-proof

### Data Integrity

- Joint assets consistent in both accounts
- Link state consistent in both directions
- No orphaned link records

---

## UX Considerations

1. **Clear Linking Status**: Always show link status
2. **Permission Visibility**: Clear what is shared
3. **Request Notifications**: Obvious pending requests
4. **Joint Indicators**: Clear badges on joint assets
5. **Audit Access**: Easy access to change history
6. **Unlink Warnings**: Clear consequences of unlinking
7. **Family Member Cards**: Visual cards for family
8. **DOB Requirement**: Explain why needed
