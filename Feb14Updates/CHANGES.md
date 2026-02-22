# February 14, 2026 Updates

## Summary
1. Created comprehensive test user seeder with real (non-preview) data
2. Updated development environment to show correct test credentials
3. **Fixed critical estate planning recommendation logic** (see [ESTATE_PLANNING_FIXES.md](ESTATE_PLANNING_FIXES.md))
4. **Consolidated estate planning to use EstateAgent as single source of truth** (see [ESTATE_AGENT_CONSOLIDATION.md](ESTATE_AGENT_CONSOLIDATION.md))
   - Eliminated duplicate recommendation systems
   - Both Current and Planning views now use EstateAgent
   - Trust £2m threshold enforced consistently across all views
5. **Fixed estate planning recommendations display** (see [ESTATE_RECOMMENDATIONS_FIX.md](ESTATE_RECOMMENDATIONS_FIX.md))
   - Fixed missing gifting strategies (Annual & PET)
   - Populated asset_breakdown with actual user assets
   - Corrected liquidity assessment to use real asset data
   - All recommendations now showing with proper IHT saving values

---

## 1. Development Script Updates

### File: `dev.sh`
**Changes:**
- Updated test login credentials display to match CLAUDE.md
- Changed from incorrect `demo@fps.com` / `password` to correct `chris@fynla.org` / `Password1!`
- Added note about verification code requirement

**Impact:** Developers will now see the correct test credentials when starting dev servers.

---

## 2. Test User Seeder Created

### File: `database/seeders/TestUserSeeder.php` (NEW)
**Purpose:** Comprehensive seeder for local testing with real (non-preview) data

### Test Accounts Created:

#### Primary User: Chris Jones
- **Email:** chris@fynla.org
- **Password:** Password1!
- **Admin Flag:** TRUE
- **Onboarding:** NOT COMPLETE (setup button visible)
- **Profile:**
  - CTO at FinTech Solutions Ltd
  - Age: 49
  - Income: £175,000/year
  - Target retirement: 58

#### Spouse: Angela Jones
- **Email:** c.jones@csjones.co
- **Password:** Password1!
- **Admin Flag:** FALSE
- **Onboarding:** NOT COMPLETE
- **Profile:**
  - Consultant Surgeon at Royal United Hospital Bath
  - Age: 47
  - Income: £135,000/year
  - Target retirement: 60

### Key Features:

#### High Surplus Income
- Combined household income: £310,000/year
- Low monthly expenditure: £1,800 each (vs £2,500 in Mitchells persona)
- **Result:** Significant monthly surplus for testing investment scenarios

#### Property Portfolio (5 Properties)
1. **Main Residence** - Bath (£950k)
2. **BTL London** - Canary Wharf (£485k, joint)
3. **BTL Manchester** - Northern Quarter (£320k, 45% tenants in common)
4. **BTL Edinburgh** - Castle View (£365k, joint)
5. **Holiday Let Cornwall** - St Ives (£425k, joint)

**Total Property Value:** £2,545,000
**Total Mortgages:** £718,000 outstanding

#### Financial Assets Summary
- **Savings:** £170,000 across 7 accounts
  - Current accounts (his, hers, joint)
  - Cash ISAs (both maxed)
  - Premium Bonds (£50k)
  - Emergency fund (£35k)

- **Investments:** £338,000 across 3 accounts
  - Chris's S&S ISA: £115k (global equities, high risk)
  - Angela's S&S ISA: £98k (LifeStrategy 80, balanced)
  - Joint GIA: £125k (diversified with bonds and gold)

- **Pensions:** £600,000+ total
  - Chris's workplace pension: £215k
  - Chris's SIPP: £385k (with detailed holdings)
  - Angela's NHS DB pension: £42k/year accrued

- **Insurance:**
  - Life insurance: £600k (in trust)
  - Critical illness: £250k

#### Estate Planning
- Mirror wills with trust provisions for children
- Bequests to Oliver and Sophie (50% each at age 25)
- Charitable bequests (£15k Cancer Research, £12k BHF)
- Children's Education Trust: £215k
- Chattels including vintage Porsche 911T (£110k)

#### Family
- **Oliver Jones** - 16 years old
- **Sophie Jones** - 13 years old

### Differences from Mitchells Persona

Based on `peak_earners` persona but with key modifications:

| Aspect | Mitchells | Chris & Angela |
|--------|-----------|----------------|
| **Income** | £265k combined | £310k combined |
| **Expenditure** | £2,500/month each | £1,800/month each |
| **Surplus** | Lower | **Higher** |
| **Properties** | 3 properties | **5 properties** |
| **Property Value** | ~£1.57m | **£2.55m** |
| **Location** | Guildford | **Bath** |
| **Preview Mode** | Yes | **No (real data)** |
| **Admin Access** | No | **Yes (Chris)** |
| **Onboarding** | Complete | **Incomplete** |

### Usage

```bash
# Seed test users
php artisan db:seed --class=TestUserSeeder

# Output
✓ Created test user: Chris Jones (chris@fynla.org) - ADMIN
✓ Created spouse: Angela Jones (c.jones@csjones.co)
✓ Onboarding: NOT COMPLETE (setup button will show)
✓ Total properties: 5
```

### Testing Benefits

1. **Admin Testing:** Chris has admin flag for testing admin features
2. **Onboarding Testing:** Both users have incomplete onboarding to test setup flow
3. **High-Value Portfolio:** Tests system with significant assets across all modules
4. **Multiple Properties:** Tests property management and BTL scenarios
5. **Complex Ownership:** Mix of joint, individual, and tenants in common
6. **Surplus Income:** Tests investment and pension maximization strategies
7. **Real Data:** Not preview mode, so changes persist and can be reseeded

---

## Technical Notes

### Database Enum Values Fixed
- Initial seeder used `knowledge_level: 'advanced'` which caused error
- Fixed to use valid enum value: `'experienced'`
- Valid values: `'novice'`, `'intermediate'`, `'experienced'`

### Seeder Architecture
- Follows same pattern as `PreviewUserSeeder.php`
- Deletes existing test users before creating new ones
- Creates all related records (properties, mortgages, pensions, etc.)
- Uses single-record architecture for joint assets
- Properly links spouse accounts

---

## Files Modified

1. **dev.sh** - Updated login credentials display
2. **database/seeders/TestUserSeeder.php** - NEW comprehensive test seeder

---

## Next Steps / Recommendations

1. Consider adding `TestUserSeeder` to `DatabaseSeeder` as optional seeder
2. Update documentation to reference chris@fynla.org for local testing
3. May want to create additional test personas for edge cases:
   - Single user (no spouse)
   - Widow/widower (testing transferred allowances)
   - Young saver (minimal assets)
   - Pre-retirement (testing decumulation)

---

## Login Instructions

**Development Environment:**
```bash
./dev.sh  # Start servers
```

Navigate to: http://localhost:8000

**Login:**
- Email: `chris@fynla.org`
- Password: `Password1!`
- Enter verification code when prompted (check console)

**Admin Access:** Chris has full admin privileges

**Setup Testing:** Both users show "Complete Setup" button (onboarding incomplete)
