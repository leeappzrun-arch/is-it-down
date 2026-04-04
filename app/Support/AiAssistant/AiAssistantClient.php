<?php

namespace App\Support\AiAssistant;

use App\Models\AiAssistantSetting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class AiAssistantClient
{
    /**
     * Send the chat history to the configured provider.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @return array<string, mixed>
     */
    public function complete(AiAssistantSetting $settings, array $messages, array $tools = []): array
    {
        $payload = [
            'model' => (string) $settings->model,
            'messages' => $messages,
            'temperature' => 0.2,
        ];

        if ($tools !== []) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        $response = Http::acceptJson()
            ->asJson()
            ->withToken((string) $settings->api_key)
            ->connectTimeout(min(5, $settings->request_timeout_seconds))
            ->timeout($settings->request_timeout_seconds)
            ->post((string) $settings->provider_url, $payload);

        if (! $response->successful()) {
            throw new AiAssistantException(
                'The AI provider returned an error: '.$response->status().' '.$response->body()
            );
        }

        $message = $response->json('choices.0.message');

        if (! is_array($message)) {
            throw new AiAssistantException('The AI provider response did not include a usable message.');
        }

        return [
            'role' => 'assistant',
            'content' => $this->normalizeContent($message['content'] ?? ''),
            'tool_calls' => $this->normalizeToolCalls($message['tool_calls'] ?? []),
        ];
    }

    /**
     * Normalize the assistant content payload to plain text.
     */
    private function normalizeContent(mixed $content): string
    {
        if (is_string($content)) {
            return trim($content);
        }

        if (! is_array($content)) {
            return '';
        }

        return trim(
            collect($content)
                ->map(function (mixed $segment): string {
                    if (! is_array($segment)) {
                        return '';
                    }

                    return (string) (Arr::get($segment, 'text') ?? Arr::get($segment, 'content') ?? '');
                })
                ->implode("\n")
        );
    }

    /**
     * Normalize tool call payloads from the provider response.
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizeToolCalls(mixed $toolCalls): array
    {
        if (! is_array($toolCalls)) {
            return [];
        }

        return array_values(array_filter(
            $toolCalls,
            fn (mixed $toolCall): bool => is_array($toolCall)
                && is_string(Arr::get($toolCall, 'function.name'))
                && is_string(Arr::get($toolCall, 'function.arguments'))
        ));
    }
}
