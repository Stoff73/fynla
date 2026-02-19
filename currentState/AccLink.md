# Account Linking & Spouse Logic - Complete System Map

## 1. System Overview

The Fynla application implements a **dual-layer spouse/joint ownership system**:

1. **Account Linking** - Two User records linked bidirectionally via `spouse_id` on the `users` table, enabling data sharing, combined calculations, and household financial planning.
2. **Joint Ownership** - Individual asset records (properties, savings, investments, etc.) that can have a `joint_owner_id` field pointing to a second user, with an `ownership_percentage` determining each owner's share.

These two layers are **independent but complementary**: a property can be jointly owned by two users who are not spouse-linked, and two spouse-linked users can have individually-owned assets.

---

## 2. Database Schema

### 2.1 Users Table - Spouse Fields

| Column | Type | Purpose |
|--------|------|---------|
| `spouse_id` | `bigint unsigned nullable FK(users.id)` | Links to the spouse's User record |
| `marital_status` | `varchar` | Values: `single`, `married`, `divorced`, `widowed`, `civil_partnership`, `separated` |
| `is_primary_account` | `boolean` | Indicates the "primary" user in a couple (the one who registered first) |
| `household_id` | `bigint unsigned nullable FK(households.id)` | Groups users into a household unit |

**Bidirectional linking**: When User A (id=1) links to User B (id=2):
- `users[1].spouse_id = 2`
- `users[2].spouse_id = 1`
- Both set to `marital_status = 'married'`

### 2.2 Households Table

```
households
  id             bigint PK
  household_name varchar nullable
  notes          text nullable
  created_at     timestamp
  updated_at     timestamp
```

The Household model has relationships to: `users`, `familyMembers`, `properties`, `businessInterests`, `chattels`, `cashAccounts`, `investmentAccounts`, `trusts`.

**Current state**: Households exist in the schema and model but are lightly used. Most cross-user logic relies on `spouse_id` rather than `household_id`. The household concept is scaffolded for future multi-person household support beyond married couples.

### 2.3 Family Members Table

```
family_members
  id                        bigint PK
  user_id                   bigint FK(users.id)
  household_id              bigint nullable FK(households.id)
  relationship              ENUM('spouse','child','parent','other_dependent')
  name                      varchar
  first_name                varchar nullable
  middle_name               varchar nullable
  last_name                 varchar nullable
  date_of_birth             date nullable
  gender                    varchar nullable
  national_insurance_number varchar nullable
  annual_income             decimal(15,2) nullable
  is_dependent              boolean default false
  education_status          varchar nullable
  receives_child_benefit    boolean nullable
  notes                     text nullable
```

**Dual representation**: A spouse exists in **two places** simultaneously:
1. As a `User` record (with their own login, assets, etc.)
2. As a `FamilyMember` record under the other user (with `relationship = 'spouse'`)

When spouses are linked, **reciprocal family member records** are created in both directions:
- User A gets a family_member with relationship='spouse' pointing to User B's data
- User B gets a family_member with relationship='spouse' pointing to User A's data

### 2.4 Spouse Permissions Table

```
spouse_permissions
  id            bigint PK
  user_id       bigint FK(users.id)     -- Requester
  spouse_id     bigint FK(users.id)     -- Approver
  status        varchar                  -- 'pending', 'accepted', 'rejected'
  requested_at  datetime nullable
  responded_at  datetime nullable
  created_at    timestamp
  updated_at    timestamp
```

### 2.5 Joint Ownership Fields on Asset Tables

The following tables share a common joint ownership pattern:

| Table | Value Column | Has `joint_owner_id` | Has `ownership_type` | Has `ownership_percentage` | Has `joint_owner_name` | Uses `HasJointOwnership` Trait |
|-------|-------------|---------------------|---------------------|---------------------------|----------------------|-------------------------------|
| `properties` | `current_value` | Yes | Yes | Yes | Yes | Yes |
| `mortgages` | `outstanding_balance` | Yes | Yes | Yes | Yes | Yes |
| `savings_accounts` | `current_balance` | Yes | Yes | Yes | No | Yes |
| `investment_accounts` | `current_value` | Yes | Yes | Yes | No | Yes |
| `business_interests` | `current_valuation` | Yes | Yes | Yes | No | Yes |
| `chattels` | `current_value` | Yes | Yes | Yes | Yes | Yes |
| `goals` | `current_amount` | Yes | Yes | Yes | No | Yes |
| `life_events` | N/A | Yes | Yes | Yes | No | No |
| `cash_accounts` | `current_balance` | No* | Yes | Yes | No | No |

*`cash_accounts` uses `household_id` and `ownership_type`/`ownership_percentage` but does **not** have a `joint_owner_id` column.

**Ownership type ENUM values**: `individual`, `joint`, `tenants_in_common`, `trust`

**Ownership percentage**: Represents the **primary owner's** share. Default 100 for individual, default 50 for joint. The joint owner's share = `100 - ownership_percentage`.

### 2.6 Joint Owner Indexes

Migration `2026_01_26_150000_add_joint_owner_indexes` adds indexes on `joint_owner_id` for: `properties`, `mortgages`, `savings_accounts`, `investment_accounts`, `business_interests`, `chattels`. Also a composite index `goals(joint_owner_id, status)`.

### 2.7 Joint Account Logs Table

```
joint_account_logs
  id              bigint PK
  user_id         bigint FK(users.id)      -- Who made the edit
  joint_owner_id  bigint FK(users.id)      -- The affected joint owner
  loggable_type   varchar                   -- Polymorphic: Property, Mortgage, InvestmentAccount, SavingsAccount
  loggable_id     bigint                    -- ID of the asset
  action          varchar                   -- 'created', 'updated', 'deleted', 'value_updated'
  changes         json nullable             -- What changed
  created_at      timestamp
  updated_at      timestamp
```

### 2.8 Letters to Spouse Table

```
letters_to_spouse
  id                        bigint PK
  user_id                   bigint FK(users.id)
  immediate_actions         text nullable
  executor_name             varchar nullable
  executor_contact          varchar nullable
  ... (25+ fields covering immediate actions, account access, long-term plans, funeral wishes)
  additional_boxes          json nullable
```

---

## 3. Backend Architecture

### 3.1 User Model Spouse Relationships

**File**: `app/Models/User.php`

```php
// Primary spouse relationship
public function spouse(): BelongsTo
{
    return $this->belongsTo(User::class, 'spouse_id');
}

// Spouse permission requests sent by this user
public function spousePermissionRequests(): HasMany
{
    return $this->hasMany(SpousePermission::class, 'user_id');
}

// Spouse permission requests received by this user
public function receivedSpousePermissions(): HasMany
{
    return $this->hasMany(SpousePermission::class, 'spouse_id');
}

// Letter to spouse
public function letterToSpouse(): HasOne
{
    return $this->hasOne(LetterToSpouse::class);
}
```

**`hasAcceptedSpousePermission()` method** (lines 502-528): Determines if data sharing is enabled. Has a **dual-path logic**:

1. **Automatic path** (primary): If both users have `marital_status = 'married'` AND `spouse_id` points to each other, data sharing is automatically enabled. No SpousePermission record needed.
2. **Explicit path** (legacy fallback): Checks the `spouse_permissions` table for an `accepted` record in either direction.

This was designed to fix a persistent issue where spouse data didn't display in the Estate module even though accounts were linked during onboarding.

### 3.2 HasJointOwnership Trait

**File**: `app/Traits/HasJointOwnership.php`

Used by: `Property`, `SavingsAccount`, `InvestmentAccount`, `BusinessInterest`, `Chattel`, `Mortgage`, `Goal`

Provides three query scopes:
- `scopeForUserOrJoint($userId)` - Records where user is owner **or** joint owner
- `scopeForUser($userId)` - Records where user is primary owner only
- `scopeForJointOwner($userId)` - Records where user is joint owner only

Plus helper methods:
- `isOwnedBy($userId)` - Whether user has any ownership
- `hasJointOwner()` - Whether a joint_owner_id is set

### 3.3 CalculatesOwnershipShare Trait

**File**: `app/Traits/CalculatesOwnershipShare.php`

Used by: `EstateAssetAggregatorService`

Core method `calculateUserShare($asset, $userId)`:
- **Individual/Trust**: Primary owner gets 100%
- **Joint/Tenants-in-Common**: Primary owner gets `ownership_percentage` (defaults to 50 if set to 100), joint owner gets `100 - ownership_percentage`
- **Business interests** (special case): `ownership_percentage` always applies regardless of ownership_type (represents shareholding percentage)

Also provides:
- `calculateUserMortgageShare()` - Same logic for mortgage liabilities
- `userOwnsAsset()` / `isPrimaryOwner()` / `isSharedOwnership()` / `getFullValue()` helpers

### 3.4 SpousePermission Model

**File**: `app/Models/SpousePermission.php`

Simple model with:
- `user_id` (requester), `spouse_id` (approver)
- `status`: `pending` | `accepted` | `rejected`
- `requested_at`, `responded_at` timestamps
- Helper methods: `isAccepted()`, `isPending()`

---

## 4. Spouse Account Linking Flow

### 4.1 Via Family Members Controller (Primary Path)

**File**: `app/Http/Controllers/Api/FamilyMembersController.php`

The `store()` method detects when `relationship === 'spouse'` and `email` is provided, then delegates to `handleSpouseCreation()`.

**Two scenarios**:

#### Scenario A: Spouse Already Has Account
1. Look up user by email
2. Validate: not self, not already linked to different user
3. If already linked to current user, just ensure family member record exists
4. **Link accounts bidirectionally**:
   - Set `spouse_id` on both users
   - Set `marital_status = 'married'` on both
   - Copy address from current user to spouse if spouse has none
   - Update spouse income from family member data
5. **Create bidirectional SpousePermission records** (both with `status = 'accepted'`)
6. **Create reciprocal FamilyMember records** in both directions
7. **Clear protection analysis cache** for both users
8. **Send `SpouseAccountLinked` email** to the spouse

#### Scenario B: Spouse Has No Account
1. **Create new User** for spouse:
   - Generate random 16-char temporary password
   - Set `must_change_password = true`
   - Set `is_primary_account = false`
   - Copy address from current user
   - Set `spouse_id` pointing to current user
   - Set `marital_status = 'married'`
   - Set same `household_id`
2. Update current user's `spouse_id` and `marital_status`
3. Create FamilyMember records in both directions
4. **Send `SpouseAccountCreated` email** with temporary password

### 4.2 Via Onboarding Controller

**File**: `app/Http/Controllers/Api/OnboardingController.php` method `handleSpouseLinking()`

During onboarding step that handles family members:
1. If a family member with `relationship = 'spouse'` has an email, attempt linking
2. Same two scenarios as FamilyMembersController but as a simpler, silent flow
3. Also creates bidirectional SpousePermission records with `status = 'accepted'`
4. Creates reciprocal FamilyMember records
5. Clears protection analysis cache

### 4.3 Unlinking (Deleting Spouse)

**File**: `FamilyMembersController::destroy()`

When a spouse family member is deleted:
1. Find the spouse User record
2. Delete the reciprocal family_member record on spouse's account
3. Clear `spouse_id` on both users
4. Delete the family member record

**Note**: This does NOT delete the spouse User account, only severs the link. It also does NOT clean up SpousePermission records (vulnerability).

---

## 5. Spouse Data Sharing Permission System

### 5.1 API Routes

```
GET    /api/spouse-permission/status   - Check current status
POST   /api/spouse-permission/request  - Request permission
POST   /api/spouse-permission/accept   - Accept request
POST   /api/spouse-permission/reject   - Reject request
DELETE /api/spouse-permission/revoke   - Revoke permission
```

All protected by `auth:sanctum` middleware.

### 5.2 SpousePermissionController

**File**: `app/Http/Controllers/Api/SpousePermissionController.php`

**`status()`**: Returns one of three states:
1. **No spouse**: User has no `spouse_id` and no spouse family member
2. **Spouse with linked account**: Returns spouse data, permission record, and `can_view_spouse_data` flag
3. **Spouse without account** (`requires_account_link = true`): Spouse exists only as a family member, no User record to link to

**`request()`**: Creates a SpousePermission with `status = 'pending'` and sends a `SpousePermissionRequest` notification to the spouse.

**`accept()`/`reject()`**: Updates the pending permission to `accepted`/`rejected`.

**`revoke()`**: Deletes the SpousePermission record entirely (hard delete).

### 5.3 Automatic Data Sharing

When accounts are linked through the FamilyMembersController or Onboarding:
- SpousePermission records are **automatically created** with `status = 'accepted'`
- The `hasAcceptedSpousePermission()` method also **bypasses** the SpousePermission table entirely for married couples with bidirectional `spouse_id` links

This means in practice, data sharing is always enabled immediately upon account linking. The explicit permission request/accept flow exists for edge cases or manual control.

---

## 6. Joint Ownership Pattern Across Modules

### 6.1 Single-Record Architecture

The application uses a **single database record per asset** that stores the FULL value:

```
Property: current_value = 400,000 (FULL property value)
          user_id = 1 (primary owner)
          joint_owner_id = 2 (joint owner)
          ownership_type = 'joint'
          ownership_percentage = 60 (User 1 gets 60%)
```

User 1's share = 400,000 * 60% = 240,000
User 2's share = 400,000 * 40% = 160,000

**Query pattern**: `WHERE user_id = ? OR joint_owner_id = ?`

This avoids data duplication and ensures both owners always see the same asset data.

### 6.2 Properties

**Model**: `app/Models/Property.php` (uses `HasJointOwnership` trait)

Additional fields:
- `joint_ownership_type`: `joint_tenancy` | `tenants_in_common` (UK legal distinction)
- `joint_owner_name`: Free-text fallback when joint owner is not a system user
- `trust_id` / `trust_name`: For trust-held properties

**Controller** (`PropertyController`): All CRUD operations use `WHERE user_id = ? OR joint_owner_id = ?`. When ownership changes from joint to individual, clears `joint_owner_id` and sets `ownership_percentage = 100`. Defaults joint ownership_percentage to 50 if not specified.

**Equity calculation** (`getEquityAttribute()`): Values are already stored as user's share in the database, so equity = current_value - mortgages (no further percentage calculation needed).

### 6.3 Mortgages

**Model**: `app/Models/Mortgage.php` (uses `HasJointOwnership` trait)

Mirrors the property ownership pattern. Has `joint_owner_id`, `ownership_type`, `ownership_percentage`, `joint_owner_name`.

### 6.4 Savings Accounts

**Model**: `app/Models/SavingsAccount.php` (uses `HasJointOwnership` trait)

Has `joint_owner_id`, `ownership_type`, `ownership_percentage`. Account numbers are **encrypted** at rest using Laravel's `Crypt::encryptString()`.

**Controller** (`SavingsController`): Uses `forUserOrJoint` scope. When updating or deleting, invalidates cache for **both** the primary owner and joint owner.

### 6.5 Investment Accounts

**Model**: `app/Models/Investment/InvestmentAccount.php` (uses `HasJointOwnership` trait)

Has `joint_owner_id`, `ownership_type`, `ownership_percentage`, `household_id`, `trust_id`.

**Controller** (`InvestmentController`): Enforces ISAs must be `individual` ownership (UK tax law - ISAs cannot be jointly held). When creating or updating, clears agent cache for both owners.

### 6.6 Business Interests

**Model**: `app/Models/BusinessInterest.php` (uses `HasJointOwnership` trait)

Has `joint_owner_id`, `ownership_type`, `ownership_percentage`. Special calculation: `ownership_percentage` always represents shareholding percentage regardless of ownership type (e.g., 60% shareholding in an individually-owned company).

### 6.7 Chattels

**Model**: `app/Models/Chattel.php` (uses `HasJointOwnership` trait)

Has `joint_owner_id`, `joint_owner_name`, `ownership_type`, `ownership_percentage`. The `joint_owner_name` field allows naming a joint owner who is not a system user.

### 6.8 Goals

**Model**: `app/Models/Goal.php` (uses `HasJointOwnership` trait)

Has `joint_owner_id`, `ownership_type`, `ownership_percentage`, `show_in_household_view`.

Goals can be linked to savings accounts via `linked_savings_account_id` and `linked_account_ids` (JSON array). The `isJoint()` helper checks both `ownership_type === 'joint'` and `joint_owner_id !== null`.

### 6.9 Life Events

Has `joint_owner_id`, `ownership_type`, `ownership_percentage`. Does not use the `HasJointOwnership` trait.

### 6.10 Cash Accounts

**Model**: `app/Models/CashAccount.php`

Has `ownership_type` and `ownership_percentage` but does **not** have `joint_owner_id`. Uses `household_id` for grouping instead. Does **not** use `HasJointOwnership` trait.

---

## 7. Estate Planning - Spouse Integration

### 7.1 IHT Calculation Service

**File**: `app/Services/Estate/IHTCalculationService.php`

The most sophisticated spouse-aware service. The `calculate()` method accepts three parameters:
- `$user` - Primary user
- `$spouse` - Optional spouse User object
- `$dataSharingEnabled` - Whether to include spouse assets

**Key spouse-aware calculations**:

1. **Asset aggregation**: If married + data sharing, gathers assets from both users
2. **Liability aggregation**: Same dual-user pattern
3. **Nil Rate Band (NRB)**:
   - Married: Double NRB (2 x 325,000 = 650,000)
   - Widowed with transfer: Own NRB + transferred NRB from deceased spouse
   - Single: Standard NRB (325,000)
4. **Residence Nil Rate Band (RNRB)**:
   - Married: Double RNRB (2 x 175,000 = 350,000)
   - Widowed: Own RNRB + transferred RNRB (`rnrb_transferred_from_spouse` field on iht_profiles)
   - Checks if **either** user/spouse owns a main residence
5. **Projected values at death**:
   - For married couples, projects to **second death** (whoever lives longer)
   - Uses actuarial life tables for both user and spouse
   - Year-by-year integrated cash/investment drawdown model
   - Income/expenses calculated for both users combined

### 7.2 Estate Asset Aggregator Service

**File**: `app/Services/Estate/EstateAssetAggregatorService.php`

Uses `CalculatesOwnershipShare` trait. All asset queries use the `WHERE user_id = ? OR joint_owner_id = ?` pattern:
- Investment accounts, properties, savings, business interests, chattels
- Each asset mapped to a standardised object with `current_value` = user's calculated share
- Mortgages and liabilities also queried with joint owner pattern

### 7.3 Estate Agent

**File**: `app/Agents/EstateAgent.php`

Eager loads `spouse` relationship in the `analyze()` method. Uses spouse presence for:
- Estate health score: Deducts 5 points if married but no linked spouse account
- Profile data: Reports `has_spouse` flag
- Scenario building: Loads spouse relationship for what-if calculations

### 7.4 IHT Profile - Transferred Allowances

For widowed users, the `iht_profiles` table stores:
- `nrb_transferred_from_spouse` - NRB transferred from deceased spouse's estate
- `rnrb_transferred_from_spouse` - RNRB transferred from deceased spouse's estate

These are used in IHT calculations to give the surviving spouse additional allowances.

---

## 8. Cross-Module Spouse Awareness

### 8.1 Protection Module

No direct spouse references in `ProtectionAgent`. However:
- Cache is invalidated for **both users** when spouse linking occurs (`protection_analysis_{userId}`)
- FamilyMember records (including spouse) affect protection needs calculations

### 8.2 Savings Module

No direct spouse references in `SavingsAgent`. Joint ownership handled at the controller level through `forUserOrJoint` queries.

### 8.3 Investment Module

No direct spouse references in `InvestmentAgent`. Joint ownership handled at the controller level. Cache cleared for both owners on updates.

### 8.4 Retirement Module

No direct spouse references in `RetirementAgent`. However, the IHT service uses retirement profiles from **both** users when projecting post-retirement income/expenses.

### 8.5 Goals Module

No direct spouse references in `GoalsAgent`. Goals support joint ownership through `joint_owner_id` and `ownership_type` fields.

### 8.6 Coordinating Agent

No direct spouse references. The CoordinatingAgent orchestrates across modules but does not have spouse-specific logic.

### 8.7 Net Worth Calculations

The frontend `netWorth` store has `spouseOverview` state that gets populated from `response.spouse_data` in the API response. This allows the dashboard to show combined household net worth.

---

## 9. Expenditure Sharing System

### 9.1 Two Modes

When a user has a linked spouse, expenditure can be tracked in two modes:

1. **Joint mode** (default): Total household expenditure is split 50/50. Both user and spouse store their 50% share.
2. **Separate mode**: Each user tracks their own individual expenditure.

### 9.2 Onboarding Controller

**File**: `app/Http/Controllers/Api/OnboardingController.php`

The `saveExpenditureData()` method:
- **Joint mode**: Divides all expenditure categories by 2, stores halved values on both users
- **Separate mode**: Stores `userData` on current user, `spouseData` on spouse

The `getExistingData()` method for the expenditure step:
- Checks if spouse also has expenditure data
- If both have data, returns in `{ userData, spouseData }` format with `expenditure_sharing_mode: 'separate'`
- If only user has data, returns single user format

### 9.3 Preview Seeder

**File**: `database/seeders/PreviewUserSeeder.php`

For married personas, the seeder:
- Creates spouse as a separate preview user with `preview_persona_id = "{personaId}_spouse"`
- Expenditure: Each spouse gets 50% of household expenditure (joint mode default)
- Links spouse_id bidirectionally
- Passes spouse to all create methods: properties, mortgages, savings, investments, pensions, insurance, liabilities, risk profiles, retirement profiles, wills, gifts, IHT profiles, chattels, goals, life events, letter to spouse

---

## 10. Frontend Architecture

### 10.1 Spouse Permission Store

**File**: `resources/js/store/modules/spousePermission.js`

State: `hasSpouse`, `spouse`, `permission`, `canViewSpouseData`, `requiresAccountLink`, `message`

Key getter: `hasSpouse` - Returns false for widowed/divorced users even if `spouse_id` exists.

Actions: `fetchPermissionStatus`, `requestPermission`, `acceptPermission`, `rejectPermission`, `revokePermission`

### 10.2 Spouse Permission Service

**File**: `resources/js/services/spousePermissionService.js`

Simple API wrapper for the five spouse-permission endpoints.

### 10.3 SpouseDataSharing Component

**File**: `resources/js/components/UserProfile/SpouseDataSharing.vue`

Displays in the user profile settings. Shows one of five states:
1. **No spouse** - Prompt to add spouse in Family Members
2. **Spouse without account** - Shows "Account Link Required" info box
3. **No permission request** - Shows "Request Data Sharing Permission" button
4. **Pending** (sent by user) - Shows "Waiting for spouse" with cancel button
5. **Pending** (received by user) - Shows accept/reject buttons
6. **Accepted** - Shows "Data Sharing Enabled" with revoke button
7. **Rejected** - Shows rejection notice

### 10.4 Joint Ownership in Vue Components

29 components reference `joint_owner`, `ownership_type`, or `ownership_percentage`:

**Forms** (where users set ownership):
- `PropertyForm.vue` / `Property/PropertyForm.vue`
- `SaveAccountModal.vue` (savings)
- `AccountForm.vue` (investments)
- `BusinessInterestForm.vue`
- `ChattelFormModal.vue`
- `AssetForm.vue` (estate)
- `StandardInvestmentFields.vue`

**Display** (where ownership is shown):
- `PropertyDetailInline.vue`, `PropertyCard.vue`, `PropertyFinancials.vue`, `PropertyList.vue`
- `InvestmentList.vue`, `InvestmentDetailInline.vue`, `PortfolioOverview.vue`
- `BusinessInterestCard.vue`, `BusinessInterestDetailInline.vue`
- `ChattelCard.vue`, `ChattelDetailInline.vue`
- `CurrentSituation.vue` (savings)
- `IHTLiabilityBreakdown.vue`, `IHTAssetBreakdown.vue`

**Other**:
- `LetterToSpouse.vue` - Estate planning letter
- `ExpenditureForm.vue` / `ExpenditureExpandableGridRow.vue` - Joint/separate expenditure
- `AssetsStep.vue` / `IncomeStep.vue` - Onboarding with ownership

### 10.5 Estate Store - Spouse Handling

**File**: `resources/js/store/modules/estate.js`

Handles the case where married users don't have a linked spouse:
- If `response.requires_spouse_link` is true, falls back to `user_iht_calculation`
- Otherwise uses the full combined calculation

---

## 11. Joint Account Activity Logging

### 11.1 JointAccountLog Model & Controller

**File**: `app/Http/Controllers/Api/JointAccountLogController.php`

When a jointly-owned asset is modified, a `JointAccountLog` record is created capturing:
- Who made the edit (`user_id`)
- Which joint owner is affected (`joint_owner_id`)
- What asset was changed (polymorphic `loggable_type`/`loggable_id`)
- What action occurred (`created`, `updated`, `deleted`, `value_updated`)
- What changed (`changes` JSON field)

**API**: `GET /api/joint-account-logs?type={property|mortgage|investment|savings}`

Logs are displayed with contextual messages like "You updated your spouse's property" vs "Your spouse updated your investment".

---

## 12. Email Notifications

### 12.1 SpouseAccountLinked

**File**: `app/Mail/SpouseAccountLinked.php`

Sent when an existing user's account is linked to a spouse. Uses the `emails.spouse-account-linked` blade template. Contains references to both the `$spouse` and `$linkedBy` users.

### 12.2 SpouseAccountCreated

**File**: `app/Mail/SpouseAccountCreated.php`

Sent when a new user account is created for a spouse during the family member creation flow. Contains the temporary password.

### 12.3 SpousePermissionRequest

**File**: `app/Notifications/SpousePermissionRequest.php`

Notification sent when one spouse requests data sharing permission. Contains a link to `/settings/spouse-permission`.

---

## 13. Preview Personas - Spouse Configuration

| Persona | Primary | Spouse | Spouse Status |
|---------|---------|--------|--------------|
| young_family | James Carter | Emily Carter | Active spouse |
| peak_earners | David Mitchell | Sarah Mitchell | Active spouse |
| widow | Margaret Thompson | (none) | Deceased (not created) |
| entrepreneur | Alex Chen | (none) | Single |
| young_saver | John Morgan | (none) | Single |
| retired_couple | Robert Williams | Patricia Williams | Active spouse |

For personas with spouses:
- Spouse created as separate User with `preview_persona_id = "{persona}_spouse"`
- Bidirectional `spouse_id` linking
- 50/50 expenditure split
- Joint ownership on shared properties, mortgages
- Separate pensions, ISAs (UK tax rules)

---

## 14. Data Flow Diagrams

### 14.1 Spouse Account Linking Flow

```
User adds spouse in Family Members with email
    |
    v
FamilyMembersController::handleSpouseCreation()
    |
    +-- Spouse account exists? --YES--> Link accounts:
    |                                    - Set spouse_id bidirectionally
    |                                    - Set marital_status = 'married'
    |                                    - Copy address if needed
    |                                    - Create SpousePermission (accepted) x2
    |                                    - Create FamilyMember x2
    |                                    - Clear protection caches
    |                                    - Send SpouseAccountLinked email
    |
    +-- Spouse account exists? --NO---> Create spouse User:
                                         - Random password, must_change_password
                                         - is_primary_account = false
                                         - Link spouse_id bidirectionally
                                         - Same household_id
                                         - Create FamilyMember x2
                                         - Send SpouseAccountCreated email
```

### 14.2 Joint Asset Query Flow

```
User requests their properties
    |
    v
PropertyController::index()
    |
    v
Property::where('user_id', $id)
    ->orWhere('joint_owner_id', $id)
    |
    v
For each property:
    CalculatesOwnershipShare::calculateUserShare()
        |
        +-- ownership_type = 'individual'? --> 100% to user_id
        +-- ownership_type = 'joint'?      --> user_id gets ownership_%, joint_owner gets (100-%)
        +-- ownership_type = 'trust'?      --> 100% to user_id (trustee)
    |
    v
Return with user_share calculated
```

### 14.3 IHT Calculation with Spouse

```
IHTCalculationService::calculate($user, $spouse, $dataSharingEnabled)
    |
    +-- Gather user assets (properties, investments, savings, business, chattels, pensions)
    +-- IF married + data sharing: Gather spouse assets separately
    |
    +-- Calculate user liabilities (mortgages + liabilities with joint owner queries)
    +-- IF married + data sharing: Calculate spouse liabilities
    |
    +-- Combined net estate = (user assets + spouse assets) - (user liabilities + spouse liabilities)
    |
    +-- NRB: married = 2x, widowed = 1x + transferred, single = 1x
    +-- RNRB: Check EITHER user/spouse owns main_residence
    +-- Charitable rate: Check if 10%+ to charity for 36% rate
    |
    +-- Projected values:
    |   +-- Married: project to SECOND DEATH (max life expectancy of both)
    |   +-- Year-by-year cash/investment integrated drawdown
    |   +-- Both users' income, expenses, pensions included
    |
    +-- Save calculation to iht_calculations table
```

---

## 15. API Routes Summary

### Spouse Permission Routes
```
GET    /api/spouse-permission/status
POST   /api/spouse-permission/request
POST   /api/spouse-permission/accept
POST   /api/spouse-permission/reject
DELETE /api/spouse-permission/revoke
```

### Spouse Data Access Routes
```
GET    /api/users/{userId}                    -- Get spouse user data
PUT    /api/users/{userId}/expenditure        -- Update spouse expenditure
```

### Joint Account Logs
```
GET    /api/joint-account-logs?type={type}    -- Get joint account activity
```

### Asset Routes (all support joint ownership)
```
GET/POST/PUT/DELETE  /api/properties/*
GET/POST/PUT/DELETE  /api/savings/*
GET/POST/PUT/DELETE  /api/investments/*
GET/POST/PUT/DELETE  /api/business-interests/*
GET/POST/PUT/DELETE  /api/chattels/*
GET/POST/PUT/DELETE  /api/mortgages/*
GET/POST/PUT/DELETE  /api/goals/*
```

---

## 16. Vulnerabilities & Issues

### 16.1 Critical

1. **Spouse unlinking does not clean up SpousePermission records**: When `FamilyMembersController::destroy()` removes a spouse, it clears `spouse_id` on both users and deletes family member records, but does NOT delete SpousePermission records. Stale accepted permissions could theoretically remain.

2. **No authorisation check on spouse data routes**: `GET /api/users/{userId}` and `PUT /api/users/{userId}/expenditure` appear to allow any authenticated user to access any user's data by ID. These should verify the requesting user is the spouse of the target user.

3. **Temporary password in email for new spouse accounts**: When a spouse account is created, the temporary password is sent via email. If email delivery fails, the user is told to use "Forgot Password" - but the temporary password exists in memory during the request. The password is not logged (good), but the pattern of auto-creating accounts with emailed passwords has inherent risk.

### 16.2 High

4. **CashAccount lacks joint_owner_id**: Unlike all other asset types, `CashAccount` has no `joint_owner_id` column. It has `ownership_type` and `ownership_percentage` but no way to link to a specific joint owner. This is inconsistent with the rest of the system.

5. **EstateController has no spouse awareness**: The `EstateController` contains no references to `spouse`, `hasAcceptedSpousePermission`, or `data_sharing`. The IHT calculation service supports spouse data, but it's unclear how the controller passes spouse data to it. The controller may not be utilising the full spouse-aware calculation path.

6. **No race condition protection on spouse linking**: If two users simultaneously try to link to the same spouse, both could succeed. The `handleSpouseCreation` method checks `$spouseUser->spouse_id` but there's no database-level uniqueness constraint on spouse relationships.

### 16.3 Medium

7. **Inconsistent joint ownership on Life Events**: Life events have `joint_owner_id`, `ownership_type`, `ownership_percentage` columns but do NOT use the `HasJointOwnership` trait, meaning the `forUserOrJoint` scope is unavailable.

8. **FamilyMember model has no link to User**: The `FamilyMember` for a spouse stores basic info (name, DOB) but has no foreign key to the spouse's User record. The email is fetched separately by looking up `User::find($user->spouse_id)`. This means family member data can drift from the actual User data.

9. **50/50 expenditure split assumption**: In joint mode, expenditure is always split 50/50. This may not reflect reality where one spouse earns significantly more or where expenditure is not evenly shared.

10. **Ownership percentage defaults**: When creating a joint asset without specifying ownership_percentage, the system defaults to 50%. Some controllers auto-correct 100% to 50% for joint assets, but this silent modification could confuse users.

11. **No agents (except Estate) are spouse-aware**: ProtectionAgent, SavingsAgent, InvestmentAgent, RetirementAgent, GoalsAgent, and CoordinatingAgent contain zero references to spouse. All spouse logic lives in controllers and the IHT service. This means module-level analysis doesn't consider the household holistically (except Estate).

### 16.4 Low

12. **Dual admin mechanism for spouse data**: The `hasAcceptedSpousePermission()` method has two paths (automatic + explicit). If the automatic path ever fails (e.g., one user changes marital_status), the fallback to SpousePermission records may not exist if they were never created (for newer accounts).

13. **Joint account logs are read-only**: Users can view joint account logs but cannot acknowledge or dismiss them. Over time, the log table will grow without any cleanup mechanism.

14. **No notification when joint asset is modified**: While JointAccountLog records are created, there's no push notification or email sent to the joint owner when their co-owned asset is modified by the other owner.

---

## 17. Improvement Recommendations

### 17.1 Critical Fixes

1. **Add authorisation to spouse data routes**: Verify `$request->user()->spouse_id === $userId` before allowing access to `/api/users/{userId}`.

2. **Clean up SpousePermission on unlinking**: When destroying a spouse family member, also delete all SpousePermission records between the two users.

3. **Add database constraint for spouse uniqueness**: Consider a CHECK constraint or application-level mutex to prevent race conditions in spouse linking.

### 17.2 Architectural Improvements

4. **Add `joint_owner_id` to CashAccount**: Bring CashAccount in line with all other asset models for consistent joint ownership support.

5. **Make Life Events use HasJointOwnership trait**: Add the trait for consistent query scope behaviour.

6. **Add spouse awareness to all Agents**: At minimum, the CoordinatingAgent should be spouse-aware, pulling household-level data for its cross-module analysis. Protection and Retirement agents would benefit most from spouse data.

7. **Add foreign key from FamilyMember to User for spouse**: Add a `linked_user_id` column to `family_members` that links a spouse FamilyMember record to their User record, preventing data drift.

### 17.3 Feature Enhancements

8. **Real-time notifications for joint asset changes**: Send email/in-app notification to the joint owner when a co-owned asset is modified.

9. **Configurable expenditure split**: Allow couples to set their own expenditure split ratio rather than forcing 50/50.

10. **Household-level dashboard view**: Create a dedicated household view showing combined assets, liabilities, and net worth for both users side by side.

11. **Divorce/separation handling**: Implement a clean separation flow that handles: splitting joint assets, removing spouse links, adjusting ownership percentages, and recalculating individual financial positions.

---

## 18. File Reference Index

### Backend - Models
| File | Purpose |
|------|---------|
| `app/Models/User.php` | Spouse relationship, hasAcceptedSpousePermission() |
| `app/Models/Household.php` | Household grouping model |
| `app/Models/FamilyMember.php` | Family member with spouse relationship type |
| `app/Models/SpousePermission.php` | Data sharing permission model |
| `app/Models/Property.php` | Joint ownership with HasJointOwnership |
| `app/Models/SavingsAccount.php` | Joint ownership with HasJointOwnership |
| `app/Models/Investment/InvestmentAccount.php` | Joint ownership with HasJointOwnership |
| `app/Models/BusinessInterest.php` | Joint ownership with HasJointOwnership |
| `app/Models/Chattel.php` | Joint ownership with HasJointOwnership |
| `app/Models/Mortgage.php` | Joint ownership with HasJointOwnership |
| `app/Models/Goal.php` | Joint ownership with HasJointOwnership |
| `app/Models/CashAccount.php` | Ownership type/percentage, NO joint_owner_id |
| `app/Models/LetterToSpouse.php` | Estate planning letter to spouse |
| `app/Models/JointAccountLog.php` | Audit log for joint asset changes |

### Backend - Traits
| File | Purpose |
|------|---------|
| `app/Traits/HasJointOwnership.php` | Query scopes: forUserOrJoint, forUser, forJointOwner |
| `app/Traits/CalculatesOwnershipShare.php` | Share calculation: calculateUserShare, calculateUserMortgageShare |

### Backend - Controllers
| File | Purpose |
|------|---------|
| `app/Http/Controllers/Api/FamilyMembersController.php` | Spouse creation/linking/unlinking |
| `app/Http/Controllers/Api/SpousePermissionController.php` | Permission CRUD |
| `app/Http/Controllers/Api/PropertyController.php` | Joint ownership CRUD |
| `app/Http/Controllers/Api/SavingsController.php` | Joint ownership CRUD |
| `app/Http/Controllers/Api/InvestmentController.php` | Joint ownership CRUD |
| `app/Http/Controllers/Api/JointAccountLogController.php` | Activity log |
| `app/Http/Controllers/Api/OnboardingController.php` | Spouse linking during onboarding |

### Backend - Services
| File | Purpose |
|------|---------|
| `app/Services/Estate/IHTCalculationService.php` | Full spouse-aware IHT calculations |
| `app/Services/Estate/EstateAssetAggregatorService.php` | Joint ownership asset/liability aggregation |

### Backend - Email/Notifications
| File | Purpose |
|------|---------|
| `app/Mail/SpouseAccountLinked.php` | Email when existing account linked |
| `app/Mail/SpouseAccountCreated.php` | Email when new spouse account created |
| `app/Notifications/SpousePermissionRequest.php` | Notification for permission request |

### Frontend - Store Modules
| File | Purpose |
|------|---------|
| `resources/js/store/modules/spousePermission.js` | Spouse permission state management |
| `resources/js/store/modules/netWorth.js` | spouseOverview data |
| `resources/js/store/modules/estate.js` | Spouse-link fallback handling |

### Frontend - Services
| File | Purpose |
|------|---------|
| `resources/js/services/spousePermissionService.js` | API wrapper for permission endpoints |

### Frontend - Components
| File | Purpose |
|------|---------|
| `resources/js/components/UserProfile/SpouseDataSharing.vue` | Permission management UI |
| `resources/js/components/UserProfile/LetterToSpouse.vue` | Letter to spouse form |
| 27 additional components | Joint ownership forms and displays |

### Database - Key Migrations
| File | Purpose |
|------|---------|
| `2026_01_26_150000_add_joint_owner_indexes.php` | Indexes on joint_owner_id columns |
| `2026_01_17_092200_add_joint_owner_name_to_chattels_table.php` | joint_owner_name on chattels |
| `2026_01_17_100145_add_tenants_in_common_to_mortgages_ownership_type.php` | Extended ENUM |
| `2026_02_05_150000_add_rnrb_transferred_to_iht_profiles_table.php` | Widow RNRB transfer |
| `2026_01_18_000001_create_goals_table.php` | Goals with joint ownership |
| `2026_02_03_120001_create_life_events_table.php` | Life events with joint ownership |
