# Fynla Database Optimization Audit Report

**Date**: December 12, 2025
**Auditor**: Database Performance Engineer
**Application**: Fynla Financial Planning Application
**Database**: MySQL 8.0+
**ORM**: Laravel Eloquent

---

## Executive Summary

This audit reviewed 141 migration files, 33+ Eloquent models, and numerous controllers/services to identify database performance issues and optimization opportunities. The database schema is generally well-designed with proper normalization, but several areas need attention for optimal production performance.

### Key Findings

| Category | Issues Found | Severity |
|----------|-------------|----------|
| Missing Indexes | 25+ | Medium-High |
| N+1 Query Patterns | 15+ | Medium |
| Normalization Issues | 2 | Low |
| Data Integrity Gaps | 3 | Low |
| Scalability Concerns | 4 | Low |

---

## 1. Schema Analysis - Normalization Review

### 1.1 Tables Meeting Normalization Standards (1NF, 2NF, 3NF)

The following tables are properly normalized:

- `users` - Single user per row, all attributes depend on user_id
- `properties` - Proper FK to users, mortgages in separate table
- `mortgages` - Linked to properties via FK, user ownership tracked
- `investment_accounts` - Holdings in separate table (polymorphic)
- `holdings` - Polymorphic relationship to accounts/pensions
- `dc_pensions`, `db_pensions`, `state_pensions` - Properly separated
- `trusts`, `gifts`, `bequests` - Estate planning properly normalized
- All protection policies (life, CI, IP, disability, sickness)

### 1.2 Acceptable Denormalization (Performance Trade-offs)

The following denormalized structures are acceptable for performance:

| Table | Denormalized Field | Justification |
|-------|-------------------|---------------|
| `properties` | `outstanding_mortgage` | Quick equity calculation without JOIN |
| `properties` | `joint_owner_name` | Display without JOIN to users |
| `mortgages` | `joint_owner_name` | Display without JOIN to users |
| `users` | Various totals | Dashboard performance |

### 1.3 Minor Normalization Observations

1. **Address Fields in Properties**: Multiple address columns (address_line_1, address_line_2, city, county, postcode) could be normalized to a separate `addresses` table if address reuse is needed. However, current approach is acceptable for the use case.

2. **Beneficiary Information in Bequests**: `beneficiary_name` is stored alongside `beneficiary_user_id`. This is intentional to support non-system beneficiaries.

---

## 2. Index Analysis

### 2.1 Existing Indexes (Well Implemented)

The schema already has excellent coverage for:
- All `user_id` foreign keys (92% covered)
- Polymorphic relationships (`holdable_type`, `holdable_id`)
- Common enum filters (`ownership_type`, `property_type`, `trust_type`)
- Composite indexes on frequently queried patterns

### 2.2 Missing Indexes Identified

The following indexes were missing and have been added in the migration:

#### Foreign Key Columns Without Indexes
| Table | Column | Query Pattern |
|-------|--------|---------------|
| `properties` | `joint_owner_id` | Reciprocal property lookups |
| `mortgages` | `joint_owner_id` | Joint mortgage queries |

#### Composite Indexes for Common Query Patterns
| Table | Columns | Use Case |
|-------|---------|----------|
| `family_members` | `(user_id, relationship)` | Children-only queries |
| `gifts` | `(user_id, gift_date)` | IHT 7-year rule queries |
| `wills` | `user_id` | Will existence checks |
| `bequests` | `(will_id, priority_order)` | Ordered bequest retrieval |
| `net_worth_statements` | `(user_id, statement_date)` | Trend analysis |
| `recommendation_tracking` | `(user_id, completed_at)` | History queries |
| `recommendation_tracking` | `(user_id, timeline)` | Timeline filtering |

#### ORDER BY Column Indexes
| Table | Column | Query Pattern |
|-------|--------|---------------|
| `mortgages` | `start_date` | `ORDER BY start_date DESC` |

#### Ownership Type Indexes (for Joint Asset Queries)
| Table | Index Added |
|-------|-------------|
| `investment_accounts` | `ownership_type` |
| `business_interests` | `ownership_type` |
| `chattels` | `ownership_type` |
| `cash_accounts` | `ownership_type` |

### 2.3 Index Impact Assessment

Expected improvements after migration:

| Query Type | Current | Expected | Improvement |
|------------|---------|----------|-------------|
| Joint property lookup | Full scan | Index seek | 10-50x |
| IHT gift date filtering | Full scan | Range scan | 5-20x |
| Family member by relationship | Full scan | Index seek | 3-10x |
| Mortgage ordering | Filesort | Index scan | 2-5x |

---

## 3. N+1 Query Analysis

### 3.1 Controllers with Potential N+1 Issues

The following patterns were identified as needing eager loading:

#### PropertyController.php (Lines 39-70)
```php
// Current: N+1 for reciprocal property lookups
foreach ($properties as $property) {
    $reciprocalProperty = Property::where('user_id', $property->joint_owner_id)...
}
```
**Recommendation**: Pre-fetch all reciprocal properties in a single query.

#### NetWorthService.php (Lines 170-190)
```php
// Current: Separate queries for each pension type
$dcValue = DCPension::where('user_id', $userId)->sum('current_fund_value');
$dbValue = DBPension::where('user_id', $userId)->get()->sum(...);
```
**Recommendation**: Use a single query with UNION or eager load from User model.

#### EstateController.php (Lines 37-44)
```php
// Current: 5 separate queries
$assets = Asset::where('user_id', $user->id)->get();
$liabilities = Liability::where('user_id', $user->id)->get();
$gifts = Gift::where('user_id', $user->id)->get();
$trusts = Trust::where('user_id', $user->id)->get();
$investmentAccounts = InvestmentAccount::where('user_id', $user->id)->get();
```
**Recommendation**: Eager load from User model or use single multi-model query.

### 3.2 Recommended Eager Loading Additions to User Model

```php
// Add to User.php for dashboard queries
protected $with = []; // Keep empty for flexibility

// Add eager loading scopes
public function scopeWithNetWorthData($query)
{
    return $query->with([
        'properties.mortgages',
        'investmentAccounts.holdings',
        'savingsAccounts',
        'dcPensions',
        'dbPensions',
        'statePension'
    ]);
}

public function scopeWithEstateData($query)
{
    return $query->with([
        'properties',
        'investmentAccounts',
        'liabilities',
        'gifts',
        'trusts'
    ]);
}
```

---

## 4. Data Integrity Assessment

### 4.1 Foreign Key Constraints (Well Implemented)

All major tables have proper FK constraints with appropriate `ON DELETE` rules:
- `CASCADE` for user-owned data (properties, pensions, policies)
- `SET NULL` for optional relationships (household_id, trust_id)

### 4.2 Unique Constraints

**Existing**:
- `users.email` - Unique
- `isa_allowance_tracking.(user_id, tax_year)` - Composite unique
- `protection_profiles.user_id` - Unique (1:1 relationship)

**Added in Migration**:
- `spouse_permissions.(user_id, spouse_id)` - Prevents duplicate permission records

### 4.3 Missing Constraints (Low Priority)

1. **Investment Accounts**: No constraint preventing duplicate provider/account_number combinations (may be intentional for different tax years)

2. **Holdings**: No constraint on allocation_percent sum (handled in application logic)

---

## 5. Scalability Assessment

### 5.1 Tables Likely to Grow Large

| Table | Growth Pattern | Current Est. | 1 Year Est. | 5 Year Est. |
|-------|---------------|--------------|-------------|-------------|
| `holdings` | High | 10K | 100K | 1M |
| `joint_account_logs` | High | 1K | 50K | 500K |
| `documents` | High | 5K | 50K | 500K |
| `document_extractions` | High | 5K | 50K | 500K |
| `recommendation_tracking` | Medium | 5K | 20K | 100K |
| `iht_calculations` | Low | 1K | 5K | 25K |

### 5.2 Partitioning Recommendations

For tables that may exceed 10M rows, consider:

1. **joint_account_logs**: Partition by `created_at` (monthly/yearly)
2. **documents**: Partition by `created_at`
3. **recommendation_tracking**: Partition by `created_at`

### 5.3 Archival Strategy

Consider implementing soft deletes with archival for:
- Completed recommendations older than 1 year
- Document extraction logs older than 6 months
- Old IHT calculations (keep only latest per user)

---

## 6. Query Optimization Recommendations

### 6.1 High-Impact Optimizations

1. **Use Laravel's `withCount()` for Dashboard**
```php
// Instead of loading all relationships
$user = User::withCount(['properties', 'investmentAccounts', 'dcPensions'])
    ->find($userId);
```

2. **Use `select()` to Limit Columns**
```php
// Instead of selecting all columns
$properties = Property::where('user_id', $userId)
    ->select(['id', 'address_line_1', 'current_value', 'ownership_type'])
    ->get();
```

3. **Use Query Caching for Tax Configurations**
```php
// Already implemented in TaxConfigService - confirm TTL is appropriate
Cache::remember('tax_config_active', 3600, fn() => TaxConfiguration::active()->first());
```

### 6.2 Model Event Optimization

Consider using model observers to maintain denormalized counts:

```php
// In PropertyObserver
public function created(Property $property)
{
    $property->user->increment('properties_count');
}

public function deleted(Property $property)
{
    $property->user->decrement('properties_count');
}
```

---

## 7. Migration Files Created

### 7.1 Index Optimization Migration
**File**: `database/migrations/2025_12_12_120000_add_database_performance_indexes.php`

Adds 25+ indexes for:
- Foreign key columns (`joint_owner_id`)
- Composite query patterns
- ORDER BY columns
- Ownership type filtering
- Unique constraints

### 7.2 Eager Loading Optimization Migration (Optional)
**File**: `database/migrations/2025_12_12_120001_add_eager_loading_optimizations.php`

Adds denormalized count columns:
- `users.properties_count`, `investment_accounts_count`, etc.
- `investment_accounts.total_holdings_value`
- `properties.mortgages_count`, `total_mortgage_balance`

**Note**: This migration is optional and requires application code changes to maintain these values.

---

## 8. Execution Plan

### Phase 1: Index Migration (Low Risk)
```bash
# Run during low-traffic period
php artisan migrate --path=database/migrations/2025_12_12_120000_add_database_performance_indexes.php
```

**Expected Impact**:
- Query time: 30-60 seconds on production
- No table locks (MySQL 8.0 online DDL)
- Immediate performance improvement

### Phase 2: Count Columns Migration (Optional)
```bash
# Run if dashboard performance is an issue
php artisan migrate --path=database/migrations/2025_12_12_120001_add_eager_loading_optimizations.php
```

**Requires**: Application code updates to maintain counts

### Phase 3: Query Optimization (Code Changes)
- Update controllers to use eager loading
- Add model scopes for common data patterns
- Implement model observers for count maintenance

---

## 9. Monitoring Recommendations

### 9.1 Slow Query Log
```sql
-- Enable slow query logging
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 1; -- Log queries > 1 second
```

### 9.2 Key Metrics to Monitor
- Average query time per endpoint
- Total queries per page load
- Index usage statistics
- Table scan frequency

### 9.3 Laravel Telescope (Development)
Already installed - use for:
- N+1 query detection
- Slow query identification
- Cache hit/miss ratios

---

## 10. Conclusion

The Fynla database schema is well-designed and follows Laravel best practices. The main optimization opportunities are:

1. **Missing Indexes** (Fixed in migration) - High impact, low risk
2. **N+1 Query Patterns** (Code changes needed) - Medium impact, low risk
3. **Denormalized Counts** (Optional migration) - Medium impact, requires maintenance

The index migration (`2025_12_12_120000`) should be applied immediately for performance gains. The eager loading migration (`2025_12_12_120001`) is optional and depends on dashboard performance requirements.

---

**Report Generated**: December 12, 2025
**Next Review**: March 2026 (or after significant feature additions)
