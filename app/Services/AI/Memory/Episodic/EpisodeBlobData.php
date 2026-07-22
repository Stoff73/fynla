<?php

declare(strict_types=1);

namespace App\Services\AI\Memory\Episodic;

use Symfony\Component\Yaml\Yaml;

/** The verbatim per-turn forensic body, rendered to the .md blob. Immutable. */
final class EpisodeBlobData
{
    /**
     * @param  list<string>|null  $proceduralVersion
     * @param  array<mixed>|null  $toolCalls
     * @param  array<mixed>|null  $toolResults
     */
    public function __construct(
        public readonly string $episodeId,
        public readonly int $conversationId,
        public readonly int $clientId,
        public readonly string $timestamp,        // ISO8601 UTC
        public readonly ?string $persona,
        public readonly ?string $module,
        public readonly ?array $proceduralVersion,
        public readonly ?string $semanticSnapshotId,
        public readonly ?string $modelUsed,
        public readonly string $systemPrompt,
        public readonly string $assembledContext,
        public readonly ?string $reasoningTrace,
        public readonly ?array $toolCalls,
        public readonly ?array $toolResults,
    ) {}

    /** Render to the .md body: YAML frontmatter + verbatim sections. */
    public function toMarkdown(): string
    {
        $fm = [
            'episode_id' => $this->episodeId,
            'conversation_id' => $this->conversationId,
            'client_id' => $this->clientId,
            'timestamp' => $this->timestamp,
            'persona' => $this->persona,
            'module' => $this->module,
            'procedural_version' => $this->proceduralVersion,
            'semantic_snapshot_id' => $this->semanticSnapshotId,
            'model_used' => $this->modelUsed,
        ];
        $yaml = Yaml::dump($fm, 2, 2);

        $sections = "## system_prompt\n\n{$this->systemPrompt}\n\n"
            ."## assembled_context\n\n{$this->assembledContext}\n";
        if ($this->reasoningTrace !== null && $this->reasoningTrace !== '') {
            $sections .= "\n## reasoning_trace\n\n{$this->reasoningTrace}\n";
        }
        $sections .= "\n## tool_calls\n\n```json\n".json_encode($this->toolCalls ?? [], JSON_PRETTY_PRINT)."\n```\n";
        $sections .= "\n## tool_results\n\n```json\n".json_encode($this->toolResults ?? [], JSON_PRETTY_PRINT)."\n```\n";

        return "---\n{$yaml}---\n\n{$sections}";
    }
}
