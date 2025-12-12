<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Database Performance Optimization Migration
 *
 * This migration adds missing indexes identified during the database audit.
 * It addresses the following issues:
 *
 * 1. Missing indexes on foreign key columns not using foreignId() helper
 * 2. Missing composite indexes for common query patterns
 * 3. Missing indexes on frequently queried columns (WHERE, ORDER BY, JOIN)
 * 4. Missing indexes on polymorphic relationships
 *
 * IMPORTANT: This migration uses IF NOT EXISTS checks via Schema::hasIndex()
 * to ensure idempotency and safe re-runs.
 *
 * Production Safety Notes:
 * - All indexes are added using standard ALTER TABLE (not INPLACE for MySQL < 8.0)
 * - For tables with millions of rows, consider running during maintenance window
 * - Use pt-online-schema-change for very large tables if needed
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ============================================================
        // SECTION 1: Missing indexes on joint_owner_id columns
        // These FK columns were added via ALTER TABLE but some lack indexes
        // ============================================================

        // Properties table - joint_owner_id needs index
        if (Schema::hasColumn('properties', 'joint_owner_id') && ! $this->indexExists('properties', 'properties_joint_owner_id_index')) {
            Schema::table('properties', function (Blueprint $table) {
                $table->index('joint_owner_id', 'properties_joint_owner_id_index');
            });
        }

        // Mortgages table - joint_owner_id needs index (if not already added)
        if (Schema::hasColumn('mortgages', 'joint_owner_id') && ! $this->indexExists('mortgages', 'mortgages_joint_owner_id_index')) {
            Schema::table('mortgages', function (Blueprint $table) {
                $table->index('joint_owner_id', 'mortgages_joint_owner_id_index');
            });
        }

        // ============================================================
        // SECTION 2: Composite indexes for common query patterns
        // ============================================================

        // Family members - commonly queried by user_id + relationship
        if (! $this->indexExists('family_members', 'family_members_user_relationship_idx')) {
            Schema::table('family_members', function (Blueprint $table) {
                $table->index(['user_id', 'relationship'], 'family_members_user_relationship_idx');
            });
        }

        // Documents - status query pattern
        if (Schema::hasTable('documents') && ! $this->indexExists('documents', 'documents_user_created_idx')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->index(['user_id', 'created_at'], 'documents_user_created_idx');
            });
        }

        // Gift date queries for IHT 7-year rule
        if (! $this->indexExists('gifts', 'gifts_user_gift_date_idx')) {
            Schema::table('gifts', function (Blueprint $table) {
                $table->index(['user_id', 'gift_date'], 'gifts_user_gift_date_idx');
            });
        }

        // Will queries - user lookups
        if (Schema::hasTable('wills') && ! $this->indexExists('wills', 'wills_user_id_idx')) {
            Schema::table('wills', function (Blueprint $table) {
                $table->index('user_id', 'wills_user_id_idx');
            });
        }

        // Bequests - will_id lookups with priority ordering
        if (Schema::hasTable('bequests') && ! $this->indexExists('bequests', 'bequests_will_priority_idx')) {
            Schema::table('bequests', function (Blueprint $table) {
                $table->index(['will_id', 'priority_order'], 'bequests_will_priority_idx');
            });
        }

        // ============================================================
        // SECTION 3: Missing ORDER BY column indexes
        // ============================================================

        // Mortgages - start_date for ordering
        if (! $this->indexExists('mortgages', 'mortgages_start_date_idx')) {
            Schema::table('mortgages', function (Blueprint $table) {
                $table->index('start_date', 'mortgages_start_date_idx');
            });
        }

        // Net worth statements - statement_date for trend queries
        if (! $this->indexExists('net_worth_statements', 'net_worth_statements_user_date_idx')) {
            Schema::table('net_worth_statements', function (Blueprint $table) {
                $table->index(['user_id', 'statement_date'], 'net_worth_statements_user_date_idx');
            });
        }

        // Recommendation tracking - completed_at for history queries
        if (! $this->indexExists('recommendation_tracking', 'rec_tracking_user_completed_idx')) {
            Schema::table('recommendation_tracking', function (Blueprint $table) {
                $table->index(['user_id', 'completed_at'], 'rec_tracking_user_completed_idx');
            });
        }

        // Investment accounts - ownership_type for joint queries
        if (! $this->indexExists('investment_accounts', 'investment_accounts_ownership_type_idx')) {
            Schema::table('investment_accounts', function (Blueprint $table) {
                $table->index('ownership_type', 'investment_accounts_ownership_type_idx');
            });
        }

        // ============================================================
        // SECTION 4: Protection policy indexes
        // Common pattern: WHERE user_id = ? for all policy types
        // Already have user_id index, but adding is_active for filtered queries
        // ============================================================

        // Life insurance policies - policy_type for filtering
        if (Schema::hasColumn('life_insurance_policies', 'policy_type') && ! $this->indexExists('life_insurance_policies', 'life_policies_user_type_idx')) {
            Schema::table('life_insurance_policies', function (Blueprint $table) {
                $table->index(['user_id', 'policy_type'], 'life_policies_user_type_idx');
            });
        }

        // Critical illness policies - policy_type for filtering
        if (Schema::hasColumn('critical_illness_policies', 'policy_type') && ! $this->indexExists('critical_illness_policies', 'ci_policies_user_type_idx')) {
            Schema::table('critical_illness_policies', function (Blueprint $table) {
                $table->index(['user_id', 'policy_type'], 'ci_policies_user_type_idx');
            });
        }

        // ============================================================
        // SECTION 5: IHT Calculation optimization
        // These tables are queried frequently for estate planning
        // ============================================================

        // IHT Profiles - already has user_id index, add for spouse lookups
        // (spouse data is fetched via user_id of spouse)

        // ISA allowance tracking - tax_year queries
        if (! $this->indexExists('isa_allowance_tracking', 'isa_tracking_tax_year_idx')) {
            Schema::table('isa_allowance_tracking', function (Blueprint $table) {
                $table->index('tax_year', 'isa_tracking_tax_year_idx');
            });
        }

        // ============================================================
        // SECTION 6: Timeline indexes for recommendation filtering
        // ============================================================

        if (! $this->indexExists('recommendation_tracking', 'rec_tracking_timeline_idx')) {
            Schema::table('recommendation_tracking', function (Blueprint $table) {
                $table->index(['user_id', 'timeline'], 'rec_tracking_timeline_idx');
            });
        }

        // ============================================================
        // SECTION 7: Trust-related query optimization
        // ============================================================

        // Trust assets lookup (if table exists)
        if (Schema::hasTable('trust_assets') && ! $this->indexExists('trust_assets', 'trust_assets_trust_id_idx')) {
            Schema::table('trust_assets', function (Blueprint $table) {
                $table->index('trust_id', 'trust_assets_trust_id_idx');
            });
        }

        // Trust beneficiaries lookup (if table exists)
        if (Schema::hasTable('trust_beneficiaries') && ! $this->indexExists('trust_beneficiaries', 'trust_beneficiaries_trust_id_idx')) {
            Schema::table('trust_beneficiaries', function (Blueprint $table) {
                $table->index('trust_id', 'trust_beneficiaries_trust_id_idx');
            });
        }

        // ============================================================
        // SECTION 8: Savings accounts optimization
        // ============================================================

        // Savings accounts - institution for grouping
        if (Schema::hasColumn('savings_accounts', 'institution') && ! $this->indexExists('savings_accounts', 'savings_accounts_institution_idx')) {
            Schema::table('savings_accounts', function (Blueprint $table) {
                $table->index('institution', 'savings_accounts_institution_idx');
            });
        }

        // ============================================================
        // SECTION 9: Holdings polymorphic relationship optimization
        // The composite index on (holdable_type, holdable_id) already exists
        // Adding reverse index for count queries
        // ============================================================

        if (! $this->indexExists('holdings', 'holdings_holdable_id_type_idx')) {
            Schema::table('holdings', function (Blueprint $table) {
                $table->index(['holdable_id', 'holdable_type'], 'holdings_holdable_id_type_idx');
            });
        }

        // ============================================================
        // SECTION 10: Spouse permission query optimization
        // ============================================================

        // Unique constraint on user_id + spouse_id to prevent duplicates
        if (! $this->indexExists('spouse_permissions', 'spouse_permissions_user_spouse_unique')) {
            Schema::table('spouse_permissions', function (Blueprint $table) {
                $table->unique(['user_id', 'spouse_id'], 'spouse_permissions_user_spouse_unique');
            });
        }

        // ============================================================
        // SECTION 11: Joint account logs optimization
        // ============================================================

        if (Schema::hasTable('joint_account_logs') && ! $this->indexExists('joint_account_logs', 'jal_created_at_idx')) {
            Schema::table('joint_account_logs', function (Blueprint $table) {
                $table->index('created_at', 'jal_created_at_idx');
            });
        }

        // ============================================================
        // SECTION 12: Document extractions optimization
        // ============================================================

        if (Schema::hasTable('document_extractions') && Schema::hasColumn('document_extractions', 'status') && ! $this->indexExists('document_extractions', 'doc_extractions_status_idx')) {
            Schema::table('document_extractions', function (Blueprint $table) {
                $table->index('status', 'doc_extractions_status_idx');
            });
        }

        // ============================================================
        // SECTION 13: Business interests optimization
        // ============================================================

        if (! $this->indexExists('business_interests', 'business_interests_ownership_type_idx')) {
            Schema::table('business_interests', function (Blueprint $table) {
                $table->index('ownership_type', 'business_interests_ownership_type_idx');
            });
        }

        // ============================================================
        // SECTION 14: Chattels optimization
        // ============================================================

        if (! $this->indexExists('chattels', 'chattels_ownership_type_idx')) {
            Schema::table('chattels', function (Blueprint $table) {
                $table->index('ownership_type', 'chattels_ownership_type_idx');
            });
        }

        // ============================================================
        // SECTION 15: Cash accounts optimization
        // ============================================================

        if (! $this->indexExists('cash_accounts', 'cash_accounts_ownership_type_idx')) {
            Schema::table('cash_accounts', function (Blueprint $table) {
                $table->index('ownership_type', 'cash_accounts_ownership_type_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // SECTION 1: Remove joint_owner_id indexes
        if ($this->indexExists('properties', 'properties_joint_owner_id_index')) {
            Schema::table('properties', function (Blueprint $table) {
                $table->dropIndex('properties_joint_owner_id_index');
            });
        }

        if ($this->indexExists('mortgages', 'mortgages_joint_owner_id_index')) {
            Schema::table('mortgages', function (Blueprint $table) {
                $table->dropIndex('mortgages_joint_owner_id_index');
            });
        }

        // SECTION 2: Remove composite indexes
        if ($this->indexExists('family_members', 'family_members_user_relationship_idx')) {
            Schema::table('family_members', function (Blueprint $table) {
                $table->dropIndex('family_members_user_relationship_idx');
            });
        }

        if (Schema::hasTable('documents') && $this->indexExists('documents', 'documents_user_created_idx')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->dropIndex('documents_user_created_idx');
            });
        }

        if ($this->indexExists('gifts', 'gifts_user_gift_date_idx')) {
            Schema::table('gifts', function (Blueprint $table) {
                $table->dropIndex('gifts_user_gift_date_idx');
            });
        }

        if (Schema::hasTable('wills') && $this->indexExists('wills', 'wills_user_id_idx')) {
            Schema::table('wills', function (Blueprint $table) {
                $table->dropIndex('wills_user_id_idx');
            });
        }

        if (Schema::hasTable('bequests') && $this->indexExists('bequests', 'bequests_will_priority_idx')) {
            Schema::table('bequests', function (Blueprint $table) {
                $table->dropIndex('bequests_will_priority_idx');
            });
        }

        // SECTION 3: Remove ORDER BY indexes
        if ($this->indexExists('mortgages', 'mortgages_start_date_idx')) {
            Schema::table('mortgages', function (Blueprint $table) {
                $table->dropIndex('mortgages_start_date_idx');
            });
        }

        if ($this->indexExists('net_worth_statements', 'net_worth_statements_user_date_idx')) {
            Schema::table('net_worth_statements', function (Blueprint $table) {
                $table->dropIndex('net_worth_statements_user_date_idx');
            });
        }

        if ($this->indexExists('recommendation_tracking', 'rec_tracking_user_completed_idx')) {
            Schema::table('recommendation_tracking', function (Blueprint $table) {
                $table->dropIndex('rec_tracking_user_completed_idx');
            });
        }

        if ($this->indexExists('investment_accounts', 'investment_accounts_ownership_type_idx')) {
            Schema::table('investment_accounts', function (Blueprint $table) {
                $table->dropIndex('investment_accounts_ownership_type_idx');
            });
        }

        // SECTION 4: Remove protection policy indexes
        if ($this->indexExists('life_insurance_policies', 'life_policies_user_type_idx')) {
            Schema::table('life_insurance_policies', function (Blueprint $table) {
                $table->dropIndex('life_policies_user_type_idx');
            });
        }

        if ($this->indexExists('critical_illness_policies', 'ci_policies_user_type_idx')) {
            Schema::table('critical_illness_policies', function (Blueprint $table) {
                $table->dropIndex('ci_policies_user_type_idx');
            });
        }

        // SECTION 5: Remove ISA tracking index
        if ($this->indexExists('isa_allowance_tracking', 'isa_tracking_tax_year_idx')) {
            Schema::table('isa_allowance_tracking', function (Blueprint $table) {
                $table->dropIndex('isa_tracking_tax_year_idx');
            });
        }

        // SECTION 6: Remove timeline index
        if ($this->indexExists('recommendation_tracking', 'rec_tracking_timeline_idx')) {
            Schema::table('recommendation_tracking', function (Blueprint $table) {
                $table->dropIndex('rec_tracking_timeline_idx');
            });
        }

        // SECTION 7: Remove trust indexes
        if (Schema::hasTable('trust_assets') && $this->indexExists('trust_assets', 'trust_assets_trust_id_idx')) {
            Schema::table('trust_assets', function (Blueprint $table) {
                $table->dropIndex('trust_assets_trust_id_idx');
            });
        }

        if (Schema::hasTable('trust_beneficiaries') && $this->indexExists('trust_beneficiaries', 'trust_beneficiaries_trust_id_idx')) {
            Schema::table('trust_beneficiaries', function (Blueprint $table) {
                $table->dropIndex('trust_beneficiaries_trust_id_idx');
            });
        }

        // SECTION 8: Remove savings accounts index
        if ($this->indexExists('savings_accounts', 'savings_accounts_institution_idx')) {
            Schema::table('savings_accounts', function (Blueprint $table) {
                $table->dropIndex('savings_accounts_institution_idx');
            });
        }

        // SECTION 9: Remove holdings reverse index
        if ($this->indexExists('holdings', 'holdings_holdable_id_type_idx')) {
            Schema::table('holdings', function (Blueprint $table) {
                $table->dropIndex('holdings_holdable_id_type_idx');
            });
        }

        // SECTION 10: Remove spouse permission unique constraint
        if ($this->indexExists('spouse_permissions', 'spouse_permissions_user_spouse_unique')) {
            Schema::table('spouse_permissions', function (Blueprint $table) {
                $table->dropUnique('spouse_permissions_user_spouse_unique');
            });
        }

        // SECTION 11: Remove joint account logs index
        if (Schema::hasTable('joint_account_logs') && $this->indexExists('joint_account_logs', 'jal_created_at_idx')) {
            Schema::table('joint_account_logs', function (Blueprint $table) {
                $table->dropIndex('jal_created_at_idx');
            });
        }

        // SECTION 12: Remove document extractions index
        if (Schema::hasTable('document_extractions') && $this->indexExists('document_extractions', 'doc_extractions_status_idx')) {
            Schema::table('document_extractions', function (Blueprint $table) {
                $table->dropIndex('doc_extractions_status_idx');
            });
        }

        // SECTION 13: Remove business interests index
        if ($this->indexExists('business_interests', 'business_interests_ownership_type_idx')) {
            Schema::table('business_interests', function (Blueprint $table) {
                $table->dropIndex('business_interests_ownership_type_idx');
            });
        }

        // SECTION 14: Remove chattels index
        if ($this->indexExists('chattels', 'chattels_ownership_type_idx')) {
            Schema::table('chattels', function (Blueprint $table) {
                $table->dropIndex('chattels_ownership_type_idx');
            });
        }

        // SECTION 15: Remove cash accounts index
        if ($this->indexExists('cash_accounts', 'cash_accounts_ownership_type_idx')) {
            Schema::table('cash_accounts', function (Blueprint $table) {
                $table->dropIndex('cash_accounts_ownership_type_idx');
            });
        }
    }

    /**
     * Check if an index exists on a table.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();

        // Get all indexes for the table
        $indexes = $connection->select(
            'SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [$databaseName, $table]
        );

        $indexNames = array_column($indexes, 'INDEX_NAME');

        return in_array($indexName, $indexNames);
    }
};
