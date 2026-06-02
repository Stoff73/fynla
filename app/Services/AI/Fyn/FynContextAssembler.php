<?php

declare(strict_types=1);

namespace App\Services\AI\Fyn;

use App\Models\User;
use App\Services\AI\AdvicePromptBuilder;
use App\Services\AI\Memory\Episodic\ProceduralVersionHolder;
use App\Services\AI\Memory\Episodic\SemanticSnapshotHolder;
use App\Services\AI\Memory\FynMemoryStore;
use App\Services\AI\Memory\Procedural\ProceduralContributionCollector;
use App\Services\AI\Memory\Procedural\ProceduralCorpusLoader;
use App\Services\AI\Memory\Procedural\Procedure;
use App\Services\AI\Memory\SemanticFact;
use App\Services\AI\Memory\SemanticRetriever;
use App\Services\AI\MemoryRetrieverService;
use App\Services\AI\Pointers\FetchContext;
use App\Services\AI\Pointers\FetchDispatcher;
use App\Services\AI\Pointers\PointerRegistry;
use App\Services\AI\Prompts\UserContentSanitiser;
use App\Services\Onboarding\OnboardingPromptBuilder;
use App\Services\TaxConfigService;
use Illuminate\Support\Carbon;

/**
 * Builds the dynamic <context>…</context> + <user_message>…</user_message>
 * block prepended in-memory to the current user turn. Bucket membership
 * comes from FynContextSelector; block content reuses the existing
 * AdvicePromptBuilder public builders verbatim (no behavioural drift).
 *
 * The caller MUST forward the same $orchestrateAnalysis closure the legacy
 * path passes (HasAiChat::buildSystemPrompt) so the POSITION bucket gets
 * real financial context — passing null makes buildFinancialContext
 * short-circuit to its "analysis service not provided" sentinel, which
 * silently strips the user's financial position from every advice turn.
 */
final class FynContextAssembler
{
    public function __construct(
        private readonly FynContextSelector $selector,
        private readonly AdvicePromptBuilder $advice,
        private readonly MemoryRetrieverService $memory,
        private readonly TaxConfigService $taxConfig,
        private readonly FynMemoryStore $memoryStore,
        private readonly SemanticRetriever $semantic,
        private readonly PointerRegistry $pointers,
        private readonly FetchDispatcher $dispatcher,
        private readonly ProceduralCorpusLoader $proceduralLoader,
        private readonly ProceduralContributionCollector $proceduralContributions,
        private readonly ProceduralVersionHolder $proceduralVersions,
    ) {}

    public function build(FynTurnContext $ctx, ?callable $orchestrateAnalysis = null): string
    {
        $buckets = $this->selector->buckets($ctx);
        $has = fn (ContextBucket $b): bool => in_array($b, $buckets, true);

        $firstName = $this->resolveFirstName($ctx->user);
        $taxYear = $this->taxConfig->getTaxYear();

        $lines = [];
        $lines[] = '<context>';
        $lines[] = "Current tax year: {$taxYear}";
        $lines[] = 'You are speaking with: '.UserContentSanitiser::wrap($firstName);
        $lines[] = $ctx->isOnboarding()
            ? 'Situation: onboarding — focus: '.$this->focusLabel($ctx->onboardingFocus)
            : 'Situation: advice';

        // IDENTITY (always present in every bucket set)
        $lines[] = '<user_profile>'."\n".$this->advice->buildUserProfile($ctx->user)."\n".'</user_profile>';
        $lines[] = '<current_context>'."\n".$this->advice->moduleContextFor($ctx->currentRoute)."\n".'</current_context>';

        // Known facts — mode-independent, included whenever non-empty
        $known = $this->memory->renderKnownFactsBlock($ctx->user, $ctx->conversation);
        if ($known !== '') {
            $lines[] = $known;
        }

        // CoALA durable memory (FR-M2): the procedural corpus shapes HOW Fyn
        // answers; recalled episodes are WHAT Fyn remembers about this user. Both
        // are empty until authored / accrued, so this is a no-op today.
        $procedural = $this->memoryStore->proceduralContext();
        if ($procedural !== '') {
            $lines[] = "<procedures>\n{$procedural}\n</procedures>";
        }
        $remembered = $this->memoryStore->recallContext($ctx->user->id);
        if ($remembered !== '') {
            $lines[] = "<remembered>\n{$remembered}\n</remembered>";
        }

        // CoALA Phase 1 — semantic knowledge corpus (additive; the static prompt's
        // compliance backbone is untouched). A malformed corpus must NOT take down
        // the whole turn — fyn:semantic:reindex is the fail-closed gate at deploy;
        // at runtime we degrade to no knowledge block (the backbone still covers
        // the user). Sparse, effective-dated to today.
        try {
            $knowledgeFacts = $this->semantic->retrieve($ctx->message, Carbon::now());
        } catch (\Throwable $e) {
            report($e);
            $knowledgeFacts = [];
        }
        // Stamp the request-scoped snapshot id (read at persist time, bound into the
        // v2 episode attestation). Explicit null when no facts — overwrites any stale
        // value if a scoped instance is reused across turns (eval harness).
        app(SemanticSnapshotHolder::class)->set(
            $knowledgeFacts !== [] ? $this->semantic->snapshotId($knowledgeFacts) : null
        );
        if ($knowledgeFacts !== []) {
            $blocks = array_map(
                static function (SemanticFact $f): string {
                    $heading = $f->source !== '' ? "### {$f->title} (source: {$f->source})" : "### {$f->title}";

                    return "{$heading}\n{$f->body}";
                },
                $knowledgeFacts,
            );
            $lines[] = "<knowledge>\n".implode("\n\n", $blocks)."\n</knowledge>";
        }

        // CoALA pointer registry — live data fetched at the moment of need (v0.5).
        // Additive sibling to <knowledge>; a fetch error degrades to no entry, never
        // breaks the turn. Lazy: only pointers whose triggers match the query fire.
        try {
            $liveBlocks = [];
            foreach ($this->pointers->matchPrefetch($ctx->message) as $pointer) {
                $res = $this->dispatcher->run($pointer, new FetchContext($ctx->user, $ctx->message));
                if ($res !== null) {
                    $liveBlocks[] = "### {$pointer->topic} (source: {$res->sourceLabel}, as of {$res->sourceVersion})\n{$res->value}";
                }
            }
        } catch (\Throwable $e) {
            report($e);
            $liveBlocks = [];
        }
        if ($liveBlocks !== []) {
            $lines[] = "<live_data>\n".implode("\n\n", $liveBlocks)."\n</live_data>";
        }

        // CoALA Phase 4c — procedural prompt overlays + FCA blocks. Additive
        // per-turn layers AFTER the static prefix and after <live_data>,
        // mirroring <knowledge>/<live_data>: load the active procedures of the
        // two prose-bearing kinds, keep those whose module matches this turn
        // (or the 'general' wildcard), and emit one block per kind. The corpus
        // is empty today, so this is a no-op (proven by the golden master). A
        // malformed corpus degrades to no block — never breaks the turn.
        $turnModule = $ctx->isOnboarding()
            ? (string) $ctx->onboardingFocus
            : (string) ($ctx->classification['primary'] ?? '');

        try {
            $corpus = $this->proceduralLoader->load();
            $now = Carbon::now();

            $overlayBodies = $this->selectProcedures($corpus->ofKind('system_prompt_overlay'), $turnModule, $now);
            $fcaBodies = $this->selectProcedures($corpus->ofKind('fca_block'), $turnModule, $now);
        } catch (\Throwable $e) {
            report($e);
            $overlayBodies = [];
            $fcaBodies = [];
        }

        if ($overlayBodies !== []) {
            $lines[] = "<overlay>\n".implode("\n\n", $overlayBodies)."\n</overlay>";
        }
        if ($fcaBodies !== []) {
            $lines[] = "<fca_block>\n".implode("\n\n", $fcaBodies)."\n</fca_block>";
        }

        if ($has(ContextBucket::POSITION)) {
            $fin = $this->advice->buildFinancialContext($ctx->user, $orchestrateAnalysis, $ctx->classification);
            $lines[] = "<financial_context>\n{$fin}\n</financial_context>";
            $rec = $this->advice->buildExistingRecordsSummary($ctx->user, $ctx->classification);
            $lines[] = "<existing_records>\n{$rec}\n</existing_records>";
        }

        if ($has(ContextBucket::READINESS)) {
            // C1: lean per-turn block (per-user READY/BLOCKED matrix only).
            // The static NAVIGATION / BLOCKED-MODULE / MODULE-DEPENDENCY rules
            // now live once in the cached FynSystemPrompt
            // (<data_completeness_rules>) instead of ~595 tok every advice turn.
            $lines[] = $this->advice->buildPrerequisiteStateContextLean($ctx->user);
        }

        // KYC gate result (parity with legacy AdvicePromptBuilder Layer 9,
        // AdvicePromptBuilder.php:195-198). Emitted whenever the gate produced
        // prompt_text — not gated by a bucket, exactly as the legacy builder
        // appends it unconditionally — so the unified prompt asks for missing
        // data instead of advising, identically to FYN_PROMPT_ARCH=legacy.
        if ($ctx->kycResult !== null
            && isset($ctx->kycResult['prompt_text'])
            && $ctx->kycResult['prompt_text'] !== '') {
            $lines[] = $ctx->kycResult['prompt_text'];
        }

        // Billing guidance (parity with legacy AdvicePromptBuilder Layer 3c,
        // AdvicePromptBuilder.php:123-125). Classification-gated on BILLING
        // and suppressed in preview, exactly as the legacy builder injects
        // it — NOT a ContextBucket, same as the KYC layer above. Reuses the
        // legacy builder verbatim (zero drift, per this class's contract).
        // Restores the subscription/invoice journey under unified: PR #335
        // deleted <billing_guidance> from the static FynSystemPrompt without
        // a per-turn replacement, silently removing Fyn's billing surface
        // when unified became the default. See memory
        // feedback_fyn_reaches_every_surface + reference_unified_prompt_has_no_billing_layer.
        if (! $ctx->isPreview && $this->advice->isBillingQuery($ctx->classification)) {
            $lines[] = $this->advice->getBillingGuidance();
        }

        if ($has(ContextBucket::CAPTURE)) {
            $focus = (string) $ctx->onboardingFocus;
            $lines[] = FynCaptureTurnInstructions::render(
                $this->focusLabel($focus),
                implode(', ', OnboardingPromptBuilder::toolsForFocus($focus)),
            );
        }

        if ($ctx->isPreview) {
            $lines[] = '<preview_mode>'."\n"
                .'The user is previewing Fynla without a real account. You cannot create, '
                .'update, or delete any records. If they ask you to save anything, tell them '
                .'warmly it will be captured when they sign up, then continue helping with '
                .'analysis and questions.'."\n".'</preview_mode>';
        }

        $lines[] = '</context>';
        $lines[] = '<user_message>';
        $lines[] = UserContentSanitiser::clean($ctx->message);
        $lines[] = '</user_message>';

        return implode("\n", $lines);
    }

    private function resolveFirstName(User $user): string
    {
        $first = trim((string) ($user->first_name ?? ''));
        if ($first === '') {
            $parts = explode(' ', (string) $user->name);
            $first = $parts[0] !== '' ? $parts[0] : 'there';
        }

        return $first;
    }

    private function focusLabel(?string $focus): string
    {
        return match ($focus) {
            'savings', 'budgeting' => 'Cash & Savings',
            'investment' => 'Investments',
            'retirement' => 'Retirement',
            'protection' => 'Protection',
            'estate' => 'Estate Planning',
            'business' => 'Business',
            'goals' => 'Goals',
            'savetax' => 'SaveTax',
            default => (string) $focus,
        };
    }

    /**
     * From the procedures of one kind, resolve the active version of each
     * distinct procedure_id and keep those whose module matches this turn (or
     * the 'general' wildcard). Records each kept procedure into the
     * request-scoped contribution collector and returns the bodies in
     * corpus-walk order.
     *
     * @param  list<Procedure>  $procedures
     * @return list<string>
     */
    private function selectProcedures(array $procedures, string $turnModule, Carbon $now): array
    {
        $bodies = [];
        $seen = [];
        foreach ($procedures as $proc) {
            if (isset($seen[$proc->procedureId])) {
                continue;
            }

            if ($proc->module !== 'general' && $proc->module !== $turnModule) {
                continue;
            }

            // Resolve the single active, in-force version for this id.
            $active = null;
            foreach ($procedures as $candidate) {
                if ($candidate->procedureId === $proc->procedureId
                    && $candidate->active
                    && $candidate->effectiveOn($now)
                    && ($active === null || $candidate->version > $active->version)) {
                    $active = $candidate;
                }
            }
            if ($active === null) {
                continue;
            }

            $seen[$proc->procedureId] = true;
            $bodies[] = $active->body;
            $this->proceduralContributions->record([
                'procedure_id' => $active->procedureId,
                'kind' => $active->kind,
                'module' => $active->module,
                'version' => $active->version,
            ]);
            $this->proceduralVersions->add($active->procedureId, $active->version);
        }

        return $bodies;
    }
}
