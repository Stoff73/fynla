<template>
  <form class="fyn-capture-form" @submit.prevent="submit">
    <template v-for="block in blocks" :key="block.key">
      <div v-if="block.type === 'kinds'">
        <p v-if="schema.kinds_prompt" class="text-body-sm text-horizon-500 mb-2">{{ schema.kinds_prompt }}</p>
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
      </div>

      <div v-else class="mt-3 rounded-lg border border-light-gray bg-white p-3" :class="{ 'mt-0 mb-3': block.key === '_lead' }">
        <p v-if="block.label" class="text-body-sm font-medium text-horizon-500">{{ block.label }}</p>
        <p v-if="errors?.[block.key]?.message" class="mt-1 text-xs text-raspberry-600">{{ errors[block.key].message }}</p>

        <div v-for="fieldKey in visibleFields(block)" :key="fieldKey" class="form-group mt-2">
          <label class="label" :for="inputId(block.key, fieldKey)">{{ labelFor(block.key, fieldKey) }}</label>

          <template v-if="field(fieldKey).type === 'money' || field(fieldKey).type === 'money_or_none'">
            <div class="flex items-center gap-2">
              <span class="text-sm text-neutral-500">£</span>
              <input
                :id="inputId(block.key, fieldKey)"
                :name="block.key + '.' + fieldKey"
                type="number"
                inputmode="decimal"
                min="0"
                step="1"
                class="form-input"
                :disabled="disabled || locked || isNone(block.key, fieldKey)"
                :value="answers[block.key][fieldKey] ?? ''"
                @input="setNumber(block.key, fieldKey, $event.target.value)"
              >
            </div>
            <label v-if="field(fieldKey).type === 'money_or_none'" class="mt-1 flex items-center gap-2 text-sm text-neutral-500">
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

          <div v-else-if="field(fieldKey).type === 'choice'" class="flex flex-wrap gap-3">
            <label v-for="option in field(fieldKey).options" :key="option.value" class="flex items-center gap-1 text-sm text-horizon-500">
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
            class="form-input"
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
            class="form-input"
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
            class="form-input"
            :disabled="disabled || locked"
            :value="answers[block.key][fieldKey] ?? ''"
            @input="setText(block.key, fieldKey, $event.target.value)"
          >

          <p v-if="field(fieldKey).hint" class="form-hint">{{ field(fieldKey).hint }}</p>
          <p v-if="errors?.[block.key]?.fields?.[fieldKey]" class="mt-1 text-xs text-raspberry-600">{{ errors[block.key].fields[fieldKey] }}</p>
        </div>
      </div>
    </template>

    <div v-if="!locked" class="mt-3">
      <button type="submit" class="btn-primary btn-sm" :disabled="disabled || !isValid">{{ schema.submit_label || 'Save' }}</button>
    </div>
  </form>
</template>

<script>
// A server-driven capture form inside the Fyn chat (CaptureForms on the
// server is the one schema home; this component knows kinds and field
// types, never property). Emits `submit` with { name, answers } holding
// only the opened kinds. No icons; the asterisk is text.
//
// All state, validation and payload logic lives in captureFormMixin
// (resources/mobile/utils/captureFormState.js) — the one home shared with
// the /m renderer — so this file holds only the template and styling.
import { captureFormMixin } from '../../../mobile/utils/captureFormState.js';

export default {
  name: 'FynCaptureForm',
  mixins: [captureFormMixin],
};
</script>

<style scoped>
.fyn-capture-form { padding: 8px 0; }
</style>
