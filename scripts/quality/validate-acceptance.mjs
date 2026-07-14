import {
    lstatSync,
    readFileSync,
    readdirSync,
    realpathSync,
} from 'node:fs';
import {
    basename,
    dirname,
    extname,
    isAbsolute,
    relative,
    resolve,
    sep,
} from 'node:path';
import process from 'node:process';
import yaml from 'yaml';

const MAX_INPUT_BYTES = 1024 * 1024;
const SHA_PATTERN = /^(?:[0-9a-f]{40}|[0-9a-f]{64})$/;
const RFC3339_PATTERN = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})(?:\.(\d+))?(Z|[+-](\d{2}):(\d{2}))$/;
const RESULT_STATUSES = new Set(['passed', 'failed', 'blocked']);
const SECRET_QUERY_KEYS = new Set([
    'password',
    'password_confirmation',
    'passwd',
    'pwd',
    'token',
    'access_token',
    'auth_token',
    'bearer_token',
    'refresh_token',
    'secret',
    'client_secret',
    'code',
    'verification_code',
    'mfa_code',
    'api_key',
    'apikey',
]);

function reject(message) {
    throw new Error(message);
}

function requireObject(value, context) {
    if (value === null || typeof value !== 'object' || Array.isArray(value)) {
        reject(`${context} must be an object`);
    }

    return value;
}

function rejectUnknownKeys(value, allowedKeys, context) {
    const unknown = Object.keys(value).some((key) => !allowedKeys.has(key));
    if (unknown) {
        reject(`${context} contains an unsupported key`);
    }
}

function requireString(value, key, context) {
    if (typeof value[key] !== 'string' || value[key].trim() === '') {
        reject(`${context}.${key} is required`);
    }

    return value[key];
}

function requireArray(value, key, context, { allowEmpty = false } = {}) {
    if (!Array.isArray(value[key]) || (!allowEmpty && value[key].length === 0)) {
        reject(`${context}.${key} must be a non-empty array`);
    }

    return value[key];
}

function requireStringArray(value, key, context, options = {}) {
    const values = requireArray(value, key, context, options);
    if (values.some((item) => typeof item !== 'string' || item.trim() === '')) {
        reject(`${context}.${key} must contain only non-empty strings`);
    }

    return values;
}

function requireUnique(values, context) {
    if (new Set(values).size !== values.length) {
        reject(`${context} must contain unique items`);
    }

    return values;
}

function normaliseQueryKey(key) {
    return key
        .replace(/([a-z0-9])([A-Z])/g, '$1_$2')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '');
}

function requireHttpUrl(value, context) {
    if (!/^https?:\/\/\S+$/.test(value)) {
        reject(`${context} must begin with lower-case http:// or https:// and contain no whitespace`);
    }

    let parsed;
    try {
        parsed = new URL(value);
    } catch {
        reject(`${context} must be a valid URL`);
    }

    if (!['http:', 'https:'].includes(parsed.protocol)) {
        reject(`${context} must use HTTP or HTTPS`);
    }

    const containsSecretQuery = [...parsed.searchParams.keys()]
        .some((key) => SECRET_QUERY_KEYS.has(normaliseQueryKey(key)));
    if (parsed.username !== '' || parsed.password !== '' || containsSecretQuery) {
        reject(`${context} URL must not contain credentials or secret query keys`);
    }

    return parsed.origin;
}

function parseRfc3339(value) {
    const match = RFC3339_PATTERN.exec(value);
    if (match === null) {
        return null;
    }

    const year = Number(match[1]);
    const month = Number(match[2]);
    const day = Number(match[3]);
    const hour = Number(match[4]);
    const minute = Number(match[5]);
    const second = Number(match[6]);
    const fraction = match[7] || '';
    const offsetHour = match[9] === undefined ? 0 : Number(match[9]);
    const offsetMinute = match[10] === undefined ? 0 : Number(match[10]);
    const leapYear = year % 4 === 0 && (year % 100 !== 0 || year % 400 === 0);
    const daysInMonth = [31, leapYear ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

    if (
        month < 1
        || month > 12
        || day < 1
        || day > daysInMonth[month - 1]
        || hour > 23
        || minute > 59
        || second > 59
        || offsetHour > 23
        || offsetMinute > 59
    ) {
        return null;
    }

    const wholeSecondTimestamp = Date.parse(
        `${match[1]}-${match[2]}-${match[3]}T${match[4]}:${match[5]}:${match[6]}${match[8]}`,
    );

    return Number.isFinite(wholeSecondTimestamp)
        ? { wholeSecondTimestamp, fraction }
        : null;
}

function timestampPrecedes(candidate, reference) {
    if (candidate.wholeSecondTimestamp !== reference.wholeSecondTimestamp) {
        return candidate.wholeSecondTimestamp < reference.wholeSecondTimestamp;
    }

    const precision = Math.max(candidate.fraction.length, reference.fraction.length);
    const candidateFraction = candidate.fraction.padEnd(precision, '0');
    const referenceFraction = reference.fraction.padEnd(precision, '0');

    return candidateFraction < referenceFraction;
}

function requireEvidencePath(value, context) {
    if (typeof value !== 'string' || value.trim() === '') {
        reject(`${context} must be a safe relative evidence path`);
    }

    const hasScheme = /^[a-z][a-z0-9+.-]*:/i.test(value);
    const hasUnsafeRoot = isAbsolute(value) || /^[\\/]/.test(value) || /^[a-z]:[\\/]/i.test(value);
    const hasTraversal = value.split(/[\\/]/).includes('..');
    if (hasScheme || hasUnsafeRoot || hasTraversal || value.includes('\0')) {
        reject(`${context} must be a safe relative evidence path`);
    }
}

function requireEvidenceFile(evidencePath, resultFile, context) {
    const evidenceRoot = realpathSync(dirname(resultFile));
    const candidate = resolve(evidenceRoot, evidencePath);
    let stats;
    let actualPath;

    try {
        stats = lstatSync(candidate);
        const resultStats = lstatSync(resultFile);
        const isResultFile = stats.dev === resultStats.dev && stats.ino === resultStats.ino;
        if (!stats.isFile() || stats.isSymbolicLink() || stats.size === 0 || isResultFile) {
            reject(`${context}: evidence file is missing or unsafe`);
        }
        actualPath = realpathSync(candidate);
    } catch (error) {
        if (error instanceof Error && error.message === `${context}: evidence file is missing or unsafe`) {
            throw error;
        }
        reject(`${context}: evidence file is missing or unsafe`);
    }

    const containedPath = relative(evidenceRoot, actualPath);
    if (containedPath === '..' || containedPath.startsWith(`..${sep}`) || isAbsolute(containedPath)) {
        reject(`${context}: evidence file is missing or unsafe`);
    }
    if (actualPath === realpathSync(resultFile)) {
        reject(`${context}: evidence file is missing or unsafe`);
    }
}

function readDocument(file) {
    const label = basename(file);
    let stats;
    let source;

    try {
        stats = lstatSync(file);
        if (!stats.isFile() || stats.isSymbolicLink() || stats.size > MAX_INPUT_BYTES) {
            reject(`${label}: unsupported input file`);
        }
        source = readFileSync(file, 'utf8');
    } catch (error) {
        if (error instanceof Error && error.message.startsWith(`${label}:`)) {
            throw error;
        }
        reject(`${label}: cannot read input`);
    }

    try {
        if (extname(file) === '.json') {
            const strictDocument = yaml.parseDocument(source, {
                prettyErrors: false,
                strict: true,
                uniqueKeys: true,
            });
            if (strictDocument.errors.length > 0) {
                reject(`${label}: invalid JSON`);
            }

            return JSON.parse(source);
        }

        const document = yaml.parseDocument(source, {
            prettyErrors: false,
            strict: true,
        });
        if (document.errors.length > 0) {
            reject(`${label}: invalid YAML`);
        }

        return document.toJS({ maxAliasCount: 50 });
    } catch (error) {
        if (error instanceof Error && [
            `${label}: invalid JSON`,
            `${label}: invalid YAML`,
        ].includes(error.message)) {
            throw error;
        }
        reject(`${label}: invalid ${extname(file) === '.json' ? 'JSON' : 'YAML'}`);
    }
}

function validateManifest(value, file) {
    const context = basename(file);
    const manifest = requireObject(value, context);
    rejectUnknownKeys(manifest, new Set([
        'id',
        'finding_ids',
        'environment',
        'user_visible',
        'user',
        'surfaces',
        'negative_assertions',
        'cleanup',
    ]), context);

    const id = requireString(manifest, 'id', context);
    if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(id)) {
        reject(`${context}.id must be a kebab-case identifier`);
    }

    requireUnique(
        requireStringArray(manifest, 'finding_ids', context, { allowEmpty: true }),
        `${context}.finding_ids`,
    );
    const environment = requireString(manifest, 'environment', context);
    if (!/^[a-z][a-z0-9_-]*$/.test(environment)) {
        reject(`${context}.environment must be a lower-case identifier`);
    }
    if ('user_visible' in manifest && typeof manifest.user_visible !== 'boolean') {
        reject(`${context}.user_visible must be a boolean`);
    }

    const user = requireObject(manifest.user, `${context}.user`);
    rejectUnknownKeys(user, new Set(['kind', 'email_ref']), `${context}.user`);
    requireString(user, 'kind', `${context}.user`);
    const emailRef = requireString(user, 'email_ref', `${context}.user`);
    if (!/^[A-Z][A-Z0-9_]*$/.test(emailRef)) {
        reject(`${context}.user.email_ref must name an environment reference`);
    }

    const surfaces = requireArray(manifest, 'surfaces', context);
    const surfaceNames = new Set();
    const origins = new Set();
    const orderedSteps = [];
    surfaces.forEach((surfaceValue, surfaceIndex) => {
        const surfaceContext = `${context}.surfaces[${surfaceIndex}]`;
        const surface = requireObject(surfaceValue, surfaceContext);
        rejectUnknownKeys(surface, new Set(['name', 'start_url', 'steps']), surfaceContext);
        const name = requireString(surface, 'name', surfaceContext);
        if (surfaceNames.has(name)) {
            reject(`${context}.surfaces must use unique names`);
        }
        surfaceNames.add(name);

        const startUrl = requireString(surface, 'start_url', surfaceContext);
        origins.add(requireHttpUrl(startUrl, `${surfaceContext}.start_url`));

        const steps = requireArray(surface, 'steps', surfaceContext);
        steps.forEach((stepValue, stepIndex) => {
            const stepContext = `${surfaceContext}.steps[${stepIndex}]`;
            const step = requireObject(stepValue, stepContext);
            rejectUnknownKeys(step, new Set(['action', 'target', 'assert', 'evidence']), stepContext);
            const action = requireString(step, 'action', stepContext);
            const target = requireString(step, 'target', stepContext);
            const assertion = requireString(step, 'assert', stepContext);
            const evidence = requireString(step, 'evidence', stepContext);
            requireEvidencePath(evidence, `${stepContext}.evidence`);
            orderedSteps.push({
                surface: name,
                action,
                target,
                assertion,
                evidence,
            });
        });
    });

    if (manifest.user_visible !== false) {
        for (const requiredSurface of ['desktop', 'mobile']) {
            if (!surfaceNames.has(requiredSurface)) {
                reject(`${context}: user-visible manifests require desktop and mobile surfaces`);
            }
        }
    }

    const negativeAssertions = requireUnique(
        requireStringArray(manifest, 'negative_assertions', context),
        `${context}.negative_assertions`,
    );
    requireStringArray(manifest, 'cleanup', context);

    return {
        id,
        surfaces: surfaceNames,
        origins,
        orderedSteps,
        negativeAssertions,
    };
}

function validateStatus(value, context) {
    if (typeof value !== 'string' || !RESULT_STATUSES.has(value)) {
        reject(`${context} must be passed, failed, or blocked`);
    }
}

function validateResult(value, file, requestedSha, manifests) {
    const context = basename(file);
    const result = requireObject(value, context);
    rejectUnknownKeys(result, new Set([
        'manifest_id',
        'commit_sha',
        'started_at',
        'finished_at',
        'environment_url',
        'browser',
        'viewport',
        'status',
        'steps',
        'negative_assertions',
        'evidence',
        'diagnostic_notes',
    ]), context);

    const manifestId = requireString(result, 'manifest_id', context);
    const manifest = manifests.get(manifestId);
    if (!manifest) {
        reject(`${context}.manifest_id does not match a validated manifest`);
    }

    const commitSha = requireString(result, 'commit_sha', context);
    if (!SHA_PATTERN.test(commitSha)) {
        reject(`${context}.commit_sha must be a lower-case full commit SHA`);
    }
    if (commitSha !== requestedSha) {
        reject(`${context}: result commit SHA does not match requested release SHA`);
    }

    const startedAt = requireString(result, 'started_at', context);
    const finishedAt = requireString(result, 'finished_at', context);
    const startedTime = parseRfc3339(startedAt);
    const finishedTime = parseRfc3339(finishedAt);
    if (startedTime === null || finishedTime === null) {
        reject(`${context}: started_at and finished_at must be RFC3339 timestamps`);
    }
    if (timestampPrecedes(finishedTime, startedTime)) {
        reject(`${context}.finished_at must not precede started_at`);
    }

    const environmentUrl = requireString(result, 'environment_url', context);
    const environmentOrigin = requireHttpUrl(environmentUrl, `${context}.environment_url`);
    if ([...manifest.origins].some((origin) => origin !== environmentOrigin)) {
        reject(`${context}: environment origin does not match manifest surfaces`);
    }

    const browser = requireObject(result.browser, `${context}.browser`);
    rejectUnknownKeys(browser, new Set(['name', 'version']), `${context}.browser`);
    requireString(browser, 'name', `${context}.browser`);
    requireString(browser, 'version', `${context}.browser`);

    const viewport = requireObject(result.viewport, `${context}.viewport`);
    rejectUnknownKeys(viewport, new Set(['width', 'height']), `${context}.viewport`);
    for (const dimension of ['width', 'height']) {
        if (!Number.isInteger(viewport[dimension]) || viewport[dimension] < 1) {
            reject(`${context}.viewport.${dimension} must be a positive integer`);
        }
    }

    validateStatus(result.status, `${context}.status`);

    const referencedEvidence = new Set();
    const steps = requireArray(result, 'steps', context);
    if (steps.length !== manifest.orderedSteps.length) {
        reject(`${context}.steps must match the manifest in order`);
    }
    steps.forEach((stepValue, stepIndex) => {
        const stepContext = `${context}.steps[${stepIndex}]`;
        const step = requireObject(stepValue, stepContext);
        rejectUnknownKeys(step, new Set([
            'surface',
            'action',
            'target',
            'assertion',
            'observed',
            'status',
            'evidence',
        ]), stepContext);
        const surface = requireString(step, 'surface', stepContext);
        const action = requireString(step, 'action', stepContext);
        const target = requireString(step, 'target', stepContext);
        const assertion = requireString(step, 'assertion', stepContext);
        if ('observed' in step) {
            requireString(step, 'observed', stepContext);
        }
        validateStatus(step.status, `${stepContext}.status`);
        const expectedStep = manifest.orderedSteps[stepIndex];
        if (
            surface !== expectedStep.surface
            || action !== expectedStep.action
            || target !== expectedStep.target
            || assertion !== expectedStep.assertion
        ) {
            reject(`${context}.steps must match the manifest in order`);
        }

        const stepEvidence = requireUnique(
            requireStringArray(step, 'evidence', stepContext),
            `${stepContext}.evidence`,
        );
        stepEvidence.forEach((path, index) => {
            requireEvidencePath(path, `${stepContext}.evidence[${index}]`);
            referencedEvidence.add(path);
        });
        if (!stepEvidence.includes(expectedStep.evidence)) {
            reject(`${stepContext}.evidence must include the manifest evidence path`);
        }
    });

    const coveredNegativeAssertions = new Set();
    const assertions = requireArray(result, 'negative_assertions', context);
    assertions.forEach((assertionValue, assertionIndex) => {
        const assertionContext = `${context}.negative_assertions[${assertionIndex}]`;
        const assertion = requireObject(assertionValue, assertionContext);
        rejectUnknownKeys(assertion, new Set(['assertion', 'status', 'evidence']), assertionContext);
        const assertionText = requireString(assertion, 'assertion', assertionContext);
        if (coveredNegativeAssertions.has(assertionText)) {
            reject(`${context}.negative_assertions must contain unique assertions`);
        }
        coveredNegativeAssertions.add(assertionText);
        validateStatus(assertion.status, `${assertionContext}.status`);
        requireUnique(
            requireStringArray(assertion, 'evidence', assertionContext),
            `${assertionContext}.evidence`,
        ).forEach((path, index) => {
            requireEvidencePath(path, `${assertionContext}.evidence[${index}]`);
            referencedEvidence.add(path);
        });
    });
    if (assertions.length !== manifest.negativeAssertions.length) {
        reject(`${context}.negative_assertions must match every manifest assertion`);
    }
    for (const assertion of manifest.negativeAssertions) {
        if (!coveredNegativeAssertions.has(assertion)) {
            reject(`${context}.negative_assertions must match every manifest assertion`);
        }
    }

    const resultEvidenceValues = requireUnique(
        requireStringArray(result, 'evidence', context),
        `${context}.evidence`,
    );
    const resultEvidence = new Set(resultEvidenceValues);
    [...resultEvidence].forEach((path, index) => requireEvidencePath(path, `${context}.evidence[${index}]`));
    for (const path of referencedEvidence) {
        if (!resultEvidence.has(path)) {
            reject(`${context}.evidence must list every referenced evidence path`);
        }
    }
    resultEvidenceValues.forEach((path, index) => {
        requireEvidenceFile(path, file, `${context}.evidence[${index}]`);
    });

    if (result.status === 'passed') {
        const hasNonPassingChild = steps.some((step) => step.status !== 'passed')
            || assertions.some((assertion) => assertion.status !== 'passed');
        if (hasNonPassingChild) {
            reject(`${context}: passed result requires every child result to be passed`);
        }
    }

    if ('diagnostic_notes' in result) {
        const notes = requireArray(result, 'diagnostic_notes', context, { allowEmpty: true });
        notes.forEach((noteValue, noteIndex) => {
            const noteContext = `${context}.diagnostic_notes[${noteIndex}]`;
            const note = requireObject(noteValue, noteContext);
            rejectUnknownKeys(note, new Set(['note', 'redacted']), noteContext);
            requireString(note, 'note', noteContext);
            if (note.redacted !== true) {
                reject(`${noteContext}.redacted must be true`);
            }
        });
    }

    return manifestId;
}

function manifestFiles(targetInput) {
    const target = resolve(targetInput);
    let stats;
    try {
        stats = lstatSync(target);
    } catch {
        reject('manifest input does not exist');
    }

    if (stats.isSymbolicLink()) {
        reject('manifest input must not be a symbolic link');
    }
    if (stats.isFile()) {
        if (!['.json', '.yaml', '.yml'].includes(extname(target))) {
            reject('manifest input must be a YAML or JSON file');
        }
        return [target];
    }
    if (!stats.isDirectory()) {
        reject('manifest input must be a file or directory');
    }

    const manifestEntries = readdirSync(target, { withFileTypes: true })
        .filter((entry) => entry.name !== 'schema.json')
        .filter((entry) => ['.json', '.yaml', '.yml'].includes(extname(entry.name)));
    if (manifestEntries.some((entry) => !entry.isFile() || entry.isSymbolicLink())) {
        reject('manifest directory contains an unsafe manifest entry');
    }

    const files = manifestEntries
        .map((entry) => resolve(target, entry.name))
        .sort();
    if (files.length === 0) {
        reject('manifest directory contains no manifest files');
    }

    return files;
}

function parseOptions(argumentsList) {
    const manifestTarget = argumentsList[0];
    if (!manifestTarget) {
        reject('usage: validate-acceptance.mjs <manifest-file-or-directory> [--result <file> --release-sha <sha>]');
    }

    let resultFile = null;
    let requestedSha = null;
    for (let index = 1; index < argumentsList.length; index += 1) {
        const option = argumentsList[index];
        const value = argumentsList[index + 1];
        if (option === '--result' && value) {
            if (resultFile !== null) {
                reject('validator options must not be repeated');
            }
            resultFile = value;
            index += 1;
        } else if (option === '--release-sha' && value) {
            if (requestedSha !== null) {
                reject('validator options must not be repeated');
            }
            requestedSha = value;
            index += 1;
        } else {
            reject('unsupported or incomplete validator option');
        }
    }

    if (resultFile === null) {
        if (requestedSha !== null) {
            reject('release SHA validation requires a result file');
        }

        return { manifestTarget, resultFile, releaseSha: null };
    }

    if (extname(resultFile) !== '.json') {
        reject('result input must be a JSON file');
    }

    const releaseSha = requestedSha || process.env.RELEASE_SHA || null;
    if (releaseSha === null) {
        reject('result validation requires a release SHA');
    }
    if (!SHA_PATTERN.test(releaseSha)) {
        reject('release SHA must be a lower-case full commit SHA');
    }

    return { manifestTarget, resultFile, releaseSha };
}

function main() {
    try {
        const { manifestTarget, resultFile, releaseSha } = parseOptions(process.argv.slice(2));
        const manifests = new Map();
        for (const file of manifestFiles(manifestTarget)) {
            const manifest = validateManifest(readDocument(file), file);
            if (manifests.has(manifest.id)) {
                reject('manifest identifiers must be unique');
            }
            manifests.set(manifest.id, manifest);
            console.log(`${manifest.id}: valid`);
        }

        if (resultFile !== null) {
            const manifestId = validateResult(
                readDocument(resolve(resultFile)),
                resolve(resultFile),
                releaseSha,
                manifests,
            );
            console.log(`${manifestId} result: valid`);
        }
    } catch (error) {
        const message = error instanceof Error ? error.message : 'unknown validation failure';
        console.error(`acceptance validation failed: ${message}`);
        process.exitCode = 1;
    }
}

main();
