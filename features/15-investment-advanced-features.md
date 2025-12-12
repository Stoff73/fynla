# Feature Specification: Investment Module - Advanced Features

## Status: Planned (Coming Soon)

## Executive Summary

The Investment Advanced Features encompass seven planned capabilities for the Investment module: Performance Tracking, Portfolio Optimisation, Rebalancing, Investment Goals, Tax Efficiency, Fee Analysis, and Strategy Recommendations. These features will provide sophisticated portfolio analysis tools commonly found in professional investment management software.

### Elevator Pitch

Transform your investment management with professional-grade tools: track returns against benchmarks, optimise your portfolio, automate rebalancing decisions, and minimise tax drag.

### Problem Statement

While basic portfolio tracking shows what users own, it does not help them answer critical questions: Am I getting good returns? Is my portfolio efficiently constructed? When should I rebalance? How do fees impact my wealth? What actions should I take?

### Target Audience

- Primary: Serious DIY investors wanting professional-level analysis
- Secondary: Users considering whether to manage investments themselves or seek advice
- Tertiary: Financial advisers using the platform with clients

### Unique Selling Proposition

Professional investment analysis tools in a consumer-friendly interface, with UK tax integration and personalised recommendations based on the user's complete financial picture.

### Success Metrics

| Metric | Target | Measurement Method |
|--------|--------|-------------------|
| Feature engagement | 40% of investors use at least one advanced feature | Feature tracking |
| Rebalancing actions | 20% implement rebalancing suggestions | Action tracking |
| Goal completion | 30% of goals show progress | Goal tracking |
| Time savings | Users report 2+ hours/month saved | User surveys |

---

## Planned Features Overview

### 1. Performance Tracking Tab

**Purpose**: Show how investments have performed over time and against benchmarks.

**Planned Functionality**:
- Value changes over different time periods (1M, 3M, 6M, 1Y, 3Y, 5Y, All)
- Time-weighted returns calculation
- Money-weighted returns calculation
- Benchmark comparison (FTSE 100, FTSE All-Share, S&P 500, Global)
- Performance attribution (which holdings drove returns)
- Dividend/income tracking

**Key Displays**:
- Line chart of portfolio value over time
- Returns table by period
- Comparison chart against selected benchmark
- Top/bottom performers list

### 2. Portfolio Optimisation Tab

**Purpose**: Analyse portfolio construction and suggest improvements using modern portfolio theory.

**Planned Functionality**:
- Asset allocation analysis
- Efficient frontier calculation
- Risk-return optimisation
- Recommended portfolio weights
- Volatility estimation
- Sharpe ratio calculation
- Correlation matrix

**Key Displays**:
- Current allocation vs optimal allocation
- Efficient frontier chart
- Risk metrics dashboard
- Diversification score

### 3. Rebalancing Tab

**Purpose**: Identify when portfolio has drifted from targets and calculate trades needed.

**Planned Functionality**:
- Set target allocation percentages
- Threshold-based drift alerts (e.g., 5% tolerance)
- Trade recommendations to restore targets
- Tax-aware rebalancing suggestions
- Cash flow integration (use new money to rebalance)

**Key Displays**:
- Current vs target allocation table
- Drift percentage by asset class
- Recommended trades list
- Projected allocation after trades

### 4. Goals Tab

**Purpose**: Create and track investment goals with projection modelling.

**Planned Functionality**:
- Create investment goals (house deposit, education, travel)
- Set target amount and date
- Link specific accounts/holdings to goals
- Progress tracking with projections
- Monte Carlo simulation for probability of success
- Required contribution calculator

**Key Displays**:
- Goal cards with progress
- Projection chart to target
- Probability gauge
- Actions to improve probability

### 5. Tax Efficiency Tab

**Purpose**: Optimise tax wrapper usage and asset location.

**Planned Functionality**:
- ISA allowance usage tracking
- Tax-efficient wrapper recommendations
- Asset location optimisation (which assets in ISA vs GIA)
- CGT harvesting suggestions
- Dividend tax optimisation
- Bed and ISA opportunities

**Key Displays**:
- Tax wrapper utilisation summary
- Asset location comparison
- CGT position estimate
- Tax-saving opportunities list

### 6. Fees Tab

**Purpose**: Analyse investment costs and their impact on returns.

**Planned Functionality**:
- Fee breakdown by account and holding
- Platform fees tracking
- Fund OCF (ongoing charges figure) aggregation
- Fee impact projection (cost over 10, 20, 30 years)
- Lower-cost alternatives identification
- Total expense ratio calculation

**Key Displays**:
- Fee breakdown pie chart
- Fee impact on wealth projection
- Cost comparison table
- High-fee alerts

### 7. Strategy Tab

**Purpose**: Provide personalised investment recommendations based on complete financial picture.

**Planned Functionality**:
- Prioritised action list
- Risk profile alignment check
- Concentration risk warnings
- Suggested next investments
- Integration with other modules (protection, retirement)
- Scenario modelling

**Key Displays**:
- Recommendation cards with priority
- Risk alignment gauge
- Action checklist
- Links to relevant actions

---

## User Stories (Planned)

### Performance Tracking

**US-PERF-01**: As a user, I want to see my portfolio return over different periods, so that I understand performance.

**US-PERF-02**: As a user, I want to compare my returns to a benchmark, so that I know if I am doing well.

**US-PERF-03**: As a user, I want to see which holdings performed best/worst, so that I can review allocations.

### Portfolio Optimisation

**US-OPT-01**: As a user, I want to see my current allocation vs optimal, so that I understand improvement opportunities.

**US-OPT-02**: As a user, I want to see the efficient frontier, so that I understand risk-return trade-offs.

**US-OPT-03**: As a user, I want a diversification score, so that I know if I am well diversified.

### Rebalancing

**US-REB-01**: As a user, I want to set target allocations, so that the system can track drift.

**US-REB-02**: As a user, I want to be alerted when allocation drifts too far, so that I know when to rebalance.

**US-REB-03**: As a user, I want to see specific trades needed to rebalance, so that I can act easily.

### Investment Goals

**US-GOAL-01**: As a user, I want to create investment goals, so that I can track progress toward them.

**US-GOAL-02**: As a user, I want to see probability of reaching my goal, so that I can adjust if needed.

**US-GOAL-03**: As a user, I want to know required contributions, so that I can plan accordingly.

### Tax Efficiency

**US-TAX-01**: As a user, I want to see my ISA allowance usage, so that I maximise tax efficiency.

**US-TAX-02**: As a user, I want asset location suggestions, so that I minimise tax drag.

**US-TAX-03**: As a user, I want CGT position visibility, so that I can plan disposals.

### Fees

**US-FEE-01**: As a user, I want to see all my investment fees, so that I understand total cost.

**US-FEE-02**: As a user, I want to see fee impact over time, so that I understand long-term cost.

**US-FEE-03**: As a user, I want lower-cost alternatives, so that I can reduce expenses.

### Strategy

**US-STRAT-01**: As a user, I want personalised recommendations, so that I know what to do next.

**US-STRAT-02**: As a user, I want risk alignment feedback, so that I know if portfolio matches profile.

**US-STRAT-03**: As a user, I want concentration warnings, so that I avoid excessive risk.

---

## Technical Considerations

### Data Requirements

**For Performance**:
- Historical price data (external API needed)
- Transaction history (not currently captured)
- Dividend history (not currently captured)

**For Optimisation**:
- Return data for asset classes
- Correlation matrices
- Risk-free rate

**For Rebalancing**:
- User-defined target allocations (new data model)
- Drift thresholds (user preferences)

**For Goals**:
- Goal records (new data model)
- Goal-account linkages
- Monte Carlo simulation engine

**For Tax**:
- Purchase dates (currently not required)
- Disposal history (not currently captured)
- Tax rates from TaxConfigService

**For Fees**:
- Fund-level OCF data (external or user-entered)
- Platform fees (user-entered)

### Integration Points

- External data providers for pricing
- Monte Carlo simulation service (exists for retirement)
- TaxConfigService for UK tax rules
- Risk profile from user preferences

### Complexity Assessment

| Feature | Complexity | Dependencies |
|---------|------------|--------------|
| Performance | High | Historical data, calculations |
| Optimisation | Very High | Mathematical models, data |
| Rebalancing | Medium | Target allocation data |
| Goals | Medium | Monte Carlo, new models |
| Tax Efficiency | Medium | Purchase dates, tax config |
| Fees | Low | OCF data capture |
| Strategy | High | All module integration |

---

## Acceptance Criteria (High-Level)

### Performance Tracking
- Returns calculate correctly for all periods
- Benchmark comparison accurate
- Attribution identifies contribution from each holding

### Portfolio Optimisation
- Efficient frontier calculates correctly
- Optimal weights sum to 100%
- Risk metrics mathematically accurate

### Rebalancing
- Drift calculated against targets
- Trade recommendations achieve target
- Tax implications noted

### Investment Goals
- Goal progress tracks correctly
- Monte Carlo provides probability
- Required contributions calculate correctly

### Tax Efficiency
- ISA usage accurate
- Asset location considers tax impact
- CGT position estimates reasonable

### Fees
- Total fees aggregate correctly
- Projection assumptions clear
- Alternatives genuinely lower cost

### Strategy
- Recommendations personalised
- Priorities logical
- Actions achievable

---

## Dependencies

### Upstream Dependencies (Required Before Implementation)

- Holdings with complete cost basis
- User risk profile (to be implemented)
- Historical data integration (external)
- Transaction tracking (enhancement to holdings)

### Downstream Dependencies

- All other modules (for holistic strategy)
- Notifications system (for alerts)
- Action tracking (for completion monitoring)

---

## Implementation Phases

**Phase 1** (Near-term):
- Fees Tab (lowest complexity)
- Basic Performance (without benchmark)
- Simple Goals (without Monte Carlo)

**Phase 2** (Medium-term):
- Full Performance with benchmarks
- Rebalancing Tab
- Goals with Monte Carlo

**Phase 3** (Long-term):
- Portfolio Optimisation
- Tax Efficiency
- Strategy (full integration)

---

## Current State

All seven tabs currently display "Coming Soon" banners with feature descriptions. The UI structure exists but functionality is not implemented.

**Live Features**:
- Portfolio Overview (working)
- Holdings (working)

**Coming Soon** (this specification):
- Performance
- Portfolio Optimisation
- Rebalancing
- Goals
- Tax Efficiency
- Fees
- Strategy
