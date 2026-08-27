-- W-0043 — orphaned shared rows. READ ONLY. Safe on production.
--
-- Orphan: a shared ownership_type naming neither a linked account nor, where the
-- table has the column, an off-platform co-owner. Half of each row below is
-- attributed to nobody, so the owner's net worth is wrong by half the record.
--
-- savings_accounts and investment_accounts have NO joint_owner_name column
-- (W-0042), so there a shared row can only name a linked account. That is
-- deliberate and first-class, so those two are tested on joint_owner_id alone.
--
-- Column existence verified against the live schema 2026-08-26.
-- Expect ZERO rows. Any row is a real user whose net worth is understated.

SELECT 'mortgages' AS tbl, id, user_id, ownership_type, ownership_percentage
FROM mortgages
WHERE ownership_type IN ('joint','tenants_in_common') AND deleted_at IS NULL
  AND joint_owner_id IS NULL AND (joint_owner_name IS NULL OR joint_owner_name = '')

UNION ALL SELECT 'properties', id, user_id, ownership_type, ownership_percentage
FROM properties
WHERE ownership_type IN ('joint','tenants_in_common') AND deleted_at IS NULL
  AND joint_owner_id IS NULL AND (joint_owner_name IS NULL OR joint_owner_name = '')

UNION ALL SELECT 'chattels', id, user_id, ownership_type, ownership_percentage
FROM chattels
WHERE ownership_type IN ('joint','tenants_in_common') AND deleted_at IS NULL
  AND joint_owner_id IS NULL AND (joint_owner_name IS NULL OR joint_owner_name = '')

UNION ALL SELECT 'savings_accounts', id, user_id, ownership_type, ownership_percentage
FROM savings_accounts
WHERE ownership_type IN ('joint','tenants_in_common') AND deleted_at IS NULL
  AND joint_owner_id IS NULL

UNION ALL SELECT 'investment_accounts', id, user_id, ownership_type, ownership_percentage
FROM investment_accounts
WHERE ownership_type IN ('joint','tenants_in_common') AND deleted_at IS NULL
  AND joint_owner_id IS NULL

ORDER BY tbl, id;
