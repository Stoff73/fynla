-- ============================================
-- Fynla v0.2.12 Database Verification Script
-- Date: November 22, 2025
-- ============================================

-- This script verifies both database changes from v0.2.12 deployment:
-- 1. dc_pensions.provider column is nullable (Section 13)
-- 2. liabilities table has joint ownership support (Sections 31, 34.1)

-- ============================================
-- VERIFICATION 1: dc_pensions.provider nullable
-- ============================================

SELECT '=== Verifying dc_pensions.provider column ===' as verification_step;

SELECT
    COLUMN_NAME,
    IS_NULLABLE,
    COLUMN_TYPE,
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'dc_pensions'
  AND COLUMN_NAME = 'provider';

-- Expected Result:
-- COLUMN_NAME: provider
-- IS_NULLABLE: YES  <-- Must be YES
-- COLUMN_TYPE: varchar(255)
-- COLUMN_DEFAULT: NULL

-- ============================================
-- VERIFICATION 2: liabilities ownership columns exist
-- ============================================

SELECT '=== Verifying liabilities ownership columns ===' as verification_step;

SELECT
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    ORDINAL_POSITION
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'liabilities'
  AND COLUMN_NAME IN ('ownership_type', 'joint_owner_id', 'trust_id')
ORDER BY ORDINAL_POSITION;

-- Expected Result: 3 rows
-- ownership_type   | enum('individual','joint','trust') | NO  | individual
-- joint_owner_id   | bigint unsigned                    | YES | NULL
-- trust_id         | bigint unsigned                    | YES | NULL

-- ============================================
-- VERIFICATION 3: ownership_type enum values
-- ============================================

SELECT '=== Verifying liabilities ownership_type enum ===' as verification_step;

SELECT COLUMN_TYPE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'liabilities'
  AND COLUMN_NAME = 'ownership_type';

-- Expected Result:
-- enum('individual','joint','trust')  <-- Must include all three values

-- ============================================
-- VERIFICATION 4: Foreign key constraints
-- ============================================

SELECT '=== Verifying liabilities foreign key constraints ===' as verification_step;

SELECT
    CONSTRAINT_NAME,
    TABLE_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'liabilities'
  AND REFERENCED_TABLE_NAME IS NOT NULL
  AND CONSTRAINT_NAME IN ('liabilities_joint_owner_id_foreign', 'liabilities_trust_id_foreign')
ORDER BY CONSTRAINT_NAME;

-- Expected Result: 2 rows
-- liabilities_joint_owner_id_foreign | liabilities | joint_owner_id | users  | id
-- liabilities_trust_id_foreign       | liabilities | trust_id       | trusts | id

-- ============================================
-- VERIFICATION 5: Data integrity check
-- ============================================

SELECT '=== Checking liabilities data integrity ===' as verification_step;

SELECT
    ownership_type,
    COUNT(*) as record_count,
    COUNT(DISTINCT user_id) as distinct_users,
    COUNT(joint_owner_id) as joint_records,
    COUNT(trust_id) as trust_records
FROM liabilities
GROUP BY ownership_type
ORDER BY ownership_type;

-- Expected Result:
-- All existing records should have ownership_type = 'individual'
-- joint_records should be 0 (no joint liabilities yet)
-- trust_records should be 0 (no trust liabilities yet)

-- ============================================
-- VERIFICATION 6: dc_pensions NULL provider count
-- ============================================

SELECT '=== Checking dc_pensions NULL provider values ===' as verification_step;

SELECT
    COUNT(*) as total_pensions,
    COUNT(provider) as pensions_with_provider,
    COUNT(*) - COUNT(provider) as pensions_without_provider
FROM dc_pensions;

-- Expected Result:
-- Any count is acceptable (NULL values are now allowed)
-- pensions_without_provider may be > 0 (this is now valid)

-- ============================================
-- VERIFICATION 7: Migration table check
-- ============================================

SELECT '=== Verifying migration was recorded ===' as verification_step;

SELECT
    migration,
    batch,
    'SUCCESS - Migration recorded' as status
FROM migrations
WHERE migration = '2025_11_22_092125_add_joint_ownership_to_liabilities_table'
ORDER BY id DESC
LIMIT 1;

-- Expected Result: 1 row
-- 2025_11_22_092125_add_joint_ownership_to_liabilities_table | [batch_number] | SUCCESS

-- ============================================
-- VERIFICATION 8: Table structure summary
-- ============================================

SELECT '=== Complete liabilities table structure ===' as verification_step;

DESCRIBE liabilities;

-- Expected Result: Full table structure including:
-- - id (primary key)
-- - user_id (foreign key)
-- - ownership_type (enum with individual, joint, trust)
-- - joint_owner_id (nullable foreign key to users)
-- - trust_id (nullable foreign key to trusts)
-- - liability_type
-- - liability_name
-- - current_balance
-- - interest_rate
-- - etc.

-- ============================================
-- FINAL SUMMARY
-- ============================================

SELECT '=== Deployment Verification Summary ===' as summary;

SELECT
    'v0.2.12 Database Changes' as deployment_version,
    (SELECT IF(IS_NULLABLE = 'YES', 'PASS', 'FAIL')
     FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'dc_pensions'
       AND COLUMN_NAME = 'provider') as dc_pensions_provider_nullable,
    (SELECT IF(COUNT(*) = 3, 'PASS', 'FAIL')
     FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'liabilities'
       AND COLUMN_NAME IN ('ownership_type', 'joint_owner_id', 'trust_id')) as liabilities_ownership_columns,
    (SELECT IF(COUNT(*) = 2, 'PASS', 'FAIL')
     FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'liabilities'
       AND CONSTRAINT_NAME IN ('liabilities_joint_owner_id_foreign', 'liabilities_trust_id_foreign')) as liabilities_foreign_keys,
    (SELECT IF(COUNT(*) = 1, 'PASS', 'FAIL')
     FROM migrations
     WHERE migration = '2025_11_22_092125_add_joint_ownership_to_liabilities_table') as migration_recorded,
    NOW() as verified_at;

-- Expected Result: All checks should show 'PASS'
-- dc_pensions_provider_nullable:      PASS
-- liabilities_ownership_columns:      PASS
-- liabilities_foreign_keys:           PASS
-- migration_recorded:                 PASS

-- ============================================
-- USAGE INSTRUCTIONS
-- ============================================

-- Run this script on production after deployment:
-- mysql -u [DB_USER] -p[DB_PASSWORD] [DB_NAME] < verify_v0.2.12_database.sql

-- OR run interactively:
-- mysql -u [DB_USER] -p[DB_PASSWORD] [DB_NAME]
-- source /path/to/verify_v0.2.12_database.sql

-- All verifications should return PASS in the final summary.
-- If any check shows FAIL, review the specific verification section above for details.

-- ============================================
-- End of Verification Script
-- ============================================
