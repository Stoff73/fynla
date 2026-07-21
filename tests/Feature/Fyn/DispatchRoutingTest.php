<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('AiChatController::sendMessage dispatches only to OnboardingChatDirector or AdviceFyn', function (): void {
    $source = File::get(base_path('app/Http/Controllers/Api/AiChatController.php'));
    preg_match('/public function sendMessage.*?\n    \}/s', $source, $matches);
    $methodBody = $matches[0] ?? '';

    expect($methodBody)->toContain('OnboardingChatDirector');
    expect($methodBody)->toContain('AdviceFyn');
    expect($methodBody)->not->toContain('FynPersonaOrchestrator');
    expect($methodBody)->not->toContain('FynPersonaInvoker');
});
