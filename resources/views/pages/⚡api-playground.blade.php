<?php

use App\Support\ApiDocumentation;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('API playground')] class extends Component {
    public string $selectedEndpoint = '';

    public string $requestPath = '';

    public string $queryString = '';

    public string $requestBody = '';

    public string $apiKey = '';

    public ?int $responseStatus = null;

    public string $responseBody = '';

    public ?string $responseContentType = null;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->selectedEndpoint = ApiDocumentation::defaultEndpointKey();
        $this->syncEndpointSelection();
    }

    /**
     * Sync the form inputs when the selected endpoint changes.
     */
    public function updatedSelectedEndpoint(): void
    {
        $this->syncEndpointSelection();
    }

    /**
     * Send the current API request.
     */
    public function sendRequest(): void
    {
        $this->validate([
            'selectedEndpoint' => ['required', 'string'],
            'requestPath' => ['required', 'string', 'max:2048'],
            'queryString' => ['nullable', 'string', 'max:2048'],
            'requestBody' => ['nullable', 'string'],
            'apiKey' => ['required', 'string', 'max:2048'],
        ]);

        $endpoint = $this->activeEndpoint();
        $queryParameters = [];

        parse_str(ltrim($this->queryString, '?'), $queryParameters);

        $options = [
            'query' => $queryParameters,
        ];

        if (in_array($endpoint['method'], ['POST', 'PUT', 'PATCH'], true)) {
            try {
                $options['json'] = trim($this->requestBody) === ''
                    ? []
                    : json_decode($this->requestBody, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $this->addError('requestBody', __('The request body must be valid JSON.'));

                return;
            }
        }

        $response = Http::acceptJson()
            ->withToken(trim($this->apiKey))
            ->send($endpoint['method'], url($this->requestPath), $options);

        $this->captureResponse($response);
    }

    /**
     * Get the endpoint catalog.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function endpoints(): array
    {
        return ApiDocumentation::endpoints();
    }

    /**
     * Get the currently selected endpoint.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function currentEndpoint(): array
    {
        return $this->activeEndpoint();
    }

    /**
     * Reset the form fields to the selected endpoint defaults.
     */
    private function syncEndpointSelection(): void
    {
        $endpoint = $this->activeEndpoint();

        $this->requestPath = $endpoint['path'];
        $this->queryString = '';
        $this->requestBody = $endpoint['body_example'] === null
            ? ''
            : (string) json_encode($endpoint['body_example'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $this->responseStatus = null;
        $this->responseBody = '';
        $this->responseContentType = null;
        $this->resetErrorBag();
    }

    /**
     * Get the selected endpoint definition.
     *
     * @return array<string, mixed>
     */
    private function activeEndpoint(): array
    {
        return ApiDocumentation::find($this->selectedEndpoint) ?? ApiDocumentation::endpoints()[0];
    }

    /**
     * Capture a response for display in the playground.
     */
    private function captureResponse(ClientResponse $response): void
    {
        $this->responseStatus = $response->status();
        $this->responseContentType = $response->header('Content-Type');

        $body = $response->body();
        $decoded = json_decode($body, true);

        $this->responseBody = json_last_error() === JSON_ERROR_NONE
            ? (string) json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            : $body;
    }
}; ?>

<section class="w-full">
    <div class="relative mb-6 w-full">
        <flux:heading size="xl" level="1">{{ __('API Playground') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ __('Choose a documented endpoint, review its contract, and test it against this environment with your own API key.') }}</flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,24rem)_minmax(0,1fr)]">
        <div class="space-y-6">
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Request setup') }}</flux:heading>
                <flux:subheading class="mt-2">{{ __('Start from the endpoint dropdown, then adjust the path or example payload as needed. Service and template examples include the current monitoring-method and additional-header fields, and the downtime endpoints let you inspect stored screenshots, failed response headers, and outage history directly.') }}</flux:subheading>

                <div class="mt-6 space-y-4">
                    <div>
                        <label for="selectedEndpoint" class="mb-2 block text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ __('Endpoint') }}</label>
                        <select
                            id="selectedEndpoint"
                            wire:model.live="selectedEndpoint"
                            class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 outline-none transition focus:border-zinc-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100"
                        >
                            @foreach ($this->endpoints as $endpoint)
                                <option value="{{ $endpoint['key'] }}">{{ $endpoint['method'] }} · {{ $endpoint['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <flux:input wire:model="apiKey" :label="__('API key')" type="password" autocomplete="off" viewable placeholder="iid_..." />
                    <flux:input wire:model="requestPath" :label="__('Request path')" type="text" placeholder="/api/v1/recipients" />
                    <flux:input wire:model="queryString" :label="__('Query string')" type="text" placeholder="search=ops&per_page=10" />

                    @if ($this->currentEndpoint['body_example'] !== null)
                        <div>
                            <label for="requestBody" class="mb-2 block text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ __('JSON body') }}</label>
                            <textarea
                                id="requestBody"
                                wire:model="requestBody"
                                rows="14"
                                class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-3 font-mono text-sm text-zinc-900 outline-none transition focus:border-zinc-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100"
                            ></textarea>
                            @error('requestBody')
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    @else
                        <div class="rounded-xl border border-dashed border-zinc-300 bg-zinc-50 p-4 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-950/40 dark:text-zinc-300">
                            {{ __('This endpoint does not require a JSON request body.') }}
                        </div>
                    @endif

                    <flux:button variant="primary" wire:click="sendRequest">{{ __('Send request') }}</flux:button>
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <flux:heading size="lg">{{ __($this->currentEndpoint['label']) }}</flux:heading>
                        <flux:subheading class="mt-2">{{ __($this->currentEndpoint['description']) }}</flux:subheading>
                    </div>

                    <div class="flex flex-wrap gap-2 text-xs font-medium">
                        <span class="rounded-full bg-zinc-900 px-3 py-1 text-white dark:bg-zinc-100 dark:text-zinc-900">{{ $this->currentEndpoint['method'] }}</span>
                        <span class="rounded-full bg-sky-100 px-3 py-1 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300">{{ $this->currentEndpoint['permission'] }}</span>
                    </div>
                </div>

                <div class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 font-mono text-sm text-zinc-800 dark:border-zinc-700 dark:bg-zinc-950/40 dark:text-zinc-200">
                    {{ $this->currentEndpoint['path'] }}
                </div>

                <div class="mt-4 space-y-3">
                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Supported query parameters') }}</div>
                    @if ($this->currentEndpoint['query_parameters'] === [])
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('This endpoint does not define query parameters.') }}</p>
                    @else
                        @foreach ($this->currentEndpoint['query_parameters'] as $parameter)
                            <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 text-sm dark:border-zinc-700 dark:bg-zinc-950/40">
                                <div class="font-mono text-zinc-900 dark:text-zinc-100">{{ $parameter['name'] }}</div>
                                <div class="mt-1 text-zinc-600 dark:text-zinc-300">{{ $parameter['description'] }}</div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <flux:heading size="lg">{{ __('Response') }}</flux:heading>
                    <flux:subheading class="mt-2">{{ __('Responses are shown exactly as the selected API endpoint returns them.') }}</flux:subheading>
                </div>

                @if ($responseStatus !== null)
                    <div class="flex flex-wrap gap-2 text-xs font-medium">
                        <span class="rounded-full px-3 py-1 {{ $responseStatus >= 400 ? 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' }}">
                            {{ __('Status :status', ['status' => $responseStatus]) }}
                        </span>
                        @if ($responseContentType)
                            <span class="rounded-full bg-zinc-100 px-3 py-1 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">{{ $responseContentType }}</span>
                        @endif
                    </div>
                @endif
            </div>

            @if ($responseStatus === null)
                <p class="mt-6 rounded-xl border border-dashed border-zinc-300 px-4 py-6 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                    {{ __('Send a request to see the API response here.') }}
                </p>
            @else
                <pre class="mt-6 overflow-x-auto rounded-xl border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-800 dark:border-zinc-700 dark:bg-zinc-950/40 dark:text-zinc-200">{{ $responseBody }}</pre>
            @endif
        </div>
    </div>
</section>
