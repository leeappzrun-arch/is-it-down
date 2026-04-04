<?php

namespace App\Support\AiAssistant;

use App\Models\AiAssistantSetting;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class AiAssistantOrchestrator
{
    public function __construct(
        protected AiAssistantClient $client,
        protected AiAssistantToolExecutor $toolExecutor,
    ) {}

    /**
     * Generate one assistant reply for the current user.
     *
     * @param  array<int, array<string, string>>  $history
     */
    public function reply(User $user, array $history, string $routeName = ''): string
    {
        $settings = AiAssistantSetting::enabled();

        if ($settings === null) {
            throw new AiAssistantException('The AI assistant has not been configured yet.');
        }

        $messages = [
            [
                'role' => 'system',
                'content' => AiAssistantRules::systemPrompt($user, $routeName),
            ],
        ];

        if (filled($settings->system_prompt)) {
            $messages[] = [
                'role' => 'system',
                'content' => (string) $settings->system_prompt,
            ];
        }

        foreach (array_slice($history, -12) as $message) {
            $role = (string) Arr::get($message, 'role', '');
            $content = trim((string) Arr::get($message, 'content', ''));

            if (! in_array($role, ['user', 'assistant'], true) || $content === '') {
                continue;
            }

            $messages[] = [
                'role' => $role,
                'content' => $content,
            ];
        }

        $tools = $this->toolExecutor->toolDefinitions($user);

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $assistantMessage = $this->client->complete($settings, $messages, $tools);

            $messages[] = $assistantMessage;

            $toolCalls = $assistantMessage['tool_calls'] ?? [];

            if (! is_array($toolCalls) || $toolCalls === []) {
                $content = trim((string) ($assistantMessage['content'] ?? ''));

                if ($content !== '') {
                    return $content;
                }

                return 'I could not generate a useful reply just now. Please try again.';
            }

            foreach ($toolCalls as $toolCall) {
                $toolName = (string) Arr::get($toolCall, 'function.name', '');
                $toolArguments = json_decode((string) Arr::get($toolCall, 'function.arguments', '{}'), true);

                if (! is_array($toolArguments)) {
                    $toolArguments = [];
                }

                $toolResult = $this->toolExecutor->execute($user, $toolName, $toolArguments);

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => (string) (Arr::get($toolCall, 'id') ?: Str::uuid()),
                    'name' => $toolName,
                    'content' => json_encode($toolResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                ];
            }
        }

        throw new AiAssistantException('The assistant exceeded the tool-call limit for this reply.');
    }
}
