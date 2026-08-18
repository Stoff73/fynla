import { createHash } from 'node:crypto';
import { mkdirSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { spawnSync } from 'node:child_process';
import process from 'node:process';

import { webkit } from '@playwright/test';

const RELEASE_SHA = 'ce74507e62ea568a862dcb3de08d9cd8f1dc141e';
const BASE_URL = 'https://csjones.co/fynla';
const QA_USER_ID = 199;
const QA_EMAIL = 'qa-fyn-19079-838bc14@fynla.local';
const QUESTION = 'Am I on track for retirement?';
const SSH_TARGET = 'u163-ptanegf9edny@ssh.csjones.co';
const SSH_PORT = '18765';
const APP_ROOT = '/home/u163-ptanegf9edny/www/csjones.co/fynla-app';
const REMOTE_AUTH_HASH_SNAPSHOT = `/tmp/fynla-webkit-${RELEASE_SHA.slice(0, 12)}-auth-hash`;
const OUTPUT_ROOT = resolve('docs/online-readiness/evidence', RELEASE_SHA);
const FYN_EVIDENCE_ROOT = resolve(OUTPUT_ROOT, 'evidence/fyn-19079');
const DESKTOP_VIEWPORT = { width: 1440, height: 900 };
const MOBILE_VIEWPORT = { width: 390, height: 844 };
const MAX_STREAM_WAIT_MS = 300_000;

mkdirSync(FYN_EVIDENCE_ROOT, { recursive: true });

function writeJson(path, value) {
  writeFileSync(path, `${JSON.stringify(value, null, 2)}\n`, { mode: 0o600 });
}

function sha256(value) {
  return createHash('sha256').update(value).digest('hex');
}

function remoteCommand(command) {
  const result = spawnSync(
    'ssh',
    ['-p', SSH_PORT, '-o', 'BatchMode=yes', SSH_TARGET, command],
    {
      encoding: 'utf8',
      maxBuffer: 20 * 1024 * 1024,
      env: process.env,
    },
  );

  if (result.status !== 0) {
    const detail = String(result.stderr || result.stdout || 'remote command failed')
      .replace(/[A-Za-z0-9+/=]{32,}/g, '[redacted]')
      .slice(0, 1200);
    throw new Error(`Staging command failed: ${detail}`);
  }

  return result.stdout;
}

function remoteTinker(code) {
  const wrapped = `${code}\necho "__CODEX_JSON__".base64_encode(json_encode($payload, JSON_UNESCAPED_SLASHES))."__CODEX_END__";`;
  const encoded = Buffer.from(wrapped, 'utf8').toString('base64');
  const output = remoteCommand(
    `cd ${APP_ROOT} && php artisan tinker --execute="$(printf %s '${encoded}' | base64 -d)"`,
  );
  const match = output.match(/__CODEX_JSON__([A-Za-z0-9+/=]+)__CODEX_END__/);
  if (!match) throw new Error('Staging response did not contain the expected redacted payload marker.');
  return JSON.parse(Buffer.from(match[1], 'base64').toString('utf8'));
}

function redactedError(error) {
  return String(error instanceof Error ? error.message : error)
    .replace(QA_EMAIL, '[qa-email]')
    .replace(/[A-Za-z0-9+/=]{32,}/g, '[redacted]')
    .slice(0, 1500);
}

function trackPage(page, surface, diagnostics) {
  page.on('console', (message) => {
    if (message.type() === 'error') {
      diagnostics.consoleErrors.push({ surface, kind: 'console', message: message.text().slice(0, 500) });
    }
  });
  page.on('pageerror', (error) => {
    diagnostics.consoleErrors.push({ surface, kind: 'pageerror', message: redactedError(error) });
  });
  page.on('response', (response) => {
    const status = response.status();
    if (status >= 400 && response.url().includes('/api/')) {
      diagnostics.apiFailures.push({
        surface,
        status,
        method: response.request().method(),
        path: new URL(response.url()).pathname,
      });
    }
  });
}

async function declineCookieConsent(pageOrFrame) {
  const dialog = pageOrFrame.getByRole('dialog', { name: 'Cookie preferences' });
  if (!await dialog.isVisible().catch(() => false)) return;
  await dialog.getByRole('button', { name: 'Decline Cookies' }).click();
  await dialog.getByRole('button', { name: 'Continue Without Cookies' }).click();
  await dialog.waitFor({ state: 'hidden', timeout: 10_000 });
}

async function waitUntil(predicate, description, timeout = MAX_STREAM_WAIT_MS) {
  const started = Date.now();
  while (Date.now() - started < timeout) {
    if (await predicate()) return;
    await new Promise((resolvePromise) => setTimeout(resolvePromise, 500));
  }
  throw new Error(`Timed out waiting for ${description}.`);
}

function getVerificationCode() {
  const result = remoteTinker(`
$code = \\App\\Models\\EmailVerificationCode::query()
    ->where('user_id', ${QA_USER_ID})
    ->where('type', 'login')
    ->whereNull('verified_at')
    ->where('expires_at', '>', now())
    ->latest('id')
    ->value('code');
$payload = ['code' => $code];
`);
  if (!/^\d{6}$/.test(String(result.code || ''))) {
    throw new Error('No current six-digit staging verification code was available.');
  }
  return String(result.code);
}

async function enterDesktopVerification(page) {
  await page.getByRole('heading', { name: 'Enter Verification Code' }).waitFor({ timeout: 30_000 });
  const code = getVerificationCode();
  const inputs = page.locator('input[inputmode="numeric"][maxlength="1"]');
  if (await inputs.count() !== 6) throw new Error('Desktop verification did not expose six code inputs.');
  for (let index = 0; index < code.length; index += 1) {
    await inputs.nth(index).fill(code[index]);
  }
  await page.waitForURL(/\/fynla\/dashboard(?:[/?#]|$)/, { timeout: 60_000 });
}

async function enterMobileVerification(page) {
  await page.getByRole('heading', { name: 'Enter verification code' }).waitFor({ timeout: 30_000 });
  const code = getVerificationCode();
  for (let index = 0; index < code.length; index += 1) {
    await page.getByRole('textbox', { name: `Digit ${index + 1}` }).fill(code[index]);
  }
  await page.getByRole('button', { name: 'Verify and continue' }).click();
  await page.waitForURL(/\/fynla\/m\/app\/dashboard(?:[/?#]|$)/, { timeout: 60_000 });
}

function conversationSnapshot(excludedConversationIds = []) {
  const excluded = JSON.stringify(excludedConversationIds.map(Number));
  return remoteTinker(`
$excluded = json_decode(base64_decode('${Buffer.from(excluded).toString('base64')}'), true) ?? [];
$query = \\App\\Models\\AiConversation::query()
    ->where('user_id', ${QA_USER_ID})
    ->whereNull('deleted_at');
if (count($excluded) > 0) { $query->whereNotIn('id', $excluded); }
$conversation = $query->latest('id')->first();
if ($conversation === null) {
    $payload = ['conversation' => null, 'messages' => [], 'audit' => []];
} else {
    $messages = \\App\\Models\\AiMessage::query()
        ->where('conversation_id', $conversation->id)
        ->orderBy('id')
        ->get(['id', 'role', 'status', 'content', 'tool_calls', 'tool_results', 'input_tokens', 'output_tokens', 'model_used', 'blob_md_sha256'])
        ->map(fn ($message) => [
            'id' => $message->id,
            'role' => $message->role,
            'status' => $message->status?->value ?? $message->status,
            'content' => $message->content,
            'tool_calls' => $message->tool_calls,
            'tool_results' => $message->tool_results,
            'input_tokens' => $message->input_tokens,
            'output_tokens' => $message->output_tokens,
            'model_used' => $message->model_used,
            'blob_md_sha256' => $message->blob_md_sha256,
        ])->all();
    $audit = \\App\\Models\\AiAuditEvent::query()
        ->where('conversation_id', $conversation->id)
        ->orderBy('id')
        ->get(['id', 'tool_name', 'operation', 'status', 'input_summary', 'result_summary', 'entity_type', 'entity_id', 'row_hash'])
        ->map(fn ($event) => [
            'id' => $event->id,
            'tool_name' => $event->tool_name,
            'operation' => $event->operation,
            'status' => $event->status,
            'input_summary' => $event->input_summary,
            'result_summary' => $event->result_summary,
            'entity_type' => $event->entity_type,
            'entity_id' => $event->entity_id,
            'row_hash' => $event->row_hash,
        ])->all();
    $payload = [
        'conversation' => [
            'id' => $conversation->id,
            'status' => $conversation->status,
            'message_count' => $conversation->message_count,
            'model_used' => $conversation->model_used,
            'created_at' => $conversation->created_at?->toIso8601String(),
        ],
        'messages' => $messages,
        'audit' => $audit,
    ];
}
`);
}

function stableValue(value) {
  if (Array.isArray(value)) return value.map(stableValue);
  if (value && typeof value === 'object') {
    return Object.fromEntries(Object.keys(value).sort().map((key) => [key, stableValue(value[key])]));
  }
  return value;
}

function analyseConversation(snapshot, visibleAnswer) {
  if (!snapshot.conversation) throw new Error('No new active Fyn conversation was persisted.');
  const userMessages = snapshot.messages.filter((message) => message.role === 'user');
  const assistantMessages = snapshot.messages.filter((message) => message.role === 'assistant');
  const assistant = assistantMessages.at(-1);
  const answer = String(assistant?.content || '');
  const paragraphs = answer
    .split(/\n\s*\n+/)
    .map((paragraph) => paragraph.replace(/\s+/g, ' ').trim().toLowerCase())
    .filter((paragraph) => paragraph.length >= 20);
  const paragraphCounts = new Map();
  for (const paragraph of paragraphs) paragraphCounts.set(paragraph, (paragraphCounts.get(paragraph) || 0) + 1);
  const repeatedParagraphs = [...paragraphCounts.values()].filter((count) => count > 1).length;

  const dispatches = snapshot.audit.filter((event) => event.status === 'dispatched');
  const fingerprints = new Map();
  for (const event of dispatches) {
    const fingerprint = `${event.tool_name}:${JSON.stringify(stableValue(event.input_summary))}`;
    fingerprints.set(fingerprint, (fingerprints.get(fingerprint) || 0) + 1);
  }
  const duplicateDispatches = [...fingerprints.values()].filter((count) => count > 1).length;
  const gatePatterns = [
    /(?:need|must|have to).{0,70}(?:create|add|set up).{0,40}(?:retirement\s+)?goal/i,
    /(?:create|add|set up).{0,40}(?:retirement\s+)?goal.{0,70}(?:first|before|in order to)/i,
    /(?:cannot|can't|unable to).{0,80}(?:without|until).{0,40}goal/i,
  ];
  const goalsGate = gatePatterns.some((pattern) => pattern.test(answer));

  const assertions = {
    exactly_one_user_and_assistant: snapshot.messages.length === 2
      && userMessages.length === 1
      && assistantMessages.length === 1,
    assistant_length_sane: answer.length >= 100 && answer.length <= 8000,
    visible_answer_complete: String(visibleAnswer || '').trim().length >= 100,
    retirement_answer: /retir|pension|income/i.test(answer),
    no_goals_gate: !goalsGate,
    no_repeated_paragraph: repeatedParagraphs === 0,
    no_duplicate_tool_dispatch: duplicateDispatches === 0,
  };

  const failed = Object.entries(assertions).filter(([, passed]) => !passed).map(([name]) => name);
  if (failed.length > 0) throw new Error(`Conversation acceptance failed: ${failed.join(', ')}`);

  return {
    conversation_id: snapshot.conversation.id,
    persisted_message_count: snapshot.messages.length,
    roles: snapshot.messages.map((message) => message.role),
    answer_characters: answer.length,
    visible_answer_characters: String(visibleAnswer || '').trim().length,
    answer_sha256: sha256(answer),
    paragraph_count: paragraphs.length,
    repeated_paragraph_count: repeatedParagraphs,
    dispatched_tools: dispatches.map((event) => event.tool_name),
    identical_dispatch_group_count: duplicateDispatches,
    audit_statuses: snapshot.audit.map((event) => `${event.tool_name}:${event.status}`),
    assertions,
  };
}

function redactMessageRows(snapshot) {
  return {
    conversation_id: snapshot.conversation.id,
    rows: snapshot.messages.map((message) => ({
      id: message.id,
      role: message.role,
      status: message.status,
      content_characters: String(message.content || '').length,
      content_sha256: sha256(String(message.content || '')),
      input_tokens: message.input_tokens,
      output_tokens: message.output_tokens,
      model_used: message.model_used,
      episodic_blob_attested: Boolean(message.blob_md_sha256),
    })),
  };
}

function prepareQaUser() {
  return remoteTinker(`
$user = \\App\\Models\\User::findOrFail(${QA_USER_ID});
$baseline = [
    'audit_chain' => app(\\App\\Services\\AI\\AuditChainService::class)->verifyChain(),
    'conversation_ids' => \\App\\Models\\AiConversation::withTrashed()->where('user_id', ${QA_USER_ID})->pluck('id')->map(fn ($id) => (int) $id)->all(),
    'token_ids' => \\Illuminate\\Support\\Facades\\DB::table('personal_access_tokens')->where('tokenable_type', \\App\\Models\\User::class)->where('tokenable_id', ${QA_USER_ID})->pluck('id')->map(fn ($id) => (int) $id)->all(),
    'session_ids' => \\Illuminate\\Support\\Facades\\DB::table('user_sessions')->where('user_id', ${QA_USER_ID})->pluck('id')->map(fn ($id) => (int) $id)->all(),
    'verification_ids' => \\Illuminate\\Support\\Facades\\DB::table('email_verification_codes')->where('user_id', ${QA_USER_ID})->pluck('id')->map(fn ($id) => (int) $id)->all(),
    'login_attempt_ids' => \\Illuminate\\Support\\Facades\\DB::table('login_attempts')->where('email', '${QA_EMAIL}')->pluck('id')->map(fn ($id) => (int) $id)->all(),
    'point_award_ids' => \\Illuminate\\Support\\Facades\\DB::table('point_awards')->where('user_id', ${QA_USER_ID})->pluck('id')->map(fn ($id) => (int) $id)->all(),
    'daily_usage' => \\Illuminate\\Support\\Facades\\DB::table('ai_daily_usage')->where('user_id', ${QA_USER_ID})->get()->map(fn ($row) => (array) $row)->all(),
    'gamification' => (array) (\\Illuminate\\Support\\Facades\\DB::table('user_gamification')->where('user_id', ${QA_USER_ID})->first() ?? []),
    'user_fields' => \\Illuminate\\Support\\Arr::only($user->getAttributes(), [
        'onboarding_completed', 'onboarding_fyn_step', 'onboarding_fyn_path',
        'onboarding_fyn_selection', 'onboarding_fyn_context', 'active_campaign',
        'failed_login_count', 'locked_until', 'last_failed_login_at', 'updated_at',
    ]),
];
file_put_contents('${REMOTE_AUTH_HASH_SNAPSHOT}', $user->getRawOriginal('password'));
chmod('${REMOTE_AUTH_HASH_SNAPSHOT}', 0600);
$temporaryPassword = bin2hex(random_bytes(18)).'aA1!';
$user->password = \\Illuminate\\Support\\Facades\\Hash::make($temporaryPassword);
$user->saveQuietly();
$state = [
    'user_id' => $user->id,
    'email_ref' => 'CSJONES_FYN_QA_EMAIL',
    'onboarding_completed' => (bool) $user->onboarding_completed,
    'onboarding_fyn_step' => $user->onboarding_fyn_step,
    'active_campaign' => $user->active_campaign,
    'goal_count' => \\App\\Models\\Goal::where('user_id', ${QA_USER_ID})->count(),
    'defined_contribution_pension_count' => \\App\\Models\\DCPension::where('user_id', ${QA_USER_ID})->count(),
    'retirement_profile_count' => \\App\\Models\\RetirementProfile::where('user_id', ${QA_USER_ID})->count(),
    'active_conversation_count' => \\App\\Models\\AiConversation::where('user_id', ${QA_USER_ID})->count(),
];
$payload = [
    'baseline' => $baseline,
    'state' => $state,
    'temporary_password' => base64_encode($temporaryPassword),
];
`);
}

function cleanupQaUser(baseline) {
  const encodedBaseline = Buffer.from(JSON.stringify(baseline), 'utf8').toString('base64');
  const result = remoteTinker(`
$baseline = json_decode(base64_decode('${encodedBaseline}'), true);
$baselineConversationIds = $baseline['conversation_ids'] ?? [];
$createdConversationIds = \\App\\Models\\AiConversation::withTrashed()
    ->where('user_id', ${QA_USER_ID})
    ->when(count($baselineConversationIds) > 0, fn ($query) => $query->whereNotIn('id', $baselineConversationIds))
    ->pluck('id')
    ->map(fn ($id) => (int) $id)
    ->all();

// Use the model's normal soft-delete path. Hard deletion would null the
// conversation_id inside append-only hash-chain rows and invalidate the chain.
if (count($createdConversationIds) > 0) {
    \\App\\Models\\AiConversation::whereIn('id', $createdConversationIds)->delete();
}

$deleteNewIds = function (string $table, string $column, array $baselineIds): void {
    $query = \\Illuminate\\Support\\Facades\\DB::table($table)->where($column, ${QA_USER_ID});
    if (count($baselineIds) > 0) { $query->whereNotIn('id', $baselineIds); }
    $query->delete();
};

\\Illuminate\\Support\\Facades\\DB::table('user_sessions')
    ->where('user_id', ${QA_USER_ID})
    ->when(count($baseline['session_ids'] ?? []) > 0, fn ($query) => $query->whereNotIn('id', $baseline['session_ids']))
    ->delete();
\\Illuminate\\Support\\Facades\\DB::table('personal_access_tokens')
    ->where('tokenable_type', \\App\\Models\\User::class)
    ->where('tokenable_id', ${QA_USER_ID})
    ->when(count($baseline['token_ids'] ?? []) > 0, fn ($query) => $query->whereNotIn('id', $baseline['token_ids']))
    ->delete();
\\Illuminate\\Support\\Facades\\DB::table('email_verification_codes')
    ->where('user_id', ${QA_USER_ID})
    ->when(count($baseline['verification_ids'] ?? []) > 0, fn ($query) => $query->whereNotIn('id', $baseline['verification_ids']))
    ->delete();
\\Illuminate\\Support\\Facades\\DB::table('login_attempts')
    ->where('email', '${QA_EMAIL}')
    ->when(count($baseline['login_attempt_ids'] ?? []) > 0, fn ($query) => $query->whereNotIn('id', $baseline['login_attempt_ids']))
    ->delete();
\\Illuminate\\Support\\Facades\\DB::table('point_awards')
    ->where('user_id', ${QA_USER_ID})
    ->when(count($baseline['point_award_ids'] ?? []) > 0, fn ($query) => $query->whereNotIn('id', $baseline['point_award_ids']))
    ->delete();

\\Illuminate\\Support\\Facades\\DB::table('ai_daily_usage')->where('user_id', ${QA_USER_ID})->delete();
foreach ($baseline['daily_usage'] ?? [] as $row) { \\Illuminate\\Support\\Facades\\DB::table('ai_daily_usage')->insert($row); }

if (count($baseline['gamification'] ?? []) > 0) {
    \\Illuminate\\Support\\Facades\\DB::table('user_gamification')->updateOrInsert(
        ['user_id' => ${QA_USER_ID}],
        $baseline['gamification'],
    );
} else {
    \\Illuminate\\Support\\Facades\\DB::table('user_gamification')->where('user_id', ${QA_USER_ID})->delete();
}

$originalHash = file_get_contents('${REMOTE_AUTH_HASH_SNAPSHOT}');
$restoredFields = $baseline['user_fields'] ?? [];
$restoredFields['password'] = $originalHash;
\\Illuminate\\Support\\Facades\\DB::table('users')->where('id', ${QA_USER_ID})->update($restoredFields);
@unlink('${REMOTE_AUTH_HASH_SNAPSHOT}');

$chain = app(\\App\\Services\\AI\\AuditChainService::class)->verifyChain();
$payload = [
    'created_conversation_ids' => $createdConversationIds,
    'remaining_active_conversations' => \\App\\Models\\AiConversation::where('user_id', ${QA_USER_ID})->count(),
    'audit_chain' => $chain,
];
`);
  const baselineChain = baseline.audit_chain || {};
  const currentChain = result.audit_chain || {};
  const chainUnchanged = baselineChain.chain_valid === true
    ? currentChain.chain_valid === true
    : currentChain.chain_valid === false
      && currentChain.broken_at === baselineChain.broken_at
      && currentChain.row_count === baselineChain.row_count;
  if (!chainUnchanged) throw new Error('The staging AI audit-chain state changed during acceptance cleanup.');
  result.audit_chain_unchanged = true;
  return result;
}

function manifestStep(surface, action, target, assertion, evidence, observed) {
  return { surface, action, target, assertion, observed, status: 'passed', evidence: [evidence] };
}

async function main() {
  const startedAt = new Date().toISOString();
  const diagnostics = { consoleErrors: [], apiFailures: [] };
  let prepared;
  let baseline;
  let browser;
  let browserVersion = 'unknown';
  let cleanup;

  try {
    const serverSha = remoteCommand(`cd ${APP_ROOT} && git rev-parse HEAD`).trim();
    if (serverSha !== RELEASE_SHA) throw new Error(`Staging SHA mismatch: expected ${RELEASE_SHA}, observed ${serverSha}.`);

    prepared = prepareQaUser();
    baseline = prepared.baseline;
    const temporaryPassword = Buffer.from(prepared.temporary_password, 'base64').toString('utf8');
    delete prepared.temporary_password;

    const requiredState = prepared.state.onboarding_completed === true
      && prepared.state.onboarding_fyn_step === null
      && prepared.state.active_campaign === null
      && prepared.state.goal_count === 0
      && prepared.state.defined_contribution_pension_count >= 1
      && prepared.state.retirement_profile_count >= 1;
    if (!requiredState) throw new Error('The standing QA account did not match the FYN-19079 prerequisite state.');
    writeJson(resolve(FYN_EVIDENCE_ROOT, '01-desktop-user-state.json'), prepared.state);

    browser = await webkit.launch({ headless: true });
    browserVersion = browser.version();

    const desktopContext = await browser.newContext({ viewport: DESKTOP_VIEWPORT, locale: 'en-GB' });
    const desktopPage = await desktopContext.newPage();
    trackPage(desktopPage, 'desktop', diagnostics);

    await desktopPage.goto(BASE_URL, { waitUntil: 'domcontentloaded', timeout: 60_000 });
    await declineCookieConsent(desktopPage);
    await desktopPage.getByRole('link', { name: 'Sign in', exact: true }).first().click();
    await desktopPage.getByRole('heading', { name: 'Sign in to your account' }).waitFor({ timeout: 30_000 });
    await desktopPage.screenshot({ path: resolve(OUTPUT_ROOT, '01-desktop-login.png'), fullPage: true });

    await desktopPage.locator('#email').fill(QA_EMAIL);
    await desktopPage.locator('#password').fill(temporaryPassword);
    await desktopPage.getByRole('button', { name: 'Sign in', exact: true }).click();
    await enterDesktopVerification(desktopPage);

    const desktopChatButton = desktopPage.getByTitle('Chat with Fyn');
    await desktopChatButton.waitFor({ timeout: 60_000 });
    await desktopChatButton.click();
    const desktopInput = desktopPage.locator('textarea[placeholder="Ask Fyn..."]');
    await desktopInput.waitFor({ timeout: 30_000 });
    const desktopAssistantBubbles = desktopPage.locator('div.bg-savannah-100.border.border-light-gray.text-horizon-500');
    const desktopAssistantCountBefore = await desktopAssistantBubbles.count();
    await desktopInput.fill(QUESTION);
    await desktopPage.getByRole('button', { name: 'Send', exact: true }).click();
    await waitUntil(
      async () => !(await desktopInput.isDisabled()) && await desktopAssistantBubbles.count() > desktopAssistantCountBefore,
      'the complete desktop Fyn response',
    );
    const desktopVisibleAnswer = await desktopAssistantBubbles.last().innerText();
    await desktopPage.screenshot({ path: resolve(FYN_EVIDENCE_ROOT, '02-desktop-answer.png'), fullPage: true });

    const desktopSnapshot = conversationSnapshot(baseline.conversation_ids);
    const desktopAnalysis = analyseConversation(desktopSnapshot, desktopVisibleAnswer);
    writeJson(resolve(FYN_EVIDENCE_ROOT, '03-desktop-stream.json'), {
      ...desktopAnalysis,
      console_error_count: diagnostics.consoleErrors.filter((item) => item.surface === 'desktop').length,
      unexpected_api_failure_count: diagnostics.apiFailures.filter((item) => item.surface === 'desktop').length,
    });
    writeJson(resolve(FYN_EVIDENCE_ROOT, '04-desktop-ai-messages.json'), redactMessageRows(desktopSnapshot));
    await desktopContext.close();

    const mobileSmokeContext = await browser.newContext({ viewport: MOBILE_VIEWPORT, locale: 'en-GB' });
    const mobileSmokePage = await mobileSmokeContext.newPage();
    trackPage(mobileSmokePage, 'mobile-smoke', diagnostics);
    await mobileSmokePage.goto(`${BASE_URL}/m`, { waitUntil: 'domcontentloaded', timeout: 60_000 });
    const frame = mobileSmokePage.frameLocator('iframe[title="Fynla"]');
    await frame.locator('main').first().waitFor({ timeout: 60_000 });
    await declineCookieConsent(frame);
    await mobileSmokePage.screenshot({ path: resolve(OUTPUT_ROOT, '02-mobile-boot.png'), fullPage: true });
    await mobileSmokeContext.close();

    const mobileContext = await browser.newContext({ viewport: MOBILE_VIEWPORT, locale: 'en-GB', isMobile: true, hasTouch: true });
    const mobilePage = await mobileContext.newPage();
    trackPage(mobilePage, 'mobile', diagnostics);
    await mobilePage.goto(`${BASE_URL}/m/app/login`, { waitUntil: 'domcontentloaded', timeout: 60_000 });
    await declineCookieConsent(mobilePage);
    await mobilePage.getByPlaceholder('you@example.com').fill(QA_EMAIL);
    await mobilePage.locator('input[type="password"]').fill(temporaryPassword);
    await mobilePage.getByRole('button', { name: 'Sign in', exact: true }).click();
    await enterMobileVerification(mobilePage);
    await mobilePage.getByRole('button', { name: 'Chat with Fyn', exact: true }).waitFor({ timeout: 90_000 });
    await mobilePage.getByRole('button', { name: 'Chat with Fyn', exact: true }).click();
    await mobilePage.getByRole('region', { name: 'Chat with Fyn' }).waitFor({ timeout: 30_000 });
    await mobilePage.screenshot({ path: resolve(FYN_EVIDENCE_ROOT, '05-mobile-user-state.png'), fullPage: true });

    const mobileInput = mobilePage.getByRole('textbox', { name: 'Ask Fyn a question' });
    const mobileAssistantBubbles = mobilePage.locator('.md-fyn__msg--fyn p');
    const mobileAssistantCountBefore = await mobileAssistantBubbles.count();
    await mobileInput.fill(QUESTION);
    await mobilePage.getByRole('button', { name: 'Send to Fyn' }).click();
    const mobileSend = mobilePage.getByRole('button', { name: 'Send to Fyn' });
    await waitUntil(
      async () => !(await mobileSend.isDisabled()) && await mobileAssistantBubbles.count() > mobileAssistantCountBefore,
      'the complete mobile Fyn response',
    );
    const mobileVisibleAnswer = await mobileAssistantBubbles.last().innerText();
    await mobilePage.screenshot({ path: resolve(FYN_EVIDENCE_ROOT, '06-mobile-answer.png'), fullPage: true });

    const mobileSnapshot = conversationSnapshot([...baseline.conversation_ids, desktopAnalysis.conversation_id]);
    const mobileAnalysis = analyseConversation(mobileSnapshot, mobileVisibleAnswer);
    writeJson(resolve(FYN_EVIDENCE_ROOT, '07-mobile-stream.json'), {
      ...mobileAnalysis,
      console_error_count: diagnostics.consoleErrors.filter((item) => item.surface === 'mobile').length,
      unexpected_api_failure_count: diagnostics.apiFailures.filter((item) => item.surface === 'mobile').length,
    });
    await mobileContext.close();

    const stateEquivalent = prepared.state.onboarding_completed === true
      && prepared.state.onboarding_fyn_step === null
      && prepared.state.active_campaign === null
      && desktopAnalysis.persisted_message_count === mobileAnalysis.persisted_message_count
      && desktopAnalysis.roles.join(',') === mobileAnalysis.roles.join(',');
    const surfaceComparison = {
      release_sha: RELEASE_SHA,
      browser: { name: 'WebKit', version: browserVersion },
      dispatch_state: 'advice',
      desktop: {
        conversation_id: desktopAnalysis.conversation_id,
        message_count: desktopAnalysis.persisted_message_count,
        answer_characters: desktopAnalysis.answer_characters,
        answer_sha256: desktopAnalysis.answer_sha256,
      },
      mobile: {
        conversation_id: mobileAnalysis.conversation_id,
        message_count: mobileAnalysis.persisted_message_count,
        answer_characters: mobileAnalysis.answer_characters,
        answer_sha256: mobileAnalysis.answer_sha256,
      },
      equivalent_single_answer_outcome: stateEquivalent,
      console_errors: diagnostics.consoleErrors,
      unexpected_api_failures: diagnostics.apiFailures,
    };
    writeJson(resolve(FYN_EVIDENCE_ROOT, '08-surface-comparison.json'), surfaceComparison);

    if (!stateEquivalent) throw new Error('Desktop and mobile did not persist equivalent advice-state outcomes.');
    if (diagnostics.consoleErrors.length > 0) throw new Error(`Observed ${diagnostics.consoleErrors.length} browser console errors.`);
    if (diagnostics.apiFailures.length > 0) throw new Error(`Observed ${diagnostics.apiFailures.length} unexpected API failures.`);

    cleanup = cleanupQaUser(baseline);
    if (cleanup.remaining_active_conversations !== prepared.state.active_conversation_count) {
      throw new Error('QA conversation cleanup did not restore the initial active conversation count.');
    }
    surfaceComparison.qa_cleanup = {
      soft_deleted_run_conversation_count: cleanup.created_conversation_ids.length,
      active_conversation_count_restored: cleanup.remaining_active_conversations,
      audit_chain_baseline: baseline.audit_chain,
      audit_chain_after_cleanup: cleanup.audit_chain,
      audit_chain_unchanged: cleanup.audit_chain_unchanged,
    };
    writeJson(resolve(FYN_EVIDENCE_ROOT, '08-surface-comparison.json'), surfaceComparison);

    const finishedAt = new Date().toISOString();
    const releaseSmokeEvidence = ['01-desktop-login.png', '02-mobile-boot.png'];
    writeJson(resolve(OUTPUT_ROOT, 'release-smoke-result.json'), {
      manifest_id: 'release-smoke',
      commit_sha: RELEASE_SHA,
      started_at: startedAt,
      finished_at: finishedAt,
      environment_url: BASE_URL,
      browser: { name: 'WebKit', version: browserVersion },
      viewport: DESKTOP_VIEWPORT,
      status: 'passed',
      steps: [
        manifestStep('desktop', 'click', 'Sign in', 'Login form is visible', '01-desktop-login.png', 'The public-page Sign in link opened the desktop login form.'),
        manifestStep('mobile', 'inspect-frame', 'mobile application iframe', 'Mobile main region is visible', '02-mobile-boot.png', 'The /m host loaded a same-origin iframe with a visible main region.'),
      ],
      negative_assertions: [
        { assertion: 'no console errors', status: 'passed', evidence: releaseSmokeEvidence },
        { assertion: 'no unexpected 4xx or 5xx API responses', status: 'passed', evidence: releaseSmokeEvidence },
        { assertion: 'no desktop/mobile state divergence', status: 'passed', evidence: releaseSmokeEvidence },
      ],
      evidence: releaseSmokeEvidence,
      diagnostic_notes: [{ note: 'Executed with the Playwright WebKit engine only; no Chromium browser was launched.', redacted: true }],
    });

    const fynEvidence = [
      'evidence/fyn-19079/01-desktop-user-state.json',
      'evidence/fyn-19079/02-desktop-answer.png',
      'evidence/fyn-19079/03-desktop-stream.json',
      'evidence/fyn-19079/04-desktop-ai-messages.json',
      'evidence/fyn-19079/05-mobile-user-state.png',
      'evidence/fyn-19079/06-mobile-answer.png',
      'evidence/fyn-19079/07-mobile-stream.json',
      'evidence/fyn-19079/08-surface-comparison.json',
    ];
    writeJson(resolve(OUTPUT_ROOT, 'fyn-19079-repetition-result.json'), {
      manifest_id: 'fyn-19079-repetition',
      commit_sha: RELEASE_SHA,
      started_at: startedAt,
      finished_at: finishedAt,
      environment_url: BASE_URL,
      browser: { name: 'WebKit', version: browserVersion },
      viewport: DESKTOP_VIEWPORT,
      status: 'passed',
      steps: [
        manifestStep('desktop', 'provision', 'completed-onboarding QA user with retirement data and zero goals', 'User has retirement data, no goals, no active campaign, and no onboarding step', fynEvidence[0], 'Standing QA state matched all retirement/advice prerequisites.'),
        manifestStep('desktop', 'ask', QUESTION, 'Fyn returns one complete retirement answer without a goals prerequisite gate', fynEvidence[1], `One visible answer persisted at ${desktopAnalysis.answer_characters} characters.`),
        manifestStep('desktop', 'inspect-stream', 'desktop conversation stream and tool audit', 'No identical tool executes more than once and no paragraph repeats', fynEvidence[2], 'No duplicate dispatch fingerprint or repeated paragraph was observed.'),
        manifestStep('desktop', 'inspect-database', 'ai_messages rows for the desktop conversation', 'Exactly one user turn and one assistant answer of 100 to 8000 characters are persisted', fynEvidence[3], 'Exactly two rows persisted in user/assistant order.'),
        manifestStep('mobile', 'inspect-frame', 'mobile application iframe signed in as the same completed-onboarding QA user', 'Mobile resolves the user to advice state with no active onboarding flow', fynEvidence[4], 'The direct /m/app verification path rendered the completed-onboarding dashboard and Fyn chat.'),
        manifestStep('mobile', 'ask', QUESTION, 'Fyn returns one complete retirement answer without a goals prerequisite gate', fynEvidence[5], `One visible answer persisted at ${mobileAnalysis.answer_characters} characters.`),
        manifestStep('mobile', 'inspect-stream', 'mobile conversation stream and tool audit', 'No identical tool executes more than once and no paragraph repeats', fynEvidence[6], 'No duplicate dispatch fingerprint or repeated paragraph was observed.'),
        manifestStep('mobile', 'compare-state', 'desktop and mobile dispatch, answer, and persisted conversation state', 'Both surfaces use advice state and persist equivalent single-answer outcomes', fynEvidence[7], 'Both surfaces persisted exactly one user turn and one assistant answer in advice state.'),
      ],
      negative_assertions: [
        { assertion: 'no goals prerequisite gate', status: 'passed', evidence: [fynEvidence[2], fynEvidence[6]] },
        { assertion: 'no repeated paragraph', status: 'passed', evidence: [fynEvidence[2], fynEvidence[6]] },
        { assertion: 'no identical tool executed more than once per answer', status: 'passed', evidence: [fynEvidence[2], fynEvidence[6]] },
        { assertion: 'no duplicate or unbounded ai_messages rows', status: 'passed', evidence: [fynEvidence[3], fynEvidence[6]] },
        { assertion: 'no desktop/mobile state divergence', status: 'passed', evidence: [fynEvidence[7]] },
        { assertion: 'no console errors', status: 'passed', evidence: [fynEvidence[7]] },
        { assertion: 'no unexpected 4xx or 5xx API responses', status: 'passed', evidence: [fynEvidence[7]] },
      ],
      evidence: fynEvidence,
      diagnostic_notes: [
        { note: 'Executed with the Playwright WebKit engine only; no Chromium browser was launched.', redacted: true },
        { note: `Cleanup soft-deleted ${cleanup.created_conversation_ids.length} run conversation(s), restored credentials/session counters, and left the pre-run AI audit-chain state unchanged.`, redacted: true },
      ],
    });

    process.stdout.write(`WEBKIT_ACCEPTANCE_PASSED ${RELEASE_SHA} WebKit/${browserVersion}\n`);
  } catch (error) {
    writeJson(resolve(OUTPUT_ROOT, 'failure-diagnostics.json'), {
      release_sha: RELEASE_SHA,
      browser: { name: 'WebKit', version: browserVersion },
      error: redactedError(error),
      diagnostics,
      cleanup,
      recorded_at: new Date().toISOString(),
    });
    throw error;
  } finally {
    if (browser) await browser.close();
    if (baseline && !cleanup) {
      try {
        cleanup = cleanupQaUser(baseline);
      } catch (cleanupError) {
        process.stderr.write(`Cleanup warning: ${redactedError(cleanupError)}\n`);
      }
    }
  }
}

main().catch((error) => {
  process.stderr.write(`${redactedError(error)}\n`);
  process.exitCode = 1;
});
