<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\AI\Memory\Procedural\ProceduralCorpusLoader;
use App\Services\AI\Memory\Procedural\Procedure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CoALA Phase 4f — read-only admin viewer over the procedural-memory corpus.
 *
 * Holds the ProceduralCorpusLoader singleton and calls load() per request: the
 * never-throws runtime entry that degrades to the last-good/empty corpus and
 * preserves the 60s mtime hot-reload + cross-request cache. This controller
 * never calls loadStrict() (deploy gate only) and never writes — both endpoints
 * are GET. It imports neither AiToolDefinitions, FynSystemPrompt nor
 * FynContextAssembler and registers no AI/chat tool, so the assembled tool
 * catalogue, the prompt prefix, and the Two-Fyn write-states are untouched.
 */
final class ProceduralCorpusController extends Controller
{
    public function __construct(
        private readonly ProceduralCorpusLoader $loader
    ) {}

    /**
     * GET /api/admin/procedural-corpus
     * All procedures grouped by kind -> module, frontmatter-only summary (no body).
     */
    public function index(): JsonResponse
    {
        $corpus = $this->loader->load();

        // Bucket every procedure version by kind, then module, then procedure_id.
        $byKind = [];
        foreach ($corpus->all() as $proc) {
            $byKind[$proc->kind][$proc->module][$proc->procedureId][] = $proc;
        }

        $groups = [];
        foreach ($byKind as $kind => $modules) {
            ksort($modules);
            $moduleList = [];
            foreach ($modules as $module => $procedures) {
                ksort($procedures);
                $procList = [];
                foreach ($procedures as $procedureId => $versions) {
                    // Ascending by version for stable display.
                    usort($versions, fn (Procedure $a, Procedure $b): int => $a->version <=> $b->version);

                    $active = null;
                    foreach ($versions as $v) {
                        if ($v->active) {
                            $active = $v->version;
                        }
                    }

                    $procList[] = [
                        'procedure_id' => $procedureId,
                        'active_version' => $active,
                        'version_count' => count($versions),
                        'versions' => array_map(fn (Procedure $v): array => [
                            'version' => $v->version,
                            'active' => $v->active,
                            'effective_from' => $v->effectiveFrom->toDateString(),
                            'effective_to' => $v->effectiveTo?->toDateString(),
                        ], $versions),
                    ];
                }
                $moduleList[] = [
                    'module' => $module,
                    'procedures' => $procList,
                ];
            }
            $groups[] = [
                'kind' => $kind,
                'modules' => $moduleList,
            ];
        }

        // Stable kind ordering for deterministic display.
        usort($groups, fn (array $a, array $b): int => strcmp($a['kind'], $b['kind']));

        return response()->json([
            'success' => true,
            'data' => ['groups' => $groups],
        ]);
    }

    /**
     * GET /api/admin/procedural-corpus/{procedureId}
     * Every version (frontmatter + body) of one procedure, ascending by version.
     * Unknown id -> empty versions array (200), never a 404 — a stale UI link
     * cannot break the page.
     */
    public function show(Request $request): JsonResponse
    {
        $procedureId = (string) $request->route('procedureId');
        $corpus = $this->loader->load();

        $versions = array_map(fn (Procedure $v): array => [
            'kind' => $v->kind,
            'module' => $v->module,
            'version' => $v->version,
            'active' => $v->active,
            'effective_from' => $v->effectiveFrom->toDateString(),
            'effective_to' => $v->effectiveTo?->toDateString(),
            'body' => $v->body,
        ], $corpus->versions($procedureId));

        return response()->json([
            'success' => true,
            'data' => [
                'procedure_id' => $procedureId,
                'versions' => $versions,
            ],
        ]);
    }
}
