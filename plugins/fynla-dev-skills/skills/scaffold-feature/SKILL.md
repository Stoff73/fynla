---
name: scaffold-feature
description: Scaffold a new feature with all required files following Fynla conventions. Creates controller, service, model, migration, Vue component, store module, API service, route, form request, and test files.
disable-model-invocation: true
---

# Scaffold Feature

Scaffold all files needed for a new feature in the Fynla codebase, following established conventions.

## Required Input

Ask the user for:
1. **Feature name** (e.g., "Trust", "Gift", "Annuity")
2. **Module** (Protection, Savings, Investment, Retirement, Estate, Goals, or new)
3. **Has CRUD?** (create/read/update/delete operations)
4. **Has joint ownership?** (needs joint_owner_id pattern)
5. **Needs analysis/calculation?** (agent integration)

## Files to Generate

### Backend

#### 1. Model (`app/Models/{Module}/{FeatureName}.php`)
```php
<?php
declare(strict_types=1);

namespace App\Models\{Module};

use App\Traits\Auditable;
use App\Traits\HasJointOwnership; // if joint ownership
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class {FeatureName} extends Model
{
    use HasFactory, Auditable, SoftDeletes;
    use HasJointOwnership; // if joint ownership

    protected $fillable = [
        'user_id',
        // feature-specific fields
    ];

    public function user() { return $this->belongsTo(User::class); }
}
```

#### 2. Migration (`database/migrations/{timestamp}_create_{table}_table.php`)
- Use anonymous class pattern with `declare(strict_types=1)`
- Include `user_id` foreign key with cascade delete
- Include `joint_owner_id` if joint ownership (nullable, set null on delete)
- Include `ownership_type` enum if joint ownership
- Include `timestamps()` and `softDeletes()`
- Add appropriate indexes

#### 3. Controller (`app/Http/Controllers/Api/{Module}/{FeatureName}Controller.php`)
- Inject agent or service via constructor (`private readonly`)
- Standard methods: `index`, `store`, `show`, `update`, `destroy`
- Use `SanitizedErrorResponse` trait
- Return consistent JSON: `{ success, message, data }`
- Status codes: 200 (get/update/delete), 201 (create)

#### 4. Form Request (`app/Http/Requests/{Module}/Store{FeatureName}Request.php`)
- Use `ValidationLimits` for numeric bounds
- Use `Rule::in()` for enum validation
- Include custom `messages()` for user-friendly errors
- Include `attributes()` for field name mapping

#### 5. Service (`app/Services/{Module}/{FeatureName}Service.php`)
- `declare(strict_types=1)`
- Constructor injection with `private readonly`
- Inject `TaxConfigService` if tax calculations needed
- Use `StructuredLogging` trait
- Pure methods: accept models/primitives, return arrays/scalars

#### 6. Routes (add to `routes/api.php`)
- Wrap in `auth:sanctum` middleware
- Group under module prefix
- Standard CRUD routes
- Add calculation endpoints if needed

#### 7. Factory (`database/factories/{Module}/{FeatureName}Factory.php`)
- Use `fake()` for realistic test data
- Add state methods for common variants
- Include `'user_id' => User::factory()` default

#### 8. Test (`tests/Unit/Services/{Module}/{FeatureName}ServiceTest.php`)
- `declare(strict_types=1)`
- Use `describe()` / `it()` Pest syntax
- Mock `TaxConfigService` if injected
- Test core calculations with known inputs/outputs

#### 9. Feature Test (`tests/Feature/{Module}/{FeatureName}ApiTest.php`)
- Seed `TaxConfigurationSeeder` in `beforeEach()`
- Test CRUD operations with `actingAs($user)`
- Test data isolation (user cannot access other user's data)
- Test validation (invalid input returns 422)

### Frontend

#### 10. API Service (`resources/js/services/{featureName}Service.js`)
```javascript
import api from './api';

const {featureName}Service = {
    async getData() { return (await api.get('/{module}/{feature}')).data; },
    async create(data) { return (await api.post('/{module}/{feature}', data)).data; },
    async update(id, data) { return (await api.put(`/{module}/{feature}/${id}`, data)).data; },
    async delete(id) { return (await api.delete(`/{module}/{feature}/${id}`)).data; },
};

export default {featureName}Service;
```

#### 11. Vuex Store (update `resources/js/store/modules/{module}.js`)
- Add state fields for the new feature data
- Add mutations: `set{Feature}s`, `add{Feature}`, `update{Feature}`, `remove{Feature}`
- Add actions: `fetch{Feature}s`, `create{Feature}`, `update{Feature}`, `delete{Feature}`
- Add getters as needed

#### 12. Vue Component (`resources/js/components/{Module}/{FeatureName}List.vue`)
- Import `currencyMixin` and `previewModeMixin`
- Use `mapGetters` and `mapActions` from Vuex
- Use `v-preview-disabled` on action buttons
- Follow component naming: PascalCase, multi-word, descriptive

#### 13. Form Modal (`resources/js/components/{Module}/{FeatureName}FormModal.vue`)
- `<form @submit.prevent="handleSubmit">`
- Emit `save` (not `submit`) to parent
- Handle validation errors from 422 responses
- Use `currencyMixin` for financial inputs

## Conventions Checklist

After scaffolding, verify:
- [ ] All PHP files have `declare(strict_types=1);`
- [ ] Model uses `Auditable` trait
- [ ] No hardcoded tax values (use `TaxConfigService`)
- [ ] Ownership uses canonical enums (`individual`, `joint`, `tenants_in_common`, `trust`)
- [ ] Currency displayed via `currencyMixin` (not local formatting)
- [ ] Form modal emits `save` not `submit`
- [ ] No amber/orange colours in UI
- [ ] No acronyms in user-facing text (except ISA)
- [ ] British spelling in user-facing text, American in code
- [ ] Routes added to `PreviewWriteInterceptor` excluded list if auth-related
- [ ] Architecture tests still pass: `./vendor/bin/pest --testsuite=Architecture`
