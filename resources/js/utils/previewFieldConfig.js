/**
 * Preview Field Configuration
 *
 * Classifies fields into personal info (requires warning) vs freely editable
 */

// Fields that are considered personal information
// When users try to edit these in preview mode, a warning modal should appear
export const PERSONAL_INFO_FIELDS = [
    // User personal details
    'user.name',
    'user.first_name',
    'user.last_name',
    'user.date_of_birth',
    'user.gender',
    'user.address_line_1',
    'user.address_line_2',
    'user.city',
    'user.county',
    'user.postcode',
    'user.email',
    'user.phone',
    'user.national_insurance_number',
    'user.employer',
    'user.occupation',
    'user.industry',

    // Spouse details
    'spouse.name',
    'spouse.first_name',
    'spouse.last_name',
    'spouse.date_of_birth',
    'spouse.gender',
    'spouse.email',
    'spouse.phone',
    'spouse.national_insurance_number',
    'spouse.employer',
    'spouse.occupation',

    // Family members (use * for array indices)
    'family_members.*.name',
    'family_members.*.first_name',
    'family_members.*.last_name',
    'family_members.*.date_of_birth',

    // Property addresses
    'properties.*.address_line_1',
    'properties.*.address_line_2',
    'properties.*.city',
    'properties.*.county',
    'properties.*.postcode',

    // Beneficiary names
    'bequests.*.beneficiary_name',
    'trusts.*.beneficiary_names',
];

/**
 * Check if a field path is considered personal information
 * @param {string} fieldPath - Dot-notation path like 'user.name' or 'properties.0.address_line_1'
 * @returns {boolean}
 */
export function isPersonalInfoField(fieldPath) {
    return PERSONAL_INFO_FIELDS.some(pattern => {
        // Convert pattern with * wildcards to regex
        // e.g., 'family_members.*.name' becomes /^family_members\.\d+\.name$/
        const regexPattern = '^' + pattern
            .replace(/\./g, '\\.')  // Escape dots
            .replace(/\*/g, '\\d+') // Replace * with digit pattern for array indices
            + '$';
        const regex = new RegExp(regexPattern);
        return regex.test(fieldPath);
    });
}

/**
 * Get field type for formatting/input purposes
 * @param {string} fieldPath
 * @returns {'currency' | 'percentage' | 'date' | 'number' | 'text'}
 */
export function getFieldType(fieldPath) {
    // Currency fields
    if (fieldPath.match(/value|balance|amount|premium|salary|income|price|payment|sum_assured|lump_sum|benefit/i)) {
        return 'currency';
    }

    // Percentage fields
    if (fieldPath.match(/percent|rate|allocation|score/i)) {
        return 'percentage';
    }

    // Date fields
    if (fieldPath.match(/date|dob|maturity|start|end/i)) {
        return 'date';
    }

    // Number fields (years, quantities)
    if (fieldPath.match(/years|months|term|count|number|age/i)) {
        return 'number';
    }

    // Default to text
    return 'text';
}

/**
 * Get which module(s) are affected by a field change
 * Used to determine which calculations to re-run
 * @param {string} fieldPath
 * @returns {string[]}
 */
export function getAffectedModules(fieldPath) {
    const modules = [];

    // Property changes affect net worth and estate (IHT)
    if (fieldPath.startsWith('properties') || fieldPath.startsWith('mortgages')) {
        modules.push('netWorth', 'estate');
    }

    // Savings affect net worth, savings module, and emergency fund
    if (fieldPath.startsWith('savings')) {
        modules.push('netWorth', 'savings');
    }

    // Investments affect net worth and investment module
    if (fieldPath.startsWith('investment')) {
        modules.push('netWorth', 'investment', 'estate');
    }

    // Pensions affect retirement and net worth
    if (fieldPath.match(/^(dc_pensions|db_pensions|state_pension)/)) {
        modules.push('retirement', 'netWorth', 'estate');
    }

    // Protection policies
    if (fieldPath.match(/^(life_insurance|critical_illness|income_protection|disability|sickness)/)) {
        modules.push('protection');
    }

    // Liabilities affect net worth and estate
    if (fieldPath.startsWith('liabilities')) {
        modules.push('netWorth', 'estate');
    }

    // IHT-specific items
    if (fieldPath.match(/^(gifts|trusts|iht_profile)/)) {
        modules.push('estate');
    }

    // User profile changes might affect protection calculations
    if (fieldPath.startsWith('user')) {
        if (fieldPath.match(/salary|income/)) {
            modules.push('protection', 'retirement');
        }
        if (fieldPath.match(/date_of_birth|age/)) {
            modules.push('protection', 'retirement', 'estate');
        }
    }

    // Expenditure affects emergency fund and protection
    if (fieldPath.startsWith('expenditure')) {
        modules.push('savings', 'protection');
    }

    // Family members affect protection needs
    if (fieldPath.startsWith('family_members')) {
        modules.push('protection');
    }

    return [...new Set(modules)]; // Remove duplicates
}

/**
 * Deep merge utility for combining base persona data with user edits
 * @param {object} base - Base persona data
 * @param {object} edits - User edits (keyed by dot-notation paths)
 * @returns {object} - Merged data
 */
export function mergePersonaEdits(base, edits) {
    if (!edits || Object.keys(edits).length === 0) {
        return base;
    }

    // Deep clone base to avoid mutations
    const result = JSON.parse(JSON.stringify(base));

    // Apply each edit by path
    for (const [path, value] of Object.entries(edits)) {
        setNestedValue(result, path, value);
    }

    return result;
}

/**
 * Set a value at a nested path
 * @param {object} obj
 * @param {string} path - Dot notation path like 'properties.0.current_value'
 * @param {*} value
 */
function setNestedValue(obj, path, value) {
    const parts = path.split('.');
    let current = obj;

    for (let i = 0; i < parts.length - 1; i++) {
        const key = isNaN(parts[i]) ? parts[i] : parseInt(parts[i]);
        if (current[key] === undefined) {
            // Create array or object as needed
            current[key] = isNaN(parts[i + 1]) ? {} : [];
        }
        current = current[key];
    }

    const lastKey = isNaN(parts[parts.length - 1])
        ? parts[parts.length - 1]
        : parseInt(parts[parts.length - 1]);
    current[lastKey] = value;
}

/**
 * Get a value from a nested path
 * @param {object} obj
 * @param {string} path
 * @returns {*}
 */
export function getNestedValue(obj, path) {
    const parts = path.split('.');
    let current = obj;

    for (const part of parts) {
        const key = isNaN(part) ? part : parseInt(part);
        if (current === undefined || current === null) {
            return undefined;
        }
        current = current[key];
    }

    return current;
}
