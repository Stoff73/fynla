// The one home for the Fyn structured capture form's state, validation and
// payload logic (props, data, computed, methods). Consumed as a mixin by
// both the web SFC (resources/js/components/Fyn/FynCaptureForm.vue) and the
// /m SFC, so a behaviour change is made once, here, for both surfaces.

// Each form's ids carry its own number: several forms of one kind can sit in
// the chat at once (one per person named, T20), and ids per kind and field
// alone sent a later form's labels and focus to the first form's inputs.
let formSequence = 0;

export const captureFormMixin = {
  props: {
    schema: { type: Object, required: true },
    errors: { type: Object, default: null },
    disabled: { type: Boolean, default: false },
    locked: { type: Boolean, default: false },
    values: { type: Object, default: null },
    // The record an edit form changes ({ type, id }); null on a capture form.
    record: { type: Object, default: null },
  },
  emits: ['submit', 'remove'],
  data() {
    const open = {};
    const answers = {};
    const none = {};
    // Lead fields (asked above the kind boxes, e.g. the spouse's income)
    // live under the '_lead' pseudo-kind.
    const lead = (this.values && this.values._lead) ? { ...this.values._lead } : {};
    answers._lead = lead;
    none._lead = {};
    (this.schema.kinds || []).forEach((kind) => {
      const given = this.values && this.values[kind.key] ? this.values[kind.key] : null;
      open[kind.key] = Boolean(given);
      answers[kind.key] = given ? { ...given } : {};
      none[kind.key] = {};
      // A null on a money_or_none field is the "none" answer, restored as the
      // ticked box (the mortgage's "No mortgage", the spouse income's "I don't know").
      kind.fields.forEach((fieldKey) => {
        if (given && this.schema.fields[fieldKey]?.type === 'money_or_none' && given[fieldKey] === null) none[kind.key][fieldKey] = true;
      });
    });
    (this.schema.lead_fields || []).forEach((fieldKey) => {
      if (this.schema.fields[fieldKey]?.type === 'money_or_none' && lead[fieldKey] === null) none._lead[fieldKey] = true;
    });
    formSequence += 1;
    return { open, answers, none, formUid: formSequence };
  },
  computed: {
    openKinds() { return (this.schema.kinds || []).filter((k) => this.open[k.key]); },
    leadSection() {
      const fields = this.schema.lead_fields || [];
      return fields.length ? { key: '_lead', label: null, fields } : null;
    },
    // Everything rendered as a field block: the lead section (if any) then the open kinds.
    sections() { return this.leadSection ? [this.leadSection, ...this.openKinds] : this.openKinds; },
    // Render order: lead fields first, then the kind boxes, then each open kind.
    blocks() {
      // A lead-only schema (the personal form) has no kind boxes to render.
      const kindsRow = (this.schema.kinds || []).length ? [{ key: '_kinds', type: 'kinds' }] : [];
      return this.leadSection ? [this.leadSection, ...kindsRow, ...this.openKinds] : [...kindsRow, ...this.openKinds];
    },
    isValid() {
      if (this.openKinds.length === 0 && !this.leadSection && !this.schema.allow_empty) return false;
      return this.sections.every((kind) => this.visibleFields(kind).every((fieldKey) => {
        if (!this.isRequired(kind.key, fieldKey)) return true;
        const type = this.field(fieldKey).type;
        if (type === 'money_or_none') return this.isNone(kind.key, fieldKey) || this.hasNumber(kind.key, fieldKey);
        if (type === 'choice') return Boolean(this.answers[kind.key][fieldKey]);
        if (type === 'text' || type === 'date' || type === 'email') return this.hasText(kind.key, fieldKey);
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
    inputId(kindKey, fieldKey) { return `fyn-form-${this.formUid}-${kindKey}-${fieldKey}`; },
    isNone(kindKey, fieldKey) { return Boolean(this.none[kindKey] && this.none[kindKey][fieldKey]); },
    hasNumber(kindKey, fieldKey) { const v = this.answers[kindKey][fieldKey]; return typeof v === 'number' && !Number.isNaN(v); },
    hasText(kindKey, fieldKey) { const v = this.answers[kindKey][fieldKey]; return typeof v === 'string' && v.trim() !== ''; },
    // A percent field's bounds come from the schema; the defaults are the ownership-share range.
    percentAttrs(fieldKey) { const f = this.field(fieldKey); return { min: f.min ?? 0.01, max: f.max ?? 99.99, step: f.step ?? 0.01 }; },
    conditionMet(kindKey, fieldKey) {
      const when = this.field(fieldKey).required_when;
      if (!when) return true;
      return (when.in || []).includes(this.answers[kindKey][when.field]);
    },
    // A `shown_when: { field, filled: true }` field shows only while that
    // field holds an amount (the mortgage details under the balance, T24);
    // hidden, it is never posted.
    shownWhenMet(kindKey, fieldKey) {
      const when = this.field(fieldKey).shown_when;
      if (!when || !when.filled) return true;
      const v = this.answers[kindKey][when.field];
      return typeof v === 'number' && v > 0 && !this.isNone(kindKey, when.field);
    },
    visibleFields(kind) { return kind.fields.filter((fieldKey) => (!this.field(fieldKey).required_when || this.conditionMet(kind.key, fieldKey)) && this.shownWhenMet(kind.key, fieldKey)); },
    isRequired(kindKey, fieldKey) {
      const f = this.field(fieldKey);
      return Boolean(f.required) || (Boolean(f.required_when) && this.conditionMet(kindKey, fieldKey));
    },
    labelFor(kindKey, fieldKey) { return this.field(fieldKey).label + (this.isRequired(kindKey, fieldKey) ? ' *' : ''); },
    setNumber(kindKey, fieldKey, raw) {
      const n = raw === '' ? null : Number(raw);
      this.answers[kindKey] = { ...this.answers[kindKey], [fieldKey]: n === null || Number.isNaN(n) ? undefined : n };
    },
    // Never trim here: the input is :value-bound, so a trimmed model value is
    // written straight back over the space the user just typed and no text
    // field can ever hold a space (Laura, 2026-09-18: "Employer and trading
    // name doesn't allow spaces"). Trimming happens on submit.
    setText(kindKey, fieldKey, raw) {
      const t = String(raw ?? '');
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
      this.sections.forEach((kind) => {
        const out = {};
        this.visibleFields(kind).forEach((fieldKey) => {
          let v = this.answers[kind.key][fieldKey];
          if (typeof v === 'string') { v = v.trim(); if (v === '') return; }
          if (v !== undefined) out[fieldKey] = v;
        });
        answers[kind.key] = out;
      });
      this.$emit('submit', { name: this.schema.name, answers, ...(this.record ? { record: this.record } : {}) });
    },
    // Remove the record an edit form is showing. The server confirms in words.
    remove() {
      if (!this.record || this.locked || this.disabled) return;
      this.$emit('remove', { name: this.schema.name, answers: {}, record: this.record, delete: true });
    },
  },
};
