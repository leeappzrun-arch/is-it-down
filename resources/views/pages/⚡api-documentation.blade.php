<?php

use App\Support\ApiDocumentation;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('API documentation')] class extends Component {
    public string $search = '';

    /**
     * Get the endpoint catalog used by the API documentation page.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function endpoints(): array
    {
        $endpoints = ApiDocumentation::endpoints();

        $search = Str::lower(trim($this->search));

        if ($search === '') {
            return $endpoints;
        }

        return array_values(array_filter(
            $endpoints,
            function (array $endpoint) use ($search): bool {
                $haystack = Str::of(collect([
                    $endpoint['label'],
                    $endpoint['description'],
                    $endpoint['path'],
                    $endpoint['permission'],
                    $endpoint['method'],
                    collect($endpoint['query_parameters'])->pluck('name')->all(),
                    collect($endpoint['query_parameters'])->pluck('description')->all(),
                    $endpoint['body_example'] ? json_encode($endpoint['body_example'], JSON_UNESCAPED_SLASHES) : null,
                ])->flatten()->filter()->implode(' '))->lower();

                return $haystack->contains($search);
            }
        ));
    }
}; ?>

<section class="w-full">
    <div class="relative mb-6 w-full">
        <flux:heading size="xl" level="1">{{ __('API Documentation') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ __('Versioned REST endpoints authenticated with user-owned API keys.') }}</flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    <div class="mb-6 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="lg">{{ __('On this page') }}</flux:heading>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <a href="#authentication" class="rounded-lg border border-zinc-200 px-4 py-3 text-sm font-medium text-zinc-700 transition hover:border-zinc-300 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:text-zinc-100">{{ __('Authentication') }}</a>
            <a href="#permissions" class="rounded-lg border border-zinc-200 px-4 py-3 text-sm font-medium text-zinc-700 transition hover:border-zinc-300 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:text-zinc-100">{{ __('Permissions') }}</a>
            <a href="#playground" class="rounded-lg border border-zinc-200 px-4 py-3 text-sm font-medium text-zinc-700 transition hover:border-zinc-300 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:text-zinc-100">{{ __('Playground') }}</a>
            <a href="#endpoint-catalog" class="rounded-lg border border-zinc-200 px-4 py-3 text-sm font-medium text-zinc-700 transition hover:border-zinc-300 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:text-zinc-100">{{ __('Endpoint catalog') }}</a>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
            <div id="authentication" class="scroll-mt-24 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Authentication') }}</flux:heading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('Every request must send an `Authorization: Bearer {api-key}` header. The bearer token is matched against the stored API key hash, and revoked or expired keys are rejected automatically.') }}</p>
                    <p>{{ __('API keys are always linked to the user who created them, and every request is authorized again against the permissions assigned to that key.') }}</p>
                    <p>{{ __('The current base path is :url.', ['url' => url('/api/v1')]) }}</p>
                </div>
            </div>

            <div id="permissions" class="scroll-mt-24 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Permissions') }}</flux:heading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('Each API key permission follows the `resource:action` format. The current published REST API uses `recipients:*`, `services:*`, `templates:*`, and `users:*` permissions, with read routes requiring `:read` and mutating routes requiring `:write`.') }}</p>
                    <p>{{ __('Recipient groups share the `recipients` permission family, and service groups share the `services` permission family. Service templates have their own `templates` permission family because they can be managed directly over the API and used as defaults when creating services.') }}</p>
                    <p>{{ __('When new functionality or permission areas are added, the endpoints, docs, playground catalog, tests, and API key permission registry should be updated in the same change.') }}</p>
                </div>
            </div>

            <div id="playground" class="scroll-mt-24 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Playground') }}</flux:heading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('Use the API Playground page to pick any documented endpoint, review its request contract, paste an API key, and send a real request against this environment.') }}</p>
                    <p>{{ __('The playground shares the same endpoint catalog as this page so the dropdown documentation and this reference stay in sync.') }}</p>
                    <p>{{ __('Service and template examples include the current `additional_headers` array format plus the `ssl_expiry_notifications_enabled` flag so integrations can mirror the UI exactly.') }}</p>
                    <p>
                        <a href="{{ route('api-playground') }}" class="font-medium text-sky-600 hover:text-sky-500 dark:text-sky-400 dark:hover:text-sky-300">{{ __('Visit the API Playground') }}</a>
                         to get started or test out requests without needing a separate REST client.
                    </p>
                </div>
            </div>
    </div>

    <div id="endpoint-catalog" class="scroll-mt-24 mt-6 space-y-4">
        <div
            x-data="{ isStuck: false, updateStickyState() { this.isStuck = this.$el.getBoundingClientRect().top <= 16 && window.scrollY > 0; } }"
            x-init="updateStickyState()"
            x-on:scroll.window.throttle.50ms="updateStickyState()"
            x-on:resize.window.throttle.50ms="updateStickyState()"
            :class="isStuck ? 'shadow-lg shadow-zinc-900/10 dark:shadow-black/30' : 'shadow-sm'"
            class="sticky top-4 z-20 rounded-xl border border-zinc-200 bg-white/95 p-4 backdrop-blur transition-shadow duration-200 sm:p-6 dark:border-zinc-700 dark:bg-zinc-900/95"
        >
            <flux:input
                wire:model.live.debounce.300ms="search"
                :label="__('Search endpoints')"
                type="search"
                :placeholder="__('Search by title, URL, permission, parameter, or method')"
            />
        </div>

        @if ($this->endpoints === [])
            <div class="rounded-xl border border-dashed border-zinc-300 bg-white px-6 py-8 text-sm text-zinc-500 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400">
                {{ __('No endpoints match your search.') }}
            </div>
        @endif

        @foreach ($this->endpoints as $endpoint)
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <flux:heading size="lg">{{ __($endpoint['label']) }}</flux:heading>
                        <flux:subheading class="mt-2">{{ __($endpoint['description']) }}</flux:subheading>
                    </div>

                    <div class="flex flex-wrap gap-2 text-xs font-medium">
                        <span class="rounded-full bg-sky-100 px-3 py-1 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300">{{ $endpoint['permission'] }}</span>
                    </div>
                </div>

                <div class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 font-mono text-sm text-zinc-800 dark:border-zinc-700 dark:bg-zinc-950/40 dark:text-zinc-200">
                    {{ $endpoint['path'] }}
                </div>

                <details class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50/80 transition dark:border-zinc-700 dark:bg-zinc-950/30">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 text-sm font-medium text-zinc-900 marker:hidden dark:text-zinc-100">
                        <span>{{ __('More details') }}</span>
                        <span class="rounded-full bg-zinc-900 px-3 py-1 text-xs font-medium text-white dark:bg-zinc-100 dark:text-zinc-900">{{ $endpoint['method'] }}</span>
                    </summary>

                    <div class="border-t border-zinc-200 px-4 py-4 dark:border-zinc-700">
                        <div class="grid gap-4 lg:grid-cols-2">
                            <div>
                                <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Query parameters') }}</div>
                                @if ($endpoint['query_parameters'] === [])
                                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ __('None for this endpoint.') }}</p>
                                @else
                                    <div class="mt-3 space-y-3">
                                        @foreach ($endpoint['query_parameters'] as $parameter)
                                            <div class="rounded-xl border border-zinc-200 bg-white p-4 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                                                <div class="font-mono text-zinc-900 dark:text-zinc-100">{{ $parameter['name'] }}</div>
                                                <div class="mt-1 text-zinc-600 dark:text-zinc-300">{{ $parameter['description'] }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <div>
                                <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Example body') }}</div>
                                @if ($endpoint['body_example'] === null)
                                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ __('This endpoint does not require a JSON request body.') }}</p>
                                @else
                                    <pre class="mt-3 overflow-x-auto rounded-xl border border-zinc-200 bg-white p-4 text-sm text-zinc-800 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">{{ json_encode($endpoint['body_example'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                @endif
                            </div>
                        </div>
                    </div>
                </details>
            </div>
        @endforeach
    </div>
</section>
