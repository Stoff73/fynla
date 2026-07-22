<script>
import { mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';

/**
 * Read-only summary of captured personal / family / employment /
 * expenditure data. Rendered alongside the onboarding chat when the
 * director is in a profile-review pause state (layout='standard').
 *
 * Edits happen via chat — the asset_capture retraction block instructs
 * Fyn to emit update_profile / update_record when the user corrects a
 * previously-captured fact. This panel never renders inline form inputs.
 *
 * No icons anywhere (CLAUDE.md §14). Palette tokens only.
 */
export default {
    name: 'ProfileReviewPanel',
    mixins: [currencyMixin],
    computed: {
        ...mapGetters('auth', ['user']),
        ...mapGetters('familyMembers', { familyMembers: 'familyMembers' }),
        personalLines() {
            if (!this.user) return [];
            const lines = [];
            const name = [this.user.first_name, this.user.surname].filter(Boolean).join(' ');
            if (name) lines.push({ label: 'Name', value: name });
            if (this.user.date_of_birth) {
                lines.push({ label: 'Date of birth', value: this.formatDob(this.user.date_of_birth) });
            }
            if (this.user.marital_status) {
                lines.push({ label: 'Marital status', value: this.labelMaritalStatus(this.user.marital_status) });
            }
            return lines;
        },
        spouse() {
            return (this.familyMembers || []).find((m) => m.relationship === 'spouse') || null;
        },
        dependants() {
            return (this.familyMembers || []).filter(
                (m) => m.relationship !== 'spouse' && (m.is_dependent ?? false),
            );
        },
        employmentLines() {
            if (!this.user) return [];
            const lines = [];
            if (this.user.employment_status) {
                lines.push({ label: 'Employment', value: this.labelEmploymentStatus(this.user.employment_status) });
            }
            if (this.user.employer) lines.push({ label: 'Employer', value: this.user.employer });
            if (this.user.occupation) lines.push({ label: 'Role', value: this.user.occupation });
            const income = Number(this.user.annual_employment_income ?? 0) + Number(this.user.annual_self_employment_income ?? 0);
            if (income > 0) {
                lines.push({ label: 'Annual income', value: this.formatCurrency(income) });
            }
            return lines;
        },
        expenditureLines() {
            if (!this.user) return [];
            const lines = [];
            const monthly = Number(this.user.monthly_expenditure ?? 0);
            if (monthly > 0) {
                lines.push({ label: 'Monthly expenditure', value: this.formatCurrency(monthly) });
            }
            return lines;
        },
    },
    methods: {
        formatDob(raw) {
            try {
                const d = new Date(raw);
                return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
            } catch {
                return raw;
            }
        },
        labelMaritalStatus(value) {
            const map = {
                single: 'Single',
                married: 'Married',
                civil_partnership: 'Civil partnership',
                divorced: 'Divorced',
                widowed: 'Widowed',
            };
            return map[value] || value;
        },
        labelEmploymentStatus(value) {
            const map = {
                employed: 'Full-time',
                full_time: 'Full-time',
                part_time: 'Part-time',
                self_employed: 'Self-employed',
                retired: 'Retired',
                unemployed: 'Not working',
            };
            return map[value] || value;
        },
        dependantAge(member) {
            if (!member.date_of_birth) return null;
            try {
                const dob = new Date(member.date_of_birth);
                const now = new Date();
                let age = now.getFullYear() - dob.getFullYear();
                if (
                    now.getMonth() < dob.getMonth()
                    || (now.getMonth() === dob.getMonth() && now.getDate() < dob.getDate())
                ) {
                    age--;
                }
                return age;
            } catch {
                return null;
            }
        },
    },
};
</script>

<template>
    <section class="card card-sm bg-white border border-light-gray p-5 space-y-5">
        <header>
            <h3 class="text-lg font-bold text-horizon-500">Your profile so far</h3>
            <p class="text-sm text-neutral-500">Tell Fyn if anything needs changing.</p>
        </header>

        <div v-if="personalLines.length">
            <h4 class="text-sm font-bold text-horizon-500 uppercase tracking-wide mb-2">About you</h4>
            <dl class="text-sm">
                <div v-for="line in personalLines" :key="line.label" class="flex justify-between py-1 border-b border-light-gray last:border-0">
                    <dt class="text-neutral-500">{{ line.label }}</dt>
                    <dd class="text-horizon-500 font-semibold">{{ line.value }}</dd>
                </div>
            </dl>
        </div>

        <div v-if="spouse">
            <h4 class="text-sm font-bold text-horizon-500 uppercase tracking-wide mb-2">Spouse</h4>
            <dl class="text-sm">
                <div class="flex justify-between py-1 border-b border-light-gray last:border-0">
                    <dt class="text-neutral-500">Name</dt>
                    <dd class="text-horizon-500 font-semibold">{{ [spouse.first_name, spouse.last_name].filter(Boolean).join(' ') }}</dd>
                </div>
                <div v-if="spouse.date_of_birth" class="flex justify-between py-1 border-b border-light-gray last:border-0">
                    <dt class="text-neutral-500">Date of birth</dt>
                    <dd class="text-horizon-500 font-semibold">{{ formatDob(spouse.date_of_birth) }}</dd>
                </div>
            </dl>
        </div>

        <div v-if="dependants.length">
            <h4 class="text-sm font-bold text-horizon-500 uppercase tracking-wide mb-2">Dependants</h4>
            <ul class="text-sm space-y-1">
                <li v-for="d in dependants" :key="d.id" class="flex justify-between py-1 border-b border-light-gray last:border-0">
                    <span class="text-horizon-500 font-semibold">{{ d.first_name }}</span>
                    <span class="text-neutral-500">
                        {{ d.relationship }}{{ dependantAge(d) !== null ? `, aged ${dependantAge(d)}` : '' }}
                    </span>
                </li>
            </ul>
        </div>

        <div v-if="employmentLines.length">
            <h4 class="text-sm font-bold text-horizon-500 uppercase tracking-wide mb-2">Employment</h4>
            <dl class="text-sm">
                <div v-for="line in employmentLines" :key="line.label" class="flex justify-between py-1 border-b border-light-gray last:border-0">
                    <dt class="text-neutral-500">{{ line.label }}</dt>
                    <dd class="text-horizon-500 font-semibold">{{ line.value }}</dd>
                </div>
            </dl>
        </div>

        <div v-if="expenditureLines.length">
            <h4 class="text-sm font-bold text-horizon-500 uppercase tracking-wide mb-2">Expenditure</h4>
            <dl class="text-sm">
                <div v-for="line in expenditureLines" :key="line.label" class="flex justify-between py-1 border-b border-light-gray last:border-0">
                    <dt class="text-neutral-500">{{ line.label }}</dt>
                    <dd class="text-horizon-500 font-semibold">{{ line.value }}</dd>
                </div>
            </dl>
        </div>
    </section>
</template>
