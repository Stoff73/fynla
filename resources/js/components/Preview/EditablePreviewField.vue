<template>
    <div class="relative inline-block group">
        <!-- Display mode -->
        <span
            v-if="!isEditing"
            class="cursor-pointer rounded px-1 -mx-1 transition-all"
            :class="displayClasses"
            @click="handleClick"
            :title="isPersonalInfo ? 'Personal info - changes not saved' : 'Click to edit'"
        >
            {{ formattedValue }}

            <!-- Edit affordance icon -->
            <svg
                v-if="isPreviewMode"
                class="inline-block w-3.5 h-3.5 ml-1 text-gray-400 opacity-0 group-hover:opacity-100 transition-opacity"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
            </svg>
        </span>

        <!-- Edit mode -->
        <input
            v-else
            ref="input"
            v-model="editValue"
            :type="inputType"
            class="border border-primary-300 rounded px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 w-32"
            :class="inputClasses"
            @blur="saveEdit"
            @keyup.enter="saveEdit"
            @keyup.escape="cancelEdit"
        />

        <!-- Personal info indicator -->
        <span
            v-if="isPersonalInfo && isPreviewMode && !isEditing"
            class="absolute -left-5 top-1/2 -translate-y-1/2"
            title="Personal information - changes not saved"
        >
            <svg class="h-4 w-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </span>

        <!-- Modified indicator -->
        <span
            v-if="isModified && !isEditing"
            class="absolute -right-4 top-0 w-2 h-2 bg-primary-500 rounded-full"
            title="Modified from original"
        />
    </div>

    <!-- Personal info warning modal -->
    <PersonalInfoWarningModal
        :is-open="showWarningModal"
        @close="showWarningModal = false"
        @register="goToRegister"
        @continue="proceedWithEdit"
    />
</template>

<script>
import { mapGetters, mapActions } from 'vuex';
import { isPersonalInfoField, getFieldType } from '@/utils/previewFieldConfig';
import PersonalInfoWarningModal from './PersonalInfoWarningModal.vue';

export default {
    name: 'EditablePreviewField',

    components: {
        PersonalInfoWarningModal,
    },

    props: {
        value: {
            required: true,
        },
        fieldPath: {
            type: String,
            required: true,
        },
        type: {
            type: String,
            default: null, // Auto-detect if not provided
        },
        currency: {
            type: String,
            default: 'GBP',
        },
        editable: {
            type: Boolean,
            default: true,
        },
    },

    data() {
        return {
            isEditing: false,
            editValue: null,
            showWarningModal: false,
            warningAcknowledged: false,
        };
    },

    computed: {
        ...mapGetters('preview', ['isPreviewMode', 'editedValues']),

        isPersonalInfo() {
            return isPersonalInfoField(this.fieldPath);
        },

        fieldType() {
            return this.type || getFieldType(this.fieldPath);
        },

        inputType() {
            switch (this.fieldType) {
                case 'currency':
                case 'number':
                case 'percentage':
                    return 'number';
                case 'date':
                    return 'date';
                default:
                    return 'text';
            }
        },

        formattedValue() {
            if (this.value === null || this.value === undefined) {
                return '-';
            }

            switch (this.fieldType) {
                case 'currency':
                    return new Intl.NumberFormat('en-GB', {
                        style: 'currency',
                        currency: this.currency,
                        minimumFractionDigits: 0,
                        maximumFractionDigits: 0,
                    }).format(this.value);

                case 'percentage':
                    return `${this.value}%`;

                case 'date':
                    if (!this.value) return '-';
                    return new Date(this.value).toLocaleDateString('en-GB', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric',
                    });

                default:
                    return this.value;
            }
        },

        isModified() {
            return this.editedValues && this.editedValues[this.fieldPath] !== undefined;
        },

        displayClasses() {
            if (!this.isPreviewMode || !this.editable) {
                return '';
            }

            return {
                'hover:bg-gray-100': !this.isPersonalInfo,
                'hover:bg-amber-50': this.isPersonalInfo,
            };
        },

        inputClasses() {
            return {
                'border-amber-300 focus:ring-amber-500': this.isPersonalInfo,
            };
        },
    },

    methods: {
        ...mapActions('preview', ['updateValue']),

        handleClick() {
            if (!this.isPreviewMode || !this.editable) {
                return;
            }

            if (this.isPersonalInfo && !this.warningAcknowledged) {
                this.showWarningModal = true;
            } else {
                this.startEdit();
            }
        },

        proceedWithEdit() {
            this.warningAcknowledged = true;
            this.showWarningModal = false;
            this.startEdit();
        },

        startEdit() {
            // Convert formatted value back to raw for editing
            this.editValue = this.value;
            this.isEditing = true;
            this.$nextTick(() => {
                if (this.$refs.input) {
                    this.$refs.input.focus();
                    this.$refs.input.select();
                }
            });
        },

        async saveEdit() {
            if (this.editValue !== this.value) {
                // Convert string input to appropriate type
                let processedValue = this.editValue;

                if (this.fieldType === 'currency' || this.fieldType === 'number') {
                    processedValue = parseFloat(this.editValue) || 0;
                } else if (this.fieldType === 'percentage') {
                    processedValue = parseFloat(this.editValue) || 0;
                }

                await this.updateValue({
                    path: this.fieldPath,
                    value: processedValue,
                });
            }
            this.isEditing = false;
        },

        cancelEdit() {
            this.editValue = null;
            this.isEditing = false;
        },

        goToRegister() {
            this.showWarningModal = false;
            this.$router.push('/register');
        },
    },
};
</script>
