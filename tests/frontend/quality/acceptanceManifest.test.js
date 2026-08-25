import { afterEach, describe, expect, it } from 'vitest';
import {
    mkdirSync,
    linkSync,
    mkdtempSync,
    readFileSync,
    rmSync,
    symlinkSync,
    writeFileSync,
} from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { spawnSync } from 'node:child_process';
import yaml from 'yaml';

const validator = resolve('scripts/quality/validate-acceptance.mjs');
const releaseSha = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
const temporaryDirectories = [];

function makeTemporaryDirectory() {
    const directory = mkdtempSync(join(tmpdir(), 'fynla-acceptance-'));
    temporaryDirectories.push(directory);

    return directory;
}

function validManifest() {
    return {
        id: 'release-smoke-test',
        finding_ids: ['UT-01'],
        environment: 'csjones',
        user: {
            kind: 'dedicated_qa',
            email_ref: 'CSJONES_QA_EMAIL',
        },
        surfaces: [
            {
                name: 'desktop',
                start_url: 'https://csjones.co/fynla',
                steps: [
                    {
                        action: 'click',
                        target: 'Sign in',
                        assert: 'Login form is visible',
                        evidence: 'evidence/01-desktop-login.png',
                    },
                    {
                        action: 'fill',
                        target: 'Email address',
                        assert: 'Email field contains the supplied account',
                        evidence: 'evidence/02-desktop-email.png',
                    },
                ],
            },
            {
                name: 'mobile',
                start_url: 'https://csjones.co/fynla/m',
                steps: [
                    {
                        action: 'inspect-frame',
                        target: 'mobile application iframe',
                        assert: 'Mobile main region is visible',
                        evidence: 'evidence/03-mobile-boot.png',
                    },
                ],
            },
        ],
        negative_assertions: [
            'no console errors',
            'no unexpected 4xx or 5xx API responses',
        ],
        cleanup: ['retain only the standing QA account'],
    };
}

function validResult() {
    return {
        manifest_id: 'release-smoke-test',
        commit_sha: releaseSha,
        started_at: '2026-07-13T08:00:00.0001Z',
        finished_at: '2026-07-13T08:00:00.0009Z',
        environment_url: 'https://csjones.co/fynla',
        browser: {
            name: 'chromium',
            version: '140.0.0',
        },
        viewport: {
            width: 1280,
            height: 720,
        },
        status: 'passed',
        steps: [
            {
                surface: 'desktop',
                action: 'click',
                target: 'Sign in',
                assertion: 'Login form is visible',
                status: 'passed',
                evidence: ['evidence/01-desktop-login.png'],
            },
            {
                surface: 'desktop',
                action: 'fill',
                target: 'Email address',
                assertion: 'Email field contains the supplied account',
                status: 'passed',
                evidence: ['evidence/02-desktop-email.png'],
            },
            {
                surface: 'mobile',
                action: 'inspect-frame',
                target: 'mobile application iframe',
                assertion: 'Mobile main region is visible',
                status: 'passed',
                evidence: ['evidence/03-mobile-boot.png'],
            },
        ],
        negative_assertions: [
            {
                assertion: 'no console errors',
                status: 'passed',
                evidence: ['evidence/04-console.json'],
            },
            {
                assertion: 'no unexpected 4xx or 5xx API responses',
                status: 'passed',
                evidence: ['evidence/05-network.json'],
            },
        ],
        evidence: [
            'evidence/01-desktop-login.png',
            'evidence/02-desktop-email.png',
            'evidence/03-mobile-boot.png',
            'evidence/04-console.json',
            'evidence/05-network.json',
        ],
    };
}

function writeContract({
    manifest = validManifest(),
    result = validResult(),
    resultFilename = 'result.json',
    createEvidence = true,
} = {}) {
    const directory = makeTemporaryDirectory();
    const manifestPath = join(directory, 'manifest.yaml');
    const resultPath = join(directory, resultFilename);
    writeFileSync(manifestPath, yaml.stringify(manifest));
    writeFileSync(resultPath, JSON.stringify(result, null, 2));

    if (createEvidence) {
        for (const evidencePath of result.evidence) {
            const fullPath = join(directory, evidencePath);
            mkdirSync(dirname(fullPath), { recursive: true });
            writeFileSync(fullPath, 'test evidence');
        }
    }

    return { directory, manifestPath, resultPath };
}

function runValidator(argumentsList, environment = {}) {
    return spawnSync(process.execPath, [validator, ...argumentsList], {
        cwd: resolve('.'),
        encoding: 'utf8',
        env: { ...process.env, ...environment },
    });
}

function runContract(manifestPath, resultPath, sha = releaseSha) {
    return runValidator([
        manifestPath,
        '--result',
        resultPath,
        '--release-sha',
        sha,
    ]);
}

afterEach(() => {
    for (const directory of temporaryDirectories.splice(0)) {
        rmSync(directory, { recursive: true, force: true });
    }
});

describe('agent acceptance manifest', () => {
    it('defines desktop and mobile interactions plus evidence', () => {
        const manifest = yaml.parse(readFileSync('tests/Browser/acceptance/release-smoke.yaml', 'utf8'));
        expect(manifest.surfaces.map((surface) => surface.name)).toEqual(['desktop', 'mobile']);
        for (const surface of manifest.surfaces) {
            expect(surface.steps.length).toBeGreaterThan(0);
            expect(surface.steps.every((step) => step.action && step.assert)).toBe(true);
        }
    });

    it('validates an exact ordered result with real evidence files', () => {
        const { manifestPath, resultPath } = writeContract();

        const outcome = runContract(manifestPath, resultPath);

        expect(outcome.status).toBe(0);
        expect(outcome.stdout).toContain('release-smoke-test result: valid');
    });

    it('rejects a result for a different release commit', () => {
        const { manifestPath, resultPath } = writeContract();

        const outcome = runContract(
            manifestPath,
            resultPath,
            'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
        );

        expect(outcome.status).toBe(1);
        expect(outcome.stderr).toContain('result commit SHA does not match requested release SHA');
    });

    it('rejects a user-visible manifest without a mobile surface', () => {
        const manifest = validManifest();
        manifest.surfaces = manifest.surfaces.filter((surface) => surface.name !== 'mobile');
        const { manifestPath } = writeContract({ manifest });

        const outcome = runValidator([manifestPath]);

        expect(outcome.status).toBe(1);
        expect(outcome.stderr).toContain('user-visible manifests require desktop and mobile surfaces');
    });

    it('rejects a manifest step without an assertion', () => {
        const manifest = validManifest();
        delete manifest.surfaces[0].steps[0].assert;
        const { manifestPath } = writeContract({ manifest });

        const outcome = runValidator([manifestPath]);

        expect(outcome.status).toBe(1);
        expect(outcome.stderr).toContain('.assert is required');
    });

    it.each([
        ['missing', (result) => result.steps.pop()],
        ['extra', (result) => result.steps.push({ ...result.steps[0] })],
        ['altered', (result) => { result.steps[0].action = 'observe'; }],
    ])('rejects a %s result step', (_name, mutate) => {
        const result = validResult();
        mutate(result);
        const { manifestPath, resultPath } = writeContract({ result });

        const outcome = runContract(manifestPath, resultPath);

        expect(outcome.status).toBe(1);
        expect(outcome.stderr).toContain('steps must match the manifest in order');
    });

    it('rejects replacement evidence for a manifest step', () => {
        const result = validResult();
        result.steps[0].evidence = ['evidence/replacement.png'];
        result.evidence.push('evidence/replacement.png');
        const { manifestPath, resultPath } = writeContract({ result });

        const outcome = runContract(manifestPath, resultPath);

        expect(outcome.status).toBe(1);
        expect(outcome.stderr).toContain('must include the manifest evidence path');
    });

    it.each([
        ['failed step', (result) => { result.steps[0].status = 'failed'; }],
        ['failed negative assertion', (result) => { result.negative_assertions[0].status = 'failed'; }],
    ])('rejects an overall passed result with a %s', (_name, mutate) => {
        const result = validResult();
        mutate(result);
        const { manifestPath, resultPath } = writeContract({ result });

        const outcome = runContract(manifestPath, resultPath);

        expect(outcome.status).toBe(1);
        expect(outcome.stderr).toContain('passed result requires every child result to be passed');
    });

    it('rejects a result whose environment origin differs from the manifest', () => {
        const result = validResult();
        result.environment_url = 'https://fynla.org';
        const { manifestPath, resultPath } = writeContract({ result });

        const outcome = runContract(manifestPath, resultPath);

        expect(outcome.status).toBe(1);
        expect(outcome.stderr).toContain('environment origin does not match manifest surfaces');
    });

    it.each([
        'https://example.com/evidence.png',
        '/tmp/evidence.png',
        'C:\\temp\\evidence.png',
        '\\\\server\\share\\evidence.png',
        '../evidence.png',
        'evidence/../evidence.png',
        'evidence\0hidden.png',
    ])('rejects an unsafe evidence path: %s', (evidencePath) => {
        const manifest = validManifest();
        manifest.surfaces[0].steps[0].evidence = evidencePath;
        const { manifestPath } = writeContract({ manifest });

        const outcome = runValidator([manifestPath]);

        expect(outcome.status).toBe(1);
        expect(outcome.stderr).toContain('must be a safe relative evidence path');
    });

    it('rejects an inventoried evidence file that does not exist', () => {
        const { manifestPath, resultPath } = writeContract({ createEvidence: false });

        const outcome = runContract(manifestPath, resultPath);

        expect(outcome.status).toBe(1);
        expect(outcome.stderr).toContain('evidence file is missing or unsafe');
    });

    it('rejects an inventoried evidence symbolic link', () => {
        const { directory, manifestPath, resultPath } = writeContract();
        const linkedPath = join(directory, 'evidence/01-desktop-login.png');
        rmSync(linkedPath);
        symlinkSync(join(directory, 'evidence/02-desktop-email.png'), linkedPath);

        const outcome = runContract(manifestPath, resultPath);

        expect(outcome.status).toBe(1);
        expect(outcome.stderr).toContain('evidence file is missing or unsafe');
    });

    it('rejects a zero-byte evidence file', () => {
        const { directory, manifestPath, resultPath } = writeContract();
        writeFileSync(join(directory, 'evidence/01-desktop-login.png'), '');

        const outcome = runContract(manifestPath, resultPath);

        expect(outcome.status).toBe(1);
        expect(outcome.stderr).toContain('evidence file is missing or unsafe');
    });

    it('rejects evidence that resolves to the result JSON itself', () => {
        const { manifestPath, resultPath } = writeContract();
        const result = validResult();
        result.evidence.push('result.json');
        writeFileSync(resultPath, JSON.stringify(result, null, 2));

        const outcome = runContract(manifestPath, resultPath);

        expect(outcome.status).toBe(1);
        expect(outcome.stderr).toContain('evidence file is missing or unsafe');
    });

    it('rejects evidence hard-linked to the result JSON', () => {
        const { directory, manifestPath, resultPath } = writeContract();
        const result = validResult();
        const linkedEvidence = 'evidence/result-hard-link.json';
        result.evidence.push(linkedEvidence);
        writeFileSync(resultPath, JSON.stringify(result, null, 2));
        linkSync(resultPath, join(directory, linkedEvidence));

        const outcome = runContract(manifestPath, resultPath);

        expect(outcome.status).toBe(1);
        expect(outcome.stderr).toContain('evidence file is missing or unsafe');
    });

    it('rejects duplicate JSON member names', () => {
        const { resultPath, manifestPath } = writeContract();
        const duplicate = JSON.stringify(validResult(), null, 2).replace(
            `"commit_sha": "${releaseSha}"`,
            `"commit_sha": "${releaseSha}",\n  "commit_sha": "${releaseSha}"`,
        );
        writeFileSync(resultPath, duplicate);

        const outcome = runContract(manifestPath, resultPath);

        expect(outcome.status).toBe(1);
        expect(outcome.stderr).toContain('invalid JSON');
    });

    it.each([
        'result.yaml',
        'result.JSON',
    ])('rejects a result file without the exact JSON extension: %s', (resultFilename) => {
        const { manifestPath, resultPath } = writeContract({ resultFilename });

        const outcome = runContract(manifestPath, resultPath);

        expect(outcome.status).toBe(1);
        expect(outcome.stderr).toContain('result input must be a JSON file');
    });

    it('rejects a manifest file with an upper-case extension', () => {
        const directory = makeTemporaryDirectory();
        const manifestPath = join(directory, 'manifest.JSON');
        writeFileSync(manifestPath, JSON.stringify(validManifest(), null, 2));

        const outcome = runValidator([manifestPath]);

        expect(outcome.status).toBe(1);
        expect(outcome.stderr).toContain('manifest input must be a YAML or JSON file');
    });

    it.each([
        ['symbolic link', (directory, manifestPath) => {
            symlinkSync(manifestPath, join(directory, 'linked.yaml'));
        }],
        ['non-regular entry', (directory) => {
            mkdirSync(join(directory, 'nested.yaml'));
        }],
    ])('rejects a supported-suffix %s in manifest directory mode', (_name, addUnsafeEntry) => {
        const directory = makeTemporaryDirectory();
        const manifestPath = join(directory, 'manifest.yaml');
        writeFileSync(manifestPath, yaml.stringify(validManifest()));
        addUnsafeEntry(directory, manifestPath);

        const outcome = runValidator([directory]);

        expect(outcome.status).toBe(1);
        expect(outcome.stderr).toContain('manifest directory contains an unsafe manifest entry');
    });

    it('ignores RELEASE_SHA for manifest-only validation', () => {
        const { manifestPath } = writeContract();

        const outcome = runValidator(
            [manifestPath],
            { RELEASE_SHA: 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb' },
        );

        expect(outcome.status).toBe(0);
    });

    it.each([
        'https://user:placeholder@csjones.co/fynla',
        'https://csjones.co/fynla?access_token=placeholder',
    ])('rejects credentials in an environment URL: %s', (startUrl) => {
        const manifest = validManifest();
        manifest.surfaces[0].start_url = startUrl;
        const { manifestPath } = writeContract({ manifest });

        const outcome = runValidator([manifestPath]);

        expect(outcome.status).toBe(1);
        expect(outcome.stderr).toContain('URL must not contain credentials or secret query keys');
        expect(outcome.stderr).not.toContain('placeholder');
    });

    it('rejects uppercase commit SHAs', () => {
        const result = validResult();
        result.commit_sha = releaseSha.toUpperCase().replaceAll('A', 'B');
        const { manifestPath, resultPath } = writeContract({ result });

        const outcome = runContract(manifestPath, resultPath, result.commit_sha.toLowerCase());

        expect(outcome.status).toBe(1);
        expect(outcome.stderr).toContain('commit_sha must be a lower-case full commit SHA');
    });

    it('rejects non-RFC3339 timestamps', () => {
        const result = validResult();
        result.started_at = '2026-07-13 08:00:00';
        const { manifestPath, resultPath } = writeContract({ result });

        const outcome = runContract(manifestPath, resultPath);

        expect(outcome.status).toBe(1);
        expect(outcome.stderr).toContain('must be RFC3339 timestamps');
    });

    it.each([
        '2026-02-30T08:00:00Z',
        '2026-01-01T24:00:00Z',
    ])('rejects an impossible RFC3339 timestamp: %s', (timestamp) => {
        const result = validResult();
        result.started_at = timestamp;
        const { manifestPath, resultPath } = writeContract({ result });

        const outcome = runContract(manifestPath, resultPath);

        expect(outcome.status).toBe(1);
        expect(outcome.stderr).toContain('must be RFC3339 timestamps');
    });

    it('rejects reverse chronology below millisecond precision', () => {
        const result = validResult();
        result.started_at = '2026-07-13T08:00:00.0009Z';
        result.finished_at = '2026-07-13T08:00:00.0001Z';
        const { manifestPath, resultPath } = writeContract({ result });

        const outcome = runContract(manifestPath, resultPath);

        expect(outcome.status).toBe(1);
        expect(outcome.stderr).toContain('finished_at must not precede started_at');
    });

    it('rejects an upper-case URL scheme', () => {
        const manifest = validManifest();
        manifest.surfaces[0].start_url = 'HTTPS://csjones.co/fynla';
        const { manifestPath } = writeContract({ manifest });

        const outcome = runValidator([manifestPath]);

        expect(outcome.status).toBe(1);
        expect(outcome.stderr).toContain('must begin with lower-case http:// or https://');
    });

    it('rejects leading and trailing URL whitespace', () => {
        for (const startUrl of [
            ' https://csjones.co/fynla',
            'https://csjones.co/fynla ',
        ]) {
            const manifest = validManifest();
            manifest.surfaces[0].start_url = startUrl;
            const { manifestPath } = writeContract({ manifest });

            const outcome = runValidator([manifestPath]);

            expect(outcome.status).toBe(1);
            expect(outcome.stderr).toContain('must begin with lower-case http:// or https://');
        }
    });

    it('documents the PHP verification-code helper with single namespace separators', () => {
        const runbook = readFileSync('docs/online-readiness/agent-browser-runbook.md', 'utf8');

        expect(runbook).toContain('Tests\\Browser\\Helpers\\Login::latestVerificationCode($email)');
        expect(runbook).not.toContain('Tests\\\\Browser\\\\Helpers\\\\Login::latestVerificationCode($email)');
    });

    it.each([
        ['environment identifier', (manifest) => { manifest.environment = 'CSJONES'; }, 'lower-case identifier'],
        ['finding identifier', (manifest) => { manifest.finding_ids.push('UT-01'); }, 'must contain unique items'],
        [
            'negative assertion',
            (manifest) => { manifest.negative_assertions.push('no console errors'); },
            'must contain unique items',
        ],
    ])('rejects an invalid or duplicate manifest %s', (_name, mutate, diagnostic) => {
        const manifest = validManifest();
        mutate(manifest);
        const { manifestPath } = writeContract({ manifest });

        const outcome = runValidator([manifestPath]);

        expect(outcome.status).toBe(1);
        expect(outcome.stderr).toContain(diagnostic);
    });

    it.each([
        ['result inventory', (result) => { result.evidence.push(result.evidence[0]); }],
        ['step evidence', (result) => { result.steps[0].evidence.push(result.steps[0].evidence[0]); }],
        [
            'negative assertion evidence',
            (result) => { result.negative_assertions[0].evidence.push(result.negative_assertions[0].evidence[0]); },
        ],
    ])('rejects duplicate %s paths', (_name, mutate) => {
        const result = validResult();
        mutate(result);
        const { manifestPath, resultPath } = writeContract({ result });

        const outcome = runContract(manifestPath, resultPath);

        expect(outcome.status).toBe(1);
        expect(outcome.stderr).toContain('must contain unique items');
    });
});
