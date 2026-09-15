### Task 8: Web renderer — `FynCaptureForm.vue` in the chat panel

**Files:**
- Create: `resources/js/components/Fyn/FynCaptureForm.vue`
- Modify: `resources/js/components/Shared/AiChatPanel.vue` — template next to `FynQuickReplies` (:182-189), import/registration (:431/:444), computed near `latestQuickRepliesIndex` (:589-600), a method near `handleQuickReplySelect` (:1220)
- Test: `tests/frontend/components/Fyn/FynCaptureForm.test.js` (create)

**Interfaces:**
- Consumes: the schema array (Task 1), store action `sendMessage({ form })` (Task 7), `currencyMixin.formatCurrency`
- Produces: component props `schema`, `errors`, `disabled`, `locked`, `values`; emit `submit(form)` with `{ name, answers }` holding only the opened kinds

Behaviour the component must have:
- Two option boxes from `schema.kinds`; clicking toggles a kind open. Both may be open.
- Per open kind, fields in `kind.fields` order. `money` → `<input type="number" inputmode="decimal" min="0" step="1">` with a `£` prefix span; `money_or_none` → the same input plus a checkbox labelled `field.none_label` that sets the value to `null` and disables the input; `choice` → a radio group; `percent` → number input 0.01–99.99, shown only when `required_when` is satisfied, pre-filled with `field.default`.
- Labels from `field.label`; an asterisk `*` appended when `field.required` is true, or `required_when` is satisfied. The asterisk is text, no icon.
- Save (`schema.submit_label`) disabled unless at least one kind is open and every required field of each open kind has a value (`null` counts as a value only for `money_or_none` with the none box ticked).
- `errors[kind].message` renders under that kind's box; `errors[kind].fields[field]` beside the field; both in `text-raspberry-600`.
- `locked` renders inputs disabled with `values[kind][field]` filled in and no Save button.
- Classes: box `rounded-lg border-2 border-raspberry-500 px-3 py-2 text-raspberry-500 bg-white`, open box adds `bg-raspberry-50`; inputs `form-input`; labels `label`; hint `form-hint`; Save `btn-primary btn-sm`. No hex, no icons.

- [ ] **Step 1: Write the failing test**

```js
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import FynCaptureForm from '@/components/Fyn/FynCaptureForm.vue';

const schema = {
  name: 'property', submit_label: 'Save',
  kinds: [
    { key: 'main_residence', label: 'Home', fields: ['current_value', 'mortgage_outstanding_balance', 'ownership_type', 'ownership_percentage'] },
    { key: 'buy_to_let', label: 'Buy to let', fields: ['current_value', 'mortgage_outstanding_balance', 'monthly_rental_income', 'ownership_type', 'ownership_percentage'] },
  ],
  fields: {
    current_value: { type: 'money', label: 'Value', required: true, hint: 'The full value' },
    mortgage_outstanding_balance: { type: 'money_or_none', label: 'Mortgage outstanding', required: true, none_label: 'No mortgage' },
    monthly_rental_income: { type: 'money', label: 'Monthly rental income', required: true },
    ownership_type: { type: 'choice', label: 'Ownership', required: true, options: [{ value: 'individual', label: 'Individual' }, { value: 'joint', label: 'Joint' }, { value: 'tenants_in_common', label: 'Tenants in common' }] },
    ownership_percentage: { type: 'percent', label: 'Your share %', required: false, default: 50, required_when: { field: 'ownership_type', in: ['joint', 'tenants_in_common'] } },
  },
};

const box = (w, label) => w.findAll('button').find((b) => b.text() === label);

describe('FynCaptureForm', () => {
  it('shows the two kinds closed with Save disabled', () => {
    const w = mount(FynCaptureForm, { props: { schema } });
    expect(box(w, 'Home')).toBeTruthy();
    expect(box(w, 'Buy to let')).toBeTruthy();
    expect(w.find('input').exists()).toBe(false);
    expect(w.find('button[type="submit"]').attributes('disabled')).toBeDefined();
  });

  it('opens Home to its fields with asterisks on the required ones', async () => {
    const w = mount(FynCaptureForm, { props: { schema } });
    await box(w, 'Home').trigger('click');
    const labels = w.findAll('label').map((l) => l.text());
    expect(labels).toContain('Value *');
    expect(labels).toContain('Mortgage outstanding *');
    expect(labels).toContain('Ownership *');
    expect(labels.some((l) => l.startsWith('Your share'))).toBe(false);
    expect(labels).not.toContain('Monthly rental income *');
  });

  it('reveals the share at 50 for Joint and enables Save once required fields are filled', async () => {
    const w = mount(FynCaptureForm, { props: { schema } });
    await box(w, 'Home').trigger('click');
    await w.find('input[name="main_residence.current_value"]').setValue('750000');
    await w.find('input[name="main_residence.mortgage_outstanding_balance"]').setValue('325000');
    expect(w.find('button[type="submit"]').attributes('disabled')).toBeDefined();
    await w.find('input[type="radio"][value="joint"]').setValue(true);
    const share = w.find('input[name="main_residence.ownership_percentage"]');
    expect(share.exists()).toBe(true);
    expect(share.element.value).toBe('50');
    expect(w.findAll('label').map((l) => l.text())).toContain('Your share % *');
    expect(w.find('button[type="submit"]').attributes('disabled')).toBeUndefined();
  });

  it('emits only the opened kinds, with No mortgage as null', async () => {
    const w = mount(FynCaptureForm, { props: { schema } });
    await box(w, 'Buy to let').trigger('click');
    await w.find('input[name="buy_to_let.current_value"]').setValue('450000');
    await w.find('input[name="buy_to_let.mortgage_outstanding_balance__none"]').setValue(true);
    await w.find('input[name="buy_to_let.monthly_rental_income"]').setValue('1000');
    await w.find('input[type="radio"][value="individual"]').setValue(true);
    await w.find('form').trigger('submit');
    expect(w.emitted('submit')[0][0]).toEqual({ name: 'property', answers: {
      buy_to_let: { current_value: 450000, mortgage_outstanding_balance: null, monthly_rental_income: 1000, ownership_type: 'individual' },
    } });
  });

  it('renders kind and field errors and locks with values', async () => {
    const w = mount(FynCaptureForm, { props: { schema, errors: { buy_to_let: { message: 'You have reached your plan\'s property limit.', fields: { current_value: 'Too large' } } } } });
    await box(w, 'Buy to let').trigger('click');
    expect(w.text()).toContain('property limit');
    expect(w.text()).toContain('Too large');

    const locked = mount(FynCaptureForm, { props: { schema, locked: true, values: { main_residence: { current_value: 750000, mortgage_outstanding_balance: 325000, ownership_type: 'joint', ownership_percentage: 50 } } } });
    expect(locked.find('button[type="submit"]').exists()).toBe(false);
    expect(locked.find('input[name="main_residence.current_value"]').element.value).toBe('750000');
    expect(locked.find('input[name="main_residence.current_value"]').attributes('disabled')).toBeDefined();
  });
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `npx vitest run tests/frontend/components/Fyn/FynCaptureForm.test.js`
Expected: FAIL — module not found.

- [ ] **Step 3: Write the component**

```vue
<template>
  <form class="fyn-capture-form" @submit.prevent="submit">
    <div class="flex flex-wrap gap-2">
      <button
        v-for="kind in schema.kinds"
        :key="kind.key"
        type="button"
        class="rounded-lg border-2 border-raspberry-500 px-3 py-2 text-sm font-medium text-raspberry-500 bg-white transition-standard"
        :class="{ 'bg-raspberry-50': isOpen(kind.key) }"
        :aria-pressed="isOpen(kind.key) ? 'true' : 'false'"
        :disabled="disabled || locked"
        @click="toggle(kind.key)"
      >{{ kind.label }}</button>
    </div>

    <div v-for="kind in openKinds" :key="kind.key" class="mt-3 rounded-lg border border-light-gray bg-white p-3">
      <p class="text-body-sm font-medium text-horizon-500">{{ kind.label }}</p>
      <p v-if="errors?.[kind.key]?.message" class="mt-1 text-xs text-raspberry-600">{{ errors[kind.key].message }}</p>

      <div v-for="fieldKey in visibleFields(kind)" :key="fieldKey" class="form-group mt-2">
        <label class="label" :for="inputId(kind.key, fieldKey)">{{ labelFor(kind.key, fieldKey) }}</label>

        <template v-if="field(fieldKey).type === 'money' || field(fieldKey).type === 'money_or_none'">
          <div class="flex items-center gap-2">
            <span class="text-sm text-neutral-500">£</span>
            <input
              :id="inputId(kind.key, fieldKey)"
              :name="kind.key + '.' + fieldKey"
              type="number"
              inputmode="decimal"
              min="0"
              step="1"
              class="form-input"
              :disabled="disabled || locked || isNone(kind.key, fieldKey)"
              :value="answers[kind.key][fieldKey] ?? ''"
              @input="setNumber(kind.key, fieldKey, $event.target.value)"
            >
          </div>
          <label v-if="field(fieldKey).type === 'money_or_none'" class="mt-1 flex items-center gap-2 text-sm text-neutral-500">
            <input
              type="checkbox"
              :name="kind.key + '.' + fieldKey + '__none'"
              :checked="isNone(kind.key, fieldKey)"
              :disabled="disabled || locked"
              @change="setNone(kind.key, fieldKey, $event.target.checked)"
            >
            {{ field(fieldKey).none_label }}
          </label>
        </template>

        <div v-else-if="field(fieldKey).type === 'choice'" class="flex flex-wrap gap-3">
          <label v-for="option in field(fieldKey).options" :key="option.value" class="flex items-center gap-1 text-sm text-horizon-500">
            <input
              type="radio"
              :name="kind.key + '.' + fieldKey"
              :value="option.value"
              :checked="answers[kind.key][fieldKey] === option.value"
              :disabled="disabled || locked"
              @change="setChoice(kind.key, fieldKey, option.value)"
            >
            {{ option.label }}
          </label>
        </div>

        <input
          v-else-if="field(fieldKey).type === 'percent'"
          :id="inputId(kind.key, fieldKey)"
          :name="kind.key + '.' + fieldKey"
          type="number"
          inputmode="decimal"
          min="0.01"
          max="99.99"
          step="0.01"
          class="form-input"
          :disabled="disabled || locked"
          :value="answers[kind.key][fieldKey] ?? ''"
          @input="setNumber(kind.key, fieldKey, $event.target.value)"
        >

        <p v-if="field(fieldKey).hint" class="form-hint">{{ field(fieldKey).hint }}</p>
        <p v-if="errors?.[kind.key]?.fields?.[fieldKey]" class="mt-1 text-xs text-raspberry-600">{{ errors[kind.key].fields[fieldKey] }}</p>
      </div>
    </div>

    <div v-if="!locked" class="mt-3">
      <button type="submit" class="btn-primary btn-sm" :disabled="disabled || !isValid">{{ schema.submit_label || 'Save' }}</button>
    </div>
  </form>
</template>

<script>
/**
 * A server-driven capture form inside the Fyn chat (CaptureForms on the
 * server is the one schema home; this component knows kinds and field
 * types, never property). Emits `submit` with { name, answers } holding
 * only the opened kinds. No icons; the asterisk is text.
 */
export default {
  name: 'FynCaptureForm',
  props: {
    schema: { type: Object, required: true },
    errors: { type: Object, default: null },
    disabled: { type: Boolean, default: false },
    locked: { type: Boolean, default: false },
    values: { type: Object, default: null },
  },
  emits: ['submit'],
  data() {
    const open = {};
    const answers = {};
    const none = {};
    (this.schema.kinds || []).forEach((kind) => {
      const given = this.values && this.values[kind.key] ? this.values[kind.key] : null;
      open[kind.key] = Boolean(given);
      answers[kind.key] = given ? { ...given } : {};
      none[kind.key] = {};
      if (given && Object.prototype.hasOwnProperty.call(given, 'mortgage_outstanding_balance') && given.mortgage_outstanding_balance === null) {
        none[kind.key].mortgage_outstanding_balance = true;
      }
    });
    return { open, answers, none };
  },
  computed: {
    openKinds() { return (this.schema.kinds || []).filter((k) => this.open[k.key]); },
    isValid() {
      if (this.openKinds.length === 0) return false;
      return this.openKinds.every((kind) => this.visibleFields(kind).every((fieldKey) => {
        if (!this.isRequired(kind.key, fieldKey)) return true;
        const type = this.field(fieldKey).type;
        if (type === 'money_or_none') return this.isNone(kind.key, fieldKey) || this.hasNumber(kind.key, fieldKey);
        if (type === 'choice') return Boolean(this.answers[kind.key][fieldKey]);
        return this.hasNumber(kind.key, fieldKey);
      }));
    },
  },
  methods: {
    field(key) { return this.schema.fields[key]; },
    isOpen(kindKey) { return Boolean(this.open[kindKey]); },
    toggle(kindKey) { this.open[kindKey] = !this.open[kindKey]; },
    inputId(kindKey, fieldKey) { return `fyn-form-${kindKey}-${fieldKey}`; },
    isNone(kindKey, fieldKey) { return Boolean(this.none[kindKey] && this.none[kindKey][fieldKey]); },
    hasNumber(kindKey, fieldKey) { const v = this.answers[kindKey][fieldKey]; return typeof v === 'number' && !Number.isNaN(v); },
    conditionMet(kindKey, fieldKey) {
      const when = this.field(fieldKey).required_when;
      if (!when) return true;
      return (when.in || []).includes(this.answers[kindKey][when.field]);
    },
    visibleFields(kind) { return kind.fields.filter((fieldKey) => !this.field(fieldKey).required_when || this.conditionMet(kind.key, fieldKey)); },
    isRequired(kindKey, fieldKey) {
      const f = this.field(fieldKey);
      return Boolean(f.required) || (Boolean(f.required_when) && this.conditionMet(kindKey, fieldKey));
    },
    labelFor(kindKey, fieldKey) { return this.field(fieldKey).label + (this.isRequired(kindKey, fieldKey) ? ' *' : ''); },
    setNumber(kindKey, fieldKey, raw) {
      const n = raw === '' ? null : Number(raw);
      this.answers[kindKey] = { ...this.answers[kindKey], [fieldKey]: n === null || Number.isNaN(n) ? undefined : n };
    },
    setNone(kindKey, fieldKey, checked) {
      this.none[kindKey] = { ...this.none[kindKey], [fieldKey]: checked };
      if (checked) this.answers[kindKey] = { ...this.answers[kindKey], [fieldKey]: null };
      else { const next = { ...this.answers[kindKey] }; delete next[fieldKey]; this.answers[kindKey] = next; }
    },
    setChoice(kindKey, fieldKey, value) {
      const next = { ...this.answers[kindKey], [fieldKey]: value };
      const share = this.field('ownership_percentage');
      if (share && share.required_when && share.required_when.field === fieldKey) {
        if ((share.required_when.in || []).includes(value)) { if (next.ownership_percentage === undefined) next.ownership_percentage = share.default; }
        else delete next.ownership_percentage;
      }
      this.answers[kindKey] = next;
    },
    submit() {
      if (!this.isValid || this.locked || this.disabled) return;
      const answers = {};
      this.openKinds.forEach((kind) => {
        const out = {};
        this.visibleFields(kind).forEach((fieldKey) => {
          const v = this.answers[kind.key][fieldKey];
          if (v !== undefined) out[fieldKey] = v;
        });
        answers[kind.key] = out;
      });
      this.$emit('submit', { name: this.schema.name, answers });
    },
  },
};
</script>

<style scoped>
.fyn-capture-form { padding: 8px 0; }
</style>
```

`AiChatPanel.vue` — after the `FynQuickReplies` block add:

```html
<!-- Structured capture form (Fyn onboarding form turn) -->
<FynCaptureForm
  v-if="msg.role === 'capture_form'"
  :schema="msg.metadata?.capture_form"
  :errors="msg.metadata?.errors || null"
  :disabled="streaming || loading"
  :locked="!isCaptureFormOpen(idx)"
  :values="captureFormValues(idx)"
  @submit="handleCaptureFormSubmit"
/>
```

Import and register it next to `FynQuickReplies` (:431/:444). Add to `computed`:

```js
latestCaptureFormIndex() {
    for (let i = this.messages.length - 1; i >= 0; i -= 1) {
        if (this.messages[i]?.role === 'capture_form') return i;
    }
    return -1;
},
```

Add to `methods`:

```js
// A form is open only while it is the newest form and nothing has been
// answered after it; a refresh mid-step re-renders it open.
isCaptureFormOpen(idx) {
    if (idx !== this.latestCaptureFormIndex) return false;
    return !this.messages.slice(idx + 1).some((m) => m.role === 'user');
},
captureFormValues(idx) {
    const answered = this.messages.slice(idx + 1).find((m) => m.role === 'user' && m.metadata?.form?.answers);
    return answered ? answered.metadata.form.answers : null;
},
async handleCaptureFormSubmit(form) {
    if (this.streaming || this.loading) return;
    window.dispatchEvent(new Event('fyn-chat-interaction'));
    if (!await this.ensureConversation()) return;
    analyticsService.trackChatMessageSent(0);
    await this.sendMessage({ form });
},
```

- [ ] **Step 4: Run the tests**

Run: `npx vitest run tests/frontend/components/Fyn tests/frontend/store`
Expected: all pass.

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/Fyn/FynCaptureForm.vue resources/js/components/Shared/AiChatPanel.vue tests/frontend/components/Fyn/FynCaptureForm.test.js
git commit -m "feat(web): FynCaptureForm renders a capture_form turn in the chat panel and posts its answer"
```

---

