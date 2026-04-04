<?php

use App\Models\AiAssistantSetting;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('AI assistant settings')] class extends Component {
    public bool $isEnabled = false;

    public string $providerUrl = '';

    public string $apiKey = '';

    public string $model = 'gpt-4o-mini';

    public int $requestTimeoutSeconds = 30;

    public string $systemPrompt = '';

    public bool $hasStoredApiKey = false;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $settings = AiAssistantSetting::current();

        $this->isEnabled = (bool) $settings->is_enabled;
        $this->providerUrl = (string) ($settings->provider_url ?? '');
        $this->model = (string) $settings->model;
        $this->requestTimeoutSeconds = (int) $settings->request_timeout_seconds;
        $this->systemPrompt = (string) ($settings->system_prompt ?? '');
        $this->hasStoredApiKey = filled($settings->api_key);
    }

    /**
     * Persist the AI assistant settings.
     */
    public function saveSettings(): void
    {
        $settings = AiAssistantSetting::current();

        $validated = $this->validate([
            'isEnabled' => ['boolean'],
            'providerUrl' => [
                Rule::requiredIf(fn (): bool => $this->isEnabled),
                'nullable',
                'url',
                'max:2048',
            ],
            'apiKey' => [
                Rule::requiredIf(fn (): bool => $this->isEnabled && ! $this->hasStoredApiKey),
                'nullable',
                'string',
                'max:4096',
            ],
            'model' => [
                Rule::requiredIf(fn (): bool => $this->isEnabled),
                'nullable',
                'string',
                'max:255',
            ],
            'requestTimeoutSeconds' => ['required', 'integer', 'min:5', 'max:120'],
            'systemPrompt' => ['nullable', 'string', 'max:65535'],
        ]);

        $payload = [
            'is_enabled' => (bool) $validated['isEnabled'],
            'provider_url' => filled($validated['providerUrl'] ?? null) ? trim((string) $validated['providerUrl']) : null,
            'model' => filled($validated['model'] ?? null) ? trim((string) $validated['model']) : 'gpt-4o-mini',
            'request_timeout_seconds' => (int) $validated['requestTimeoutSeconds'],
            'system_prompt' => filled($validated['systemPrompt'] ?? null) ? trim((string) $validated['systemPrompt']) : null,
        ];

        if (filled($validated['apiKey'] ?? null)) {
            $payload['api_key'] = trim((string) $validated['apiKey']);
        }

        $settings->update($payload);

        $this->apiKey = '';
        $this->hasStoredApiKey = filled($settings->fresh()->api_key);

        $this->dispatch('ai-settings-saved');
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('AI assistant settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('AI Assistant')" :subheading="__('Configure the floating in-app assistant and the provider it uses')">
        <div class="space-y-6">
            <div class="rounded-xl border border-dashed border-zinc-300 bg-zinc-50 p-4 text-sm leading-6 text-zinc-600 dark:border-zinc-700 dark:bg-zinc-950/40 dark:text-zinc-300">
                {{ __('The assistant is only rendered when it is enabled and a provider URL, model, and API key have been saved. Use a chat-completions endpoint URL. For OpenAI-style providers, that usually looks like https://api.openai.com/v1/chat/completions.') }}
            </div>

            <form wire:submit="saveSettings" class="space-y-6">
                <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <flux:heading>{{ __('Enable assistant') }}</flux:heading>
                            <flux:subheading class="mt-1">{{ __('Turn the bottom-right chat launcher on or off for authenticated users.') }}</flux:subheading>
                        </div>

                        <flux:switch wire:model="isEnabled" />
                    </div>
                </div>

                <flux:input
                    wire:model="providerUrl"
                    :label="__('Provider URL')"
                    type="url"
                    placeholder="https://api.openai.com/v1/chat/completions"
                />

                <div>
                    <flux:input
                        wire:model="apiKey"
                        :label="__('API Key')"
                        type="password"
                        autocomplete="off"
                        viewable
                    />

                    <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                        {{ $hasStoredApiKey ? __('A key is already stored. Leave this blank to keep it, or enter a new key to replace it.') : __('Enter the provider API key used for chat requests.') }}
                    </p>
                </div>

                <flux:input
                    wire:model="model"
                    :label="__('Model')"
                    type="text"
                    placeholder="gpt-4o-mini"
                />

                <flux:input
                    wire:model="requestTimeoutSeconds"
                    :label="__('Request timeout (seconds)')"
                    type="number"
                    min="5"
                    max="120"
                />

                <flux:textarea
                    wire:model="systemPrompt"
                    rows="8"
                    :label="__('Additional system prompt')"
                    :placeholder="__('Optional extra instructions to append after the built-in application rules.')"
                />

                <div class="flex items-center gap-4">
                    <flux:button variant="primary" type="submit">{{ __('Save AI settings') }}</flux:button>

                    <x-action-message on="ai-settings-saved">{{ __('Saved.') }}</x-action-message>
                </div>
            </form>
        </div>
    </x-pages::settings.layout>
</section>
