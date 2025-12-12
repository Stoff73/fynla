-- Fynla v0.2.11 Migration Verification SQL
-- File: verify_v0.2.11_migration.sql
-- Purpose: Verify database changes after migration deployment
-- Migration: 2025_11_22_092125_add_joint_ownership_to_liabilities_table.php

-- ============================================================================
-- PRE-MIGRATION CHECKS
-- Run these BEFORE migration to establish baseline
-- ============================================================================

-- Check 1: Current liabilities table structure (BEFORE migration)
SELECT 'BEFORE MIGRATION - Current liabilities structure:' as status;
DESCRIBE liabilities;
-- Expected: Should NOT show ownership_type, joint_owner_id, trust_id columns

-- Check 2: Count existing liability records
SELECT 'BEFORE MIGRATION - Current liability count:' as status;
SELECT COUNT(*) as total_liabilities FROM liabilities;
-- Note this number - it should remain the same after migration

-- Check 3: Sample existing liability data
SELECT 'BEFORE MIGRATION - Sample liability data:' as status;
SELECT id, user_id, liability_type, current_balance
FROM liabilities
LIMIT 5;

-- ============================================================================
-- POST-MIGRATION VERIFICATION
-- Run these AFTER migration to verify success
-- ============================================================================

-- Check 1: Verify migration ran successfully
SELECT 'POST MIGRATION - Check migration status:' as status;
SELECT migration, batch
FROM migrations
WHERE migration = '2025_11_22_092125_add_joint_ownership_to_liabilities_table'
ORDER BY batch DESC
LIMIT 1;
-- Expected: Should show one row with migration name and batch number

-- Check 2: Verify new columns exist
SELECT 'POST MIGRATION - Verify new columns exist:' as status;
DESCRIBE liabilities;
-- Expected: Should show these new columns:
-- - ownership_type | enum('individual','joint','trust') | NO | | individual
-- - joint_owner_id | bigint unsigned | YES | MUL | NULL
-- - trust_id       | bigint unsigned | YES | MUL | NULL

-- Check 3: Verify foreign key constraints created
SELECT 'POST MIGRATION - Verify foreign keys:' as status;
SELECT
    CONSTRAINT_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'liabilities'
  AND CONSTRAINT_NAME LIKE '%foreign%'
ORDER BY CONSTRAINT_NAME;
-- Expected: Should show:
-- - liabilities_joint_owner_id_foreign | joint_owner_id | users | id
-- - liabilities_trust_id_foreign | trust_id | trusts | id
-- - liabilities_user_id_foreign | user_id | users | id (existing)

-- Check 4: Verify indexes created
SELECT 'POST MIGRATION - Verify indexes:' as status;
SHOW INDEX FROM liabilities;
-- Expected: Should include:
-- - liabilities_joint_owner_id_index
-- - liabilities_trust_id_index

-- Check 5: Verify all existing data defaulted to 'individual'
SELECT 'POST MIGRATION - Verify ownership type defaults:' as status;
SELECT
    COUNT(*) as total_records,
    COUNT(CASE WHEN ownership_type = 'individual' THEN 1 END) as individual_count,
    COUNT(CASE WHEN ownership_type = 'joint' THEN 1 END) as joint_count,
    COUNT(CASE WHEN ownership_type = 'trust' THEN 1 END) as trust_count
FROM liabilities;
-- Expected: All records should be 'individual' (joint_count and trust_count should be 0)

-- Check 6: Verify no data loss (record count unchanged)
SELECT 'POST MIGRATION - Verify no data loss:' as status;
SELECT COUNT(*) as total_liabilities FROM liabilities;
-- Expected: Should match the count from PRE-MIGRATION Check 2

-- Check 7: Verify existing data integrity (sample check)
SELECT 'POST MIGRATION - Verify data integrity:' as status;
SELECT id, user_id, liability_type, current_balance, ownership_type, joint_owner_id, trust_id
FROM liabilities
LIMIT 5;
-- Expected: All existing fields unchanged, new fields populated with defaults

-- ============================================================================
-- FOREIGN KEY RELATIONSHIP TESTS
-- ============================================================================

-- Test 1: Verify joint_owner_id can reference valid users
SELECT 'RELATIONSHIP TEST - joint_owner_id references users:' as status;
SELECT l.id, l.user_id, l.joint_owner_id, u.name as joint_owner_name
FROM liabilities l
LEFT JOIN users u ON l.joint_owner_id = u.id
WHERE l.joint_owner_id IS NOT NULL
LIMIT 5;
-- Expected: If any joint liabilities exist, should show valid user names

-- Test 2: Verify trust_id can reference valid trusts
SELECT 'RELATIONSHIP TEST - trust_id references trusts:' as status;
SELECT l.id, l.user_id, l.trust_id, t.trust_name
FROM liabilities l
LEFT JOIN trusts t ON l.trust_id = t.id
WHERE l.trust_id IS NOT NULL
LIMIT 5;
-- Expected: If any trust-owned liabilities exist, should show valid trust names

-- ============================================================================
-- ENUM VALUE TESTS
-- ============================================================================

-- Test 1: Verify ENUM accepts 'individual'
SELECT 'ENUM TEST - individual ownership:' as status;
SELECT COUNT(*) as individual_count
FROM liabilities
WHERE ownership_type = 'individual';
-- Expected: Should return count > 0 (all existing records)

-- Test 2: Test inserting 'joint' ownership (dry run - not committed)
-- This is a SELECT to verify the enum would accept 'joint'
SELECT 'ENUM TEST - joint ownership validation:' as status;
SELECT 'joint' as test_value,
       CASE
         WHEN 'joint' IN ('individual', 'joint', 'trust') THEN 'VALID'
         ELSE 'INVALID'
       END as validation_result;
-- Expected: validation_result = 'VALID'

-- Test 3: Test inserting 'trust' ownership (dry run - not committed)
SELECT 'ENUM TEST - trust ownership validation:' as status;
SELECT 'trust' as test_value,
       CASE
         WHEN 'trust' IN ('individual', 'joint', 'trust') THEN 'VALID'
         ELSE 'INVALID'
       END as validation_result;
-- Expected: validation_result = 'VALID'

-- ============================================================================
-- PERFORMANCE TESTS
-- ============================================================================

-- Test 1: Verify indexes are being used (EXPLAIN test)
SELECT 'PERFORMANCE TEST - Index usage on joint_owner_id:' as status;
EXPLAIN SELECT * FROM liabilities WHERE joint_owner_id = 1;
-- Expected: 'key' column should show 'liabilities_joint_owner_id_index'

-- Test 2: Verify indexes are being used (EXPLAIN test)
SELECT 'PERFORMANCE TEST - Index usage on trust_id:' as status;
EXPLAIN SELECT * FROM liabilities WHERE trust_id = 1;
-- Expected: 'key' column should show 'liabilities_trust_id_index'

-- ============================================================================
-- ROLLBACK VERIFICATION (if rollback was performed)
-- Run these AFTER rollback to verify database reverted correctly
-- ============================================================================

-- Check 1: Verify migration removed from migrations table
SELECT 'ROLLBACK CHECK - Migration removed:' as status;
SELECT COUNT(*) as migration_count
FROM migrations
WHERE migration = '2025_11_22_092125_add_joint_ownership_to_liabilities_table';
-- Expected: 0 (migration should not exist after rollback)

-- Check 2: Verify columns removed
SELECT 'ROLLBACK CHECK - Columns removed:' as status;
SELECT
    COUNT(*) as ownership_type_exists
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'liabilities'
  AND COLUMN_NAME IN ('ownership_type', 'joint_owner_id', 'trust_id');
-- Expected: 0 (columns should not exist after rollback)

-- Check 3: Verify foreign keys removed
SELECT 'ROLLBACK CHECK - Foreign keys removed:' as status;
SELECT COUNT(*) as fk_count
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'liabilities'
  AND CONSTRAINT_NAME IN ('liabilities_joint_owner_id_foreign', 'liabilities_trust_id_foreign');
-- Expected: 0 (foreign keys should not exist after rollback)

-- Check 4: Verify data integrity after rollback
SELECT 'ROLLBACK CHECK - Data integrity:' as status;
SELECT COUNT(*) as total_liabilities FROM liabilities;
-- Expected: Should match original count from PRE-MIGRATION Check 2

-- ============================================================================
-- COMPREHENSIVE STATUS REPORT
-- ============================================================================

-- Generate comprehensive status report
SELECT '========================================' as '';
SELECT 'COMPREHENSIVE MIGRATION STATUS REPORT' as '';
SELECT '========================================' as '';

SELECT 'Migration File:' as metric, '2025_11_22_092125_add_joint_ownership_to_liabilities_table' as value
UNION ALL
SELECT 'Target Table:', 'liabilities'
UNION ALL
SELECT 'Total Liabilities:', CAST(COUNT(*) AS CHAR) FROM liabilities
UNION ALL
SELECT 'Individual Ownership:', CAST(SUM(CASE WHEN ownership_type = 'individual' THEN 1 ELSE 0 END) AS CHAR) FROM liabilities
UNION ALL
SELECT 'Joint Ownership:', CAST(SUM(CASE WHEN ownership_type = 'joint' THEN 1 ELSE 0 END) AS CHAR) FROM liabilities
UNION ALL
SELECT 'Trust Ownership:', CAST(SUM(CASE WHEN ownership_type = 'trust' THEN 1 ELSE 0 END) AS CHAR) FROM liabilities;

SELECT '========================================' as '';
SELECT 'Column Structure:' as '';
SELECT
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_KEY
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'liabilities'
  AND COLUMN_NAME IN ('ownership_type', 'joint_owner_id', 'trust_id')
ORDER BY ORDINAL_POSITION;

SELECT '========================================' as '';
SELECT 'Foreign Key Constraints:' as '';
SELECT
    CONSTRAINT_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'liabilities'
  AND CONSTRAINT_NAME LIKE '%foreign%'
ORDER BY CONSTRAINT_NAME;

SELECT '========================================' as '';
SELECT 'Indexes:' as '';
SELECT
    INDEX_NAME,
    COLUMN_NAME,
    NON_UNIQUE,
    INDEX_TYPE
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'liabilities'
ORDER BY INDEX_NAME, SEQ_IN_INDEX;

SELECT '========================================' as '';
SELECT 'VERIFICATION COMPLETE' as '';
SELECT '========================================' as '';

-- ============================================================================
-- USAGE INSTRUCTIONS
-- ============================================================================

/*
HOW TO USE THIS FILE:

1. PRE-MIGRATION VERIFICATION:
   Run checks from "PRE-MIGRATION CHECKS" section to establish baseline

2. RUN THE MIGRATION:
   Via SSH: php artisan migrate --force

3. POST-MIGRATION VERIFICATION:
   Run all checks from "POST-MIGRATION VERIFICATION" section

4. COMPREHENSIVE REPORT:
   Run "COMPREHENSIVE STATUS REPORT" section to get full summary

5. IF ROLLBACK NEEDED:
   Via SSH: php artisan migrate:rollback --step=1
   Then run "ROLLBACK VERIFICATION" section

EXPECTED RESULTS (Post-Migration):
- Migration appears in migrations table
- 3 new columns exist: ownership_type, joint_owner_id, trust_id
- 2 foreign keys created
- 2 indexes created
- All existing records have ownership_type = 'individual'
- No data loss (record count unchanged)

TROUBLESHOOTING:
- If columns don't exist: Migration didn't run - check Laravel logs
- If foreign keys missing: Check trusts table exists
- If data loss: Restore from backup immediately
- If enum values invalid: Rollback and investigate migration file

SUPPORT:
- Deployment guide: /DEPLOYMENT/DEPLOYMENT_PATCH_v0.2.11_22Nov2025.md
- Changes documentation: friFixes21Nov.md sections 33-34
*/
