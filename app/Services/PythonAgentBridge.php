<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Bridge between Laravel and the Python Agent SDK sidecar.
 *
 * Spawns a Python subprocess that runs the Anthropic agent loop,
 * feeding it the user's financial context and receiving structured
 * analysis output.
 */
class PythonAgentBridge
{
    /**
     * Execute an agent analysis task.
     *
     * @param  string  $task  Task type: holistic_plan, scenario, deep_recommendations
     * @param  array  $context  Financial context data for the agent
     * @param  User  $user  The user being analysed
     * @param  int  $timeoutSeconds  Maximum execution time
     * @return array Structured analysis result
     *
     * @throws \RuntimeException If the agent process fails
     */
    public function execute(string $task, array $context, User $user, int $timeoutSeconds = 60): array
    {
        $input = json_encode([
            'task' => $task,
            'user_context' => $context,
            'user_id' => $user->id,
            'api_key' => config('services.anthropic.api_key'),
            'model' => config('services.anthropic.advanced_chat_model', 'claude-sonnet-4-6-20260320'),
            'max_tokens' => 8192,
        ]);

        $process = new Process([
            'python3',
            base_path('scripts/run_agent.py'),
            '--input', $input,
        ]);
        $process->setTimeout($timeoutSeconds);

        try {
            $process->run();
        } catch (\Exception $e) {
            Log::error('[PythonAgentBridge] Process failed to start', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'task' => $task,
            ]);
            throw new \RuntimeException('Agent analysis failed: '.$e->getMessage());
        }

        if (! $process->isSuccessful()) {
            Log::error('[PythonAgentBridge] Agent failed', [
                'stderr' => $process->getErrorOutput(),
                'exit_code' => $process->getExitCode(),
                'user_id' => $user->id,
                'task' => $task,
            ]);
            throw new \RuntimeException('Agent analysis failed');
        }

        $output = $process->getOutput();
        $result = json_decode($output, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('[PythonAgentBridge] Invalid JSON output', [
                'output' => substr($output, 0, 500),
                'user_id' => $user->id,
                'task' => $task,
            ]);
            throw new \RuntimeException('Agent returned invalid output');
        }

        return $result;
    }

    /**
     * Check whether the Python runtime is available.
     */
    public function isAvailable(): bool
    {
        $process = new Process(['python3', '--version']);
        $process->setTimeout(5);

        try {
            $process->run();

            return $process->isSuccessful();
        } catch (\Exception) {
            return false;
        }
    }
}
