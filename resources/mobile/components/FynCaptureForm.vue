<template>
  <form class="md-fyn__form" @submit.prevent="submit">
    <div class="md-fyn__form-kinds">
      <button
        v-for="kind in schema.kinds"
        :key="kind.key"
        type="button"
        class="md-fyn__form-kind"
        :class="{ 'md-fyn__form-kind--open': isOpen(kind.key) }"
        :aria-pressed="isOpen(kind.key) ? 'true' : 'false'"
        :disabled="disabled || locked"
        @click="toggle(kind.key)"
      >{{ kind.label }}</button>
    </div>

    <div v-for="kind in openKinds" :key="kind.key" class="md-fyn__form-section">
      <p class="md-fyn__form-label">{{ kind.label }}</p>
      <p v-if="errors?.[kind.key]?.message" class="md-fyn__form-error">{{ errors[kind.key].message }}</p>

      <div v-for="fieldKey in visibleFields(kind)" :key="fieldKey" class="md-fyn__form-field">
        <label class="md-fyn__form-label" :for="inputId(kind.key, fieldKey)">{{ labelFor(kind.key, fieldKey) }}</label>

        <template v-if="field(fieldKey).type === 'money' || field(fieldKey).type === 'money_or_none'">
          <div class="md-fyn__form-money">
            <span>£</span>
            <input
              :id="inputId(kind.key, fieldKey)"
              :name="kind.key + '.' + fieldKey"
              type="number"
              inputmode="decimal"
              min="0"
              step="1"
              class="m-field"
              :disabled="disabled || locked || isNone(kind.key, fieldKey)"
              :value="answers[kind.key][fieldKey] ?? ''"
              @input="setNumber(kind.key, fieldKey, $event.target.value)"
            >
          </div>
          <label v-if="field(fieldKey).type === 'money_or_none'" class="md-fyn__form-choices">
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

        <div v-else-if="field(fieldKey).type === 'choice'" class="md-fyn__form-choices">
          <label v-for="option in field(fieldKey).options" :key="option.value">
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
          class="m-field"
          :disabled="disabled || locked"
          :value="answers[kind.key][fieldKey] ?? ''"
          @input="setNumber(kind.key, fieldKey, $event.target.value)"
        >

        <p v-if="field(fieldKey).hint" class="md-fyn__form-hint">{{ field(fieldKey).hint }}</p>
        <p v-if="errors?.[kind.key]?.fields?.[fieldKey]" class="md-fyn__form-error">{{ errors[kind.key].fields[fieldKey] }}</p>
      </div>
    </div>

    <div v-if="!locked" class="md-fyn__form-actions">
      <button type="submit" class="m-btn" :disabled="disabled || !isValid">{{ schema.submit_label || 'Save' }}</button>
    </div>
  </form>
</template>

<script>
// The /m renderer for a server-driven capture form inside the Fyn chat.
// All state, validation and payload logic lives in captureFormMixin —
// the one home shared with the web renderer — so this file holds only
// the template and styling. No icons; the asterisk is text.
import { captureFormMixin } from '../utils/captureFormState.js';
export default { name: 'FynCaptureForm', mixins: [captureFormMixin] };
</script>
