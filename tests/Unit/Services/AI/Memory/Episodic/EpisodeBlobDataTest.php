<?php

declare(strict_types=1);

use App\Services\AI\Memory\Episodic\EpisodeBlobData;

it('renders frontmatter and verbatim sections, omitting an empty reasoning_trace', function (): void {
    $data = new EpisodeBlobData(
        episodeId: '42', conversationId: 1, clientId: 7, timestamp: '2026-06-01T10:00:00Z',
        persona: 'advice', module: 'retirement', proceduralVersion: ['fyn.advice.overlay@1.0.0'],
        semanticSnapshotId: str_repeat('a', 64), modelUsed: 'grok-4',
        systemPrompt: 'SYS', assembledContext: 'CTX', reasoningTrace: null,
        toolCalls: [['name' => 'list_goals']], toolResults: [['ok' => true]],
    );

    $md = $data->toMarkdown();

    expect($md)->toStartWith('---')
        ->and($md)->toContain('episode_id:')
        ->and($md)->toContain('## system_prompt')
        ->and($md)->toContain('SYS')
        ->and($md)->toContain('## assembled_context')
        ->and($md)->toContain('## tool_calls')
        ->and($md)->not->toContain('## reasoning_trace');
});

it('includes a non-empty reasoning_trace section', function (): void {
    $data = new EpisodeBlobData('1', 1, 1, '2026-06-01T10:00:00Z', null, null, null, null, null, 'S', 'C', 'PLAN', null, null);
    expect($data->toMarkdown())->toContain('## reasoning_trace')->toContain('PLAN');
});
