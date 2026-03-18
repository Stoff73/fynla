/**
 * Module-specific configuration for the ActionDefinitionDrawer component.
 *
 * Each module config includes: label, sourceOptions, whatIfImpactOptions,
 * conditionOptions, and triggerFields.
 */

export const MODULE_CONFIGS = {
  protection: {
    label: 'Protection',
    sourceOptions: [
      { value: 'agent', label: 'Agent' },
      { value: 'gap', label: 'Gap Analysis' },
    ],
    whatIfImpactOptions: [
      { value: 'coverage_increase', label: 'Coverage Increase' },
      { value: 'gap_reduction', label: 'Gap Reduction' },
      { value: 'default', label: 'Default' },
    ],
    conditionOptions: [
      { value: 'gap_exists', label: 'Coverage gap exists' },
      { value: 'strategy_recommendation', label: 'Strategy recommendation matches' },
      { value: 'policies_exist_with_gaps', label: 'Policies exist but gaps remain' },
      { value: 'multiple_policies', label: 'Multiple policies (consolidation)' },
      { value: 'profile_missing', label: 'Protection profile missing' },
      { value: 'no_policies_with_gaps', label: 'No policies with coverage gaps' },
    ],
    triggerFields: {
      gap_exists: ['coverage_type', 'threshold'],
      strategy_recommendation: ['recommendation_key'],
      multiple_policies: ['threshold'],
    },
  },

  savings: {
    label: 'Cash & Savings',
    sourceOptions: [
      { value: 'agent', label: 'Agent' },
      { value: 'goal', label: 'Goal' },
    ],
    whatIfImpactOptions: [
      { value: 'savings_increase', label: 'Savings Increase' },
      { value: 'rate_improvement', label: 'Rate Improvement' },
      { value: 'default', label: 'Default' },
    ],
    conditionOptions: [
      { value: 'emergency_fund_critical', label: 'Emergency fund critical' },
      { value: 'emergency_fund_low', label: 'Emergency fund low' },
      { value: 'emergency_fund_building', label: 'Emergency fund building' },
      { value: 'emergency_fund_excess', label: 'Emergency fund excess' },
      { value: 'emergency_fund_no_data', label: 'Emergency fund no data' },
      { value: 'rate_below_market', label: 'Rate below market' },
      { value: 'rate_poor', label: 'Poor rate detected' },
      { value: 'fscs_approaching', label: 'Financial Services Compensation Scheme limit approaching' },
      { value: 'fscs_breach', label: 'Financial Services Compensation Scheme limit breached' },
      { value: 'fixed_maturity_warning', label: 'Fixed maturity warning' },
      { value: 'fixed_maturity_urgent', label: 'Fixed maturity urgent' },
      { value: 'promo_rate_expiring', label: 'Promotional rate expiring' },
      { value: 'cash_isa_recommended', label: 'Cash ISA recommended' },
      { value: 'regular_saver_opportunity', label: 'Regular saver opportunity' },
      { value: 'debt_rate_exceeds_savings', label: 'Debt rate exceeds savings' },
      { value: 'excess_cash_isa_available', label: 'Excess cash ISA available' },
      { value: 'excess_cash_pension', label: 'Excess cash pension' },
      { value: 'goal_off_track', label: 'Goal off track' },
      { value: 'goal_nearly_achieved', label: 'Goal nearly achieved' },
      { value: 'goal_no_linked_account', label: 'Goal no linked account' },
    ],
    triggerFields: {
      emergency_fund_critical: ['months_threshold'],
      emergency_fund_low: ['months_threshold'],
      rate_below_market: ['threshold'],
      fscs_approaching: ['threshold'],
    },
  },

  investment: {
    label: 'Investments',
    sourceOptions: [
      { value: 'agent', label: 'Agent' },
      { value: 'goal', label: 'Goal' },
    ],
    whatIfImpactOptions: [
      { value: 'fee_reduction', label: 'Fee Reduction' },
      { value: 'savings_increase', label: 'Savings Increase' },
      { value: 'contribution', label: 'Contribution' },
      { value: 'tax_optimisation', label: 'Tax Optimisation' },
      { value: 'default', label: 'Default' },
    ],
    conditionOptions: [
      { value: 'risk_profile_missing', label: 'Risk profile missing' },
      { value: 'no_holdings_in_accounts', label: 'No holdings in accounts' },
      { value: 'diversification_score_below_threshold', label: 'Diversification below threshold' },
      { value: 'total_fee_above_threshold', label: 'Total fee above threshold' },
      { value: 'fund_ocf_above_threshold', label: 'Fund charges above threshold' },
      { value: 'platform_fee_above_threshold', label: 'Platform fee above threshold' },
      { value: 'allocation_needs_rebalancing', label: 'Asset allocation needs rebalancing' },
      { value: 'tax_loss_harvesting_opportunities', label: 'Tax loss harvesting opportunities' },
      { value: 'has_gia_no_isa', label: 'Has General Investment Account but no ISA' },
      { value: 'isa_remaining_with_gia', label: 'ISA allowance remaining with General Investment Account' },
      { value: 'gia_value_above_threshold_no_bonds', label: 'General Investment Account value above threshold' },
      { value: 'emergency_fund_below_threshold', label: 'Emergency fund below critical threshold' },
      { value: 'emergency_fund_below_target', label: 'Emergency fund below target' },
      { value: 'poor_savings_rate', label: 'Poor savings rate detected' },
      { value: 'savings_isa_allowance_remaining', label: 'Savings ISA allowance remaining' },
      { value: 'surplus_available_isa', label: 'Surplus available for ISA' },
      { value: 'surplus_available_pension', label: 'Surplus available for pension' },
      { value: 'surplus_available_bond', label: 'Surplus available for bond' },
    ],
    triggerFields: {
      diversification_score_below_threshold: ['threshold'],
      total_fee_above_threshold: ['threshold'],
      fund_ocf_above_threshold: ['threshold'],
      platform_fee_above_threshold: ['threshold'],
      gia_value_above_threshold_no_bonds: ['threshold', 'min_gia_value'],
    },
  },

  retirement: {
    label: 'Retirement',
    sourceOptions: [
      { value: 'agent', label: 'Agent' },
      { value: 'goal', label: 'Goal' },
    ],
    whatIfImpactOptions: [
      { value: 'contribution', label: 'Contribution' },
      { value: 'consolidation', label: 'Consolidation' },
      { value: 'tax_optimisation', label: 'Tax Optimisation' },
      { value: 'default', label: 'Default' },
    ],
    conditionOptions: [
      { value: 'employee_contribution_percent_below', label: 'Employee contribution percent below threshold' },
      { value: 'zero_contribution_with_fund_value', label: 'Zero contribution with fund value' },
      { value: 'income_gap_positive_and_additional_contribution_required', label: 'Income gap with additional contribution required' },
      { value: 'higher_rate_taxpayer_below_allowance', label: 'Higher rate taxpayer below allowance' },
      { value: 'annual_allowance_has_excess', label: 'Annual Allowance has excess' },
      { value: 'ni_years_wont_reach_required_by_spa', label: 'National Insurance years won\'t reach required by State Pension age' },
      { value: 'income_gap_exceeds_percentage_of_target', label: 'Income gap exceeds percentage of target' },
      { value: 'linked_goal_no_monthly_contribution', label: 'Linked goal has no monthly contribution' },
      { value: 'linked_goal_off_track', label: 'Linked goal is off track' },
      { value: 'goal_months_remaining_below_and_progress_below', label: 'Goal deadline approaching with low progress' },
    ],
    triggerFields: {
      employee_contribution_percent_below: ['threshold'],
      income_gap_exceeds_percentage_of_target: ['threshold'],
      goal_months_remaining_below_and_progress_below: ['months_threshold', 'progress_threshold'],
    },
  },

  estate: {
    label: 'Estate Planning',
    sourceOptions: [
      { value: 'agent', label: 'Agent' },
      { value: 'goal', label: 'Goal' },
    ],
    whatIfImpactOptions: [
      { value: 'iht_reduction', label: 'Inheritance Tax Reduction' },
      { value: 'estate_protection', label: 'Estate Protection' },
      { value: 'default', label: 'Default' },
    ],
    conditionOptions: [
      { value: 'no_will', label: 'No will in place' },
      { value: 'policy_not_in_trust', label: 'Life policy not held in trust' },
      { value: 'iht_exceeds_nrb', label: 'Estate value exceeds nil-rate band' },
      { value: 'no_lpa', label: 'No Lasting Power of Attorney (Financial)' },
      { value: 'no_lpa_health', label: 'No Lasting Power of Attorney (Health)' },
      { value: 'gifts_pet_window', label: 'Gifts within seven-year window' },
      { value: 'trust_review_due', label: 'Trust arrangement review due' },
      { value: 'beneficiary_review', label: 'Beneficiary designations review' },
    ],
    triggerFields: {
      trust_review_due: ['months_threshold'],
    },
  },

  tax: {
    label: 'Tax',
    sourceOptions: [
      { value: 'agent', label: 'Agent' },
      { value: 'goal', label: 'Goal' },
    ],
    whatIfImpactOptions: [
      { value: 'tax_optimisation', label: 'Tax Optimisation' },
      { value: 'allowance_utilisation', label: 'Allowance Utilisation' },
      { value: 'default', label: 'Default' },
    ],
    conditionOptions: [
      { value: 'isa_not_maxed', label: 'ISA allowance not maximised' },
      { value: 'pension_carry_forward_available', label: 'Pension carry forward available' },
      { value: 'spousal_transfer_beneficial', label: 'Spousal transfer beneficial' },
      { value: 'cgt_allowance_unused', label: 'Capital Gains Tax allowance unused' },
      { value: 'high_dividend_in_gia', label: 'High dividend in General Investment Account' },
    ],
    triggerFields: {
      high_dividend_in_gia: ['min_gia_value'],
    },
  },
};

export const MODULE_KEYS = Object.keys(MODULE_CONFIGS);

export default MODULE_CONFIGS;
