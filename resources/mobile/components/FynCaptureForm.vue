<template>
  <form class="md-fyn__form" @submit.prevent="submit">
    <template v-for="block in blocks" :key="block.key">
      <div v-if="block.type === 'kinds'">
        <p v-if="schema.kinds_prompt" class="md-fyn__form-kind-title">{{ schema.kinds_prompt }}</p>
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
      </div>

      <div v-else class="md-fyn__form-section">
        <p v-if="block.label" class="md-fyn__form-kind-title">{{ block.label }}</p>
        <p v-if="errors?.[block.key]?.message" class="md-fyn__form-error">{{ errors[block.key].message }}</p>

        <div v-for="fieldKey in visibleFields(block)" :key="fieldKey" class="md-fyn__form-field">
          <label class="md-fyn__form-label" :for="inputId(block.key, fieldKey)">{{ labelFor(block.key, fieldKey) }}</label>

          <template v-if="field(fieldKey).type === 'money' || field(fieldKey).type === 'money_or_none'">
            <div class="md-fyn__form-money">
              <span>£</span>
              <input
                :id="inputId(block.key, fieldKey)"
                :name="block.key + '.' + fieldKey"
                type="number"
                inputmode="decimal"
                min="0"
                step="1"
                class="m-field"
                :disabled="disabled || locked || isNone(block.key, fieldKey)"
                :value="answers[block.key][fieldKey] ?? ''"
                @input="setNumber(block.key, fieldKey, $event.target.value)"
              >
            </div>
            <label v-if="field(fieldKey).type === 'money_or_none'" class="md-fyn__form-choices">
              <input
                type="checkbox"
                :name="block.key + '.' + fieldKey + '__none'"
                :checked="isNone(block.key, fieldKey)"
                :disabled="disabled || locked"
                @change="setNone(block.key, fieldKey, $event.target.checked)"
              >
              {{ field(fieldKey).none_label }}
            </label>
          </template>

          <div v-else-if="field(fieldKey).type === 'choice'" class="md-fyn__form-choices">
            <label v-for="option in field(fieldKey).options" :key="option.value">
              <input
                type="radio"
                :name="block.key + '.' + fieldKey"
                :value="option.value"
                :checked="answers[block.key][fieldKey] === option.value"
                :disabled="disabled || locked"
                @change="setChoice(block.key, fieldKey, option.value)"
              >
              {{ option.label }}
            </label>
          </div>

          <input
            v-else-if="field(fieldKey).type === 'percent'"
            :id="inputId(block.key, fieldKey)"
            :name="block.key + '.' + fieldKey"
            type="number"
            inputmode="decimal"
            v-bind="percentAttrs(fieldKey)"
            class="m-field"
            :disabled="disabled || locked"
            :value="answers[block.key][fieldKey] ?? ''"
            @input="setNumber(block.key, fieldKey, $event.target.value)"
          >

          <input
            v-else-if="field(fieldKey).type === 'text' || field(fieldKey).type === 'email'"
            :id="inputId(block.key, fieldKey)"
            :name="block.key + '.' + fieldKey"
            :type="field(fieldKey).type"
            maxlength="255"
            autocomplete="off"
            class="m-field"
            :disabled="disabled || locked"
            :value="answers[block.key][fieldKey] ?? ''"
            @input="setText(block.key, fieldKey, $event.target.value)"
          >

          <input
            v-else-if="field(fieldKey).type === 'date'"
            :id="inputId(block.key, fieldKey)"
            :name="block.key + '.' + fieldKey"
            type="date"
            autocomplete="off"
            class="m-field"
            :disabled="disabled || locked"
            :value="answers[block.key][fieldKey] ?? ''"
            @input="setText(block.key, fieldKey, $event.target.value)"
          >

          <p v-if="field(fieldKey).hint" class="md-fyn__form-hint">{{ field(fieldKey).hint }}</p>
          <p v-if="errors?.[block.key]?.fields?.[fieldKey]" class="md-fyn__form-error">{{ errors[block.key].fields[fieldKey] }}</p>
        </div>
      </div>
    </template>

    <div v-if="!locked" class="md-fyn__form-actions">
      <button type="submit" class="m-btn" :disabled="disabled || !isValid">{{ schema.submit_label || 'Save' }}</button>
      <button v-if="record && schema.edit" type="button" class="m-btn-ghost" :disabled="disabled" @click="remove">Remove</button>
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
