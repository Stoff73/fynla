// The one home for the Fyn structured capture form's state, validation and
// payload logic (props, data, computed, methods). Consumed as a mixin by
// both the web SFC (resources/js/components/Fyn/FynCaptureForm.vue) and the
// /m SFC, so a behaviour change is made once, here, for both surfaces.

export const captureFormMixin = {
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
        if (type === 'text') return this.hasText(kind.key, fieldKey);
        return this.hasNumber(kind.key, fieldKey);
      }));
    },
  },
  methods: {
    field(key) { return this.schema.fields[key]; },
    isOpen(kindKey) { return Boolean(this.open[kindKey]); },
    toggle(kindKey) {
      this.open[kindKey] = !this.open[kindKey];
      if (this.open[kindKey]) this.focusFirstField(kindKey);
    },
    // CSJ 2026-09-16: opening a kind scrolls its fields into view and puts
    // the cursor in the first one, on both renderers.
    focusFirstField(kindKey) {
      this.$nextTick(() => {
        const kind = (this.schema.kinds || []).find((k) => k.key === kindKey);
        const first = kind && this.visibleFields(kind)[0];
        const el = first && document.getElementById(this.inputId(kindKey, first));
        if (!el) return;
        if (typeof el.scrollIntoView === 'function') el.scrollIntoView({ block: 'center', behavior: 'smooth' });
        el.focus({ preventScroll: true });
      });
    },
    inputId(kindKey, fieldKey) { return `fyn-form-${kindKey}-${fieldKey}`; },
    isNone(kindKey, fieldKey) { return Boolean(this.none[kindKey] && this.none[kindKey][fieldKey]); },
    hasNumber(kindKey, fieldKey) { const v = this.answers[kindKey][fieldKey]; return typeof v === 'number' && !Number.isNaN(v); },
    hasText(kindKey, fieldKey) { const v = this.answers[kindKey][fieldKey]; return typeof v === 'string' && v !== ''; },
    // A percent field's bounds come from the schema; the defaults are the ownership-share range.
    percentAttrs(fieldKey) { const f = this.field(fieldKey); return { min: f.min ?? 0.01, max: f.max ?? 99.99, step: f.step ?? 0.01 }; },
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
    setText(kindKey, fieldKey, raw) {
      const t = String(raw ?? '').trim();
      this.answers[kindKey] = { ...this.answers[kindKey], [fieldKey]: t === '' ? undefined : t };
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
