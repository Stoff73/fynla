# Fynla Coding Standards

## PHP Standards (PSR-12)

### File Structure

- Use Unix LF (linefeed) line endings
- End files with a non-blank line
- Omit closing `?>` tag in PHP-only files
- File header order: `<?php` tag → declare(strict_types=1) → namespace → use statements → code

### Naming Conventions

- **Classes**: PascalCase (e.g., `ProtectionAgent`, `IHTCalculator`)
- **Methods/Properties**: camelCase (e.g., `calculateHumanCapital()`, `$annualIncome`)
- **PHP Keywords**: lowercase (use `bool`, `int`, `float` not `boolean`, `integer`, `double`)
- **No underscore prefixes** for properties or methods

### Formatting

- **Indentation**: 4 spaces (no tabs)
- **Line length**: 120 characters (recommended: 80)
- **No trailing whitespace**
- **One statement per line**
- **Visibility**: Must be declared for all properties and methods
- **Braces**: Required for all control structures
- **Opening braces**: Same line for methods/functions
- **elseif**: Use `elseif` instead of `else if`

### Example

```php
<?php

declare(strict_types=1);

namespace App\Agents;

use App\Services\TaxCalculator;

class ProtectionAgent extends BaseAgent
{
    public function calculateCoverageGap(float $income, int $age): array
    {
        $humanCapital = $this->calculateHumanCapital($income, $age);

        return [
            'gap' => $humanCapital - $existingCoverage,
            'adequacy_score' => min(100, ($existingCoverage / $humanCapital) * 100),
        ];
    }
}
```

## MySQL Standards

### Naming Conventions

- **Tables**: `snake_case` (e.g., `life_insurance_policies`, `dc_pensions`)
- **Columns**: `snake_case` (e.g., `user_id`, `sum_assured`, `premium_amount`)
- **Primary keys**: `id` (BIGINT UNSIGNED AUTO_INCREMENT)
- **Foreign keys**: `{table}_id` (e.g., `user_id`, `investment_account_id`)

### Schema Design

- **Engine**: InnoDB for all tables (ACID compliance, foreign key support)
- **Indexes**: Always define indexes on foreign keys
- **Data types**:
  - `BIGINT UNSIGNED` for IDs
  - `DECIMAL(15,2)` for currency values
  - `DECIMAL(5,4)` for percentages/interest rates
  - `VARCHAR` with appropriate length for strings
  - `DATE` for dates, `TIMESTAMP` for created_at/updated_at
- **Timestamps**: Include `created_at` and `updated_at` on all tables
- **Foreign keys**: Use `ON DELETE CASCADE` for dependent data, `ON DELETE SET NULL` for optional references

### Example

```sql
CREATE TABLE dc_pensions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    scheme_name VARCHAR(255) NOT NULL,
    current_fund_value DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    employee_contribution_percent DECIMAL(5,2),
    employer_contribution_percent DECIMAL(5,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Vue.js 3 Standards

### Component Naming

- **Component names**: Multi-word (except root `App`)
- **In JS/SFC**: PascalCase (`ProtectionDashboard.vue`, `EmergencyFundGauge.vue`)
- **In DOM templates**: kebab-case (`<protection-dashboard>`, `<emergency-fund-gauge>`)

### Component Structure

- **Component data**: Must be a function returning an object
- **One component per file**
- **Use single-file components** (.vue) for all components

### Props

- **Define with specifications**: type, required, default, validator
- **In component definitions**: camelCase
- **In templates**: kebab-case

### Template Syntax

- **Always use `key`** with `v-for`
- **Never use `v-if` on same element** as `v-for` (use computed properties)
- **Directive shorthands**: Use consistently (`:` for `v-bind`, `@` for `v-on`)
- **Keep expressions simple**: Move complex logic to computed properties
- **Quote attribute values**

### Component Organization

Order for component options:
1. name
2. components
3. props
4. data
5. computed
6. methods
7. lifecycle hooks

### Form Modal Event Naming

**CRITICAL**: Never use `@submit` as event name for custom events.

**Wrong**:
```vue
<!-- Parent component -->
<FormModal @submit="handleSubmit" />  ❌ WRONG - conflicts with native form submit
```

**Correct**:
```vue
<!-- Form Modal Component -->
<template>
  <form @submit.prevent="submitForm">
    <!-- form fields -->
  </form>
</template>

<script>
methods: {
  submitForm() {
    if (!this.validateForm()) {
      return;
    }
    this.$emit('save', formData);  // Use 'save' NOT 'submit'
  }
}
</script>

<!-- Parent Component -->
<template>
  <FormModal @save="handleSubmit" />  ✅ CORRECT - use @save
</template>
```

**Why**: `@submit` catches BOTH custom Vue events AND native form submit events, causing double submissions.

### Example Component

```vue
<template>
  <div class="protection-overview-card" @click="navigateToDetail">
    <h3>{{ title }}</h3>
    <div class="metric">
      <span class="value">{{ formattedScore }}</span>
      <span class="label">Coverage Score</span>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ProtectionOverviewCard',

  props: {
    title: {
      type: String,
      required: true,
    },
    adequacyScore: {
      type: Number,
      required: true,
      validator: (value) => value >= 0 && value <= 100,
    },
  },

  computed: {
    formattedScore() {
      return `${this.adequacyScore}%`;
    },
  },

  methods: {
    navigateToDetail() {
      this.$router.push('/protection');
    },
  },
};
</script>
```

## JavaScript/TypeScript Standards

### General Best Practices

- **Use `const` by default**, `let` when reassignment needed, never `var`
- **Arrow functions** for callbacks and anonymous functions
- **Template literals** for string interpolation
- **Destructuring** for objects and arrays
- **async/await** instead of promise chains
- **Meaningful variable names** (avoid single letters except in loops)

### Example

```javascript
// Good
const fetchUserData = async (userId) => {
  const response = await api.get(`/users/${userId}`);
  const { name, email, ...rest } = response.data;
  return { name, email };
};

// Bad
var getUserData = function(id) {
  return api.get('/users/' + id).then(function(resp) {
    var n = resp.data.name;
    var e = resp.data.email;
    return { n: n, e: e };
  });
};
```

## Laravel Best Practices

### Controllers

- Keep controllers thin - delegate business logic to services/agents
- Use Form Requests for validation
- Return consistent JSON responses for API endpoints

### Models

- Use Eloquent relationships (hasMany, belongsTo, etc.)
- Define fillable or guarded properties
- Use mutators and accessors for data transformation
- Keep models focused on data representation, not business logic

### Services/Agents

- Create dedicated service classes for business logic
- Agent classes should inherit from `BaseAgent`
- Keep methods focused and single-purpose
- Use dependency injection

### Routes

- Use route groups for related endpoints
- Use route model binding where appropriate
- Protect routes with middleware (auth, CSRF)

### Example

```php
// Agent
class RetirementAgent extends BaseAgent
{
    public function __construct(
        private TaxCalculator $taxCalculator,
        private PensionProjector $projector
    ) {}

    public function analyze(int $userId): array
    {
        $pensions = DCPension::where('user_id', $userId)->get();
        $projections = $this->projector->projectPensions($pensions);

        return $this->generateRecommendations($projections);
    }
}

// Controller
class RetirementController extends Controller
{
    public function analyze(RetirementAnalysisRequest $request, RetirementAgent $agent)
    {
        $analysis = $agent->analyze($request->user()->id);

        return response()->json([
            'success' => true,
            'data' => $analysis,
        ]);
    }
}
```

## Asset Ownership Pattern

### Ownership Types

All assets support three ownership types:
- `individual` - Solely owned by one person
- `joint` - Owned by two people (usually spouses)
- `trust` - Owned by a trust entity

### Database Schema Pattern

All asset tables include:
```php
$table->enum('ownership_type', ['individual', 'joint', 'trust'])->default('individual');
$table->bigInteger('joint_owner_id')->nullable();  // For joint ownership
$table->foreignId('trust_id')->nullable();         // For trust ownership
```

### Frontend Form Pattern

**CRITICAL**: Use exact values matching database enum:

```vue
<select v-model="formData.ownership_type">
  <option value="individual">Individual Owner</option>
  <option value="joint">Joint Owner</option>
  <option value="trust">Trust</option>
</select>
```

**DO NOT use 'sole'** - use 'individual'. The database enum requires these exact values.

### ISA Ownership Restriction

ISAs can ONLY be individually owned (UK tax rule). Backend validation enforces this:

```php
if ($validated['account_type'] === 'isa' && $validated['ownership_type'] !== 'individual') {
    return response()->json([
        'success' => false,
        'message' => 'ISAs can only be individually owned...',
    ], 422);
}
```

## Key Principles

1. **UK-Specific**: All calculations must follow UK tax rules (2025/26 tax year)
2. **Agent Pattern**: Encapsulate module logic in Agent classes
3. **Centralized Config**: Never hardcode tax rates - use `config/uk_tax_config.php`
4. **No Financial Advice**: Fynla is for demonstration/analysis only
5. **Data Isolation**: Users can only access their own data
6. **Progressive Disclosure**: Summaries first, details on demand
7. **Mobile-First**: Responsive design (320px to 2560px)
8. **Caching**: Cache expensive calculations
9. **Background Jobs**: Queue long-running calculations (>2 seconds)
10. **Testing**: Write Pest tests for all financial calculations
11. **Code Quality**: Follow PSR-12 for PHP, Vue.js style guide
12. **No Fictional Data**: Never auto-populate demo/test data
