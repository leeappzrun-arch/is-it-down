<?php

use App\Models\ApiKey;
use App\Support\ApiKeyPermissions;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('API key management')] class extends Component {
    public string $search = '';

    public string $name = '';

    public string $ownerType = ApiKey::OWNER_USER;

    public string $serviceName = '';

    public string $expirationOption = '1_year';

    /** @var array<int, string> */
    public array $selectedPermissions = [];

    public ?string $newlyCreatedToken = null;

    public bool $showNewApiKeyModal = false;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $this->selectedPermissions = ApiKeyPermissions::all();
    }

    /**
     * Update the form when the owner type changes.
     */
    public function updatedOwnerType(string $ownerType): void
    {
        if ($ownerType !== ApiKey::OWNER_SERVICE) {
            $this->serviceName = '';
        }

        $this->resetValidation();
    }

    /**
     * Clear the plain-text token once the confirmation modal closes.
     */
    public function updatedShowNewApiKeyModal(bool $showModal): void
    {
        if (! $showModal) {
            $this->newlyCreatedToken = null;
        }
    }

    /**
     * Get the available owner types.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function ownerOptions(): array
    {
        return [
            ApiKey::OWNER_USER => 'My account',
            ApiKey::OWNER_SERVICE => 'Service API key',
        ];
    }

    /**
     * Get the expiration presets available to admins.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function expirationOptions(): array
    {
        return [
            '6_months' => '6 Months',
            '1_year' => '1 Year',
            '2_years' => '2 Years',
            'never' => 'Never',
        ];
    }

    /**
     * Get the grouped permission matrix.
     *
     * @return array<string, array<string, string>>
     */
    #[Computed]
    public function permissionGroups(): array
    {
        return ApiKeyPermissions::grouped();
    }

    /**
     * Get the existing API keys.
     */
    #[Computed]
    public function apiKeys()
    {
        $apiKeys = ApiKey::query()
            ->with(['creator:id,name,email', 'user:id,name,email'])
            ->orderByRaw('CASE WHEN revoked_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('name')
            ->get();

        if ($this->searchTerm() === '') {
            return $apiKeys;
        }

        return $apiKeys
            ->filter(function (ApiKey $apiKey): bool {
                return $this->matchesSearch([
                    $apiKey->name,
                    $apiKey->token_prefix,
                    $apiKey->creator?->name,
                    $apiKey->creator?->email,
                    $apiKey->user?->name,
                    $apiKey->user?->email,
                    $apiKey->service_name,
                    implode(' ', $apiKey->permissions ?? []),
                    $apiKey->expirationLabel(),
                    $apiKey->isRevoked() ? 'revoked' : ($apiKey->isExpired() ? 'expired' : 'active'),
                ]);
            })
            ->values();
    }

    /**
     * Create a new API key.
     */
    public function createApiKey(): void
    {
        $validated = $this->validate($this->rules());
        $plainTextToken = ApiKey::generatePlainTextToken();

        ApiKey::query()->create([
            'name' => trim($validated['name']),
            'owner_type' => $validated['ownerType'],
            'user_id' => $validated['ownerType'] === ApiKey::OWNER_USER ? auth()->id() : null,
            'service_name' => $validated['ownerType'] === ApiKey::OWNER_SERVICE ? trim((string) $validated['serviceName']) : null,
            'created_by_id' => auth()->id(),
            'token_prefix' => substr($plainTextToken, 0, 12),
            'token_hash' => ApiKey::hashToken($plainTextToken),
            'permissions' => ApiKeyPermissions::normalize($validated['selectedPermissions']),
            'expires_at' => $this->resolveExpirationDate($validated['expirationOption']),
            'last_used_at' => null,
            'revoked_at' => null,
        ]);

        $this->newlyCreatedToken = $plainTextToken;
        $this->showNewApiKeyModal = true;
        $this->reset(['name', 'serviceName']);
        $this->ownerType = ApiKey::OWNER_USER;
        $this->expirationOption = '1_year';
        $this->selectedPermissions = ApiKeyPermissions::all();
        $this->resetValidation();

        $this->dispatch('api-key-created');
    }

    /**
     * Revoke an API key.
     */
    public function revokeApiKey(int $apiKeyId): void
    {
        $apiKey = ApiKey::query()->findOrFail($apiKeyId);

        if ($apiKey->isRevoked()) {
            return;
        }

        $apiKey->forceFill([
            'revoked_at' => now(),
        ])->save();

        $this->dispatch('api-key-revoked');
    }

    /**
     * Get the validation rules for API key creation.
     *
     * @return array<string, array<int, mixed>>
     */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'ownerType' => ['required', Rule::in(ApiKey::ownerTypes())],
            'serviceName' => [
                Rule::requiredIf(fn (): bool => $this->ownerType === ApiKey::OWNER_SERVICE),
                'nullable',
                'string',
                'max:255',
            ],
            'expirationOption' => ['required', Rule::in(array_keys($this->expirationOptions()))],
            'selectedPermissions' => ['required', 'array', 'min:1'],
            'selectedPermissions.*' => ['string', Rule::in(ApiKeyPermissions::all())],
        ];
    }

    /**
     * Resolve an expiration preset to a concrete timestamp.
     */
    private function resolveExpirationDate(string $expirationOption): ?\Carbon\CarbonInterface
    {
        return match ($expirationOption) {
            '6_months' => now()->addMonthsNoOverflow(6),
            '1_year' => now()->addYear(),
            '2_years' => now()->addYears(2),
            default => null,
        };
    }

    /**
     * Get the normalized search term.
     */
    private function searchTerm(): string
    {
        return Str::lower(trim($this->search));
    }

    /**
     * Determine whether the provided values match the current search term.
     *
     * @param  array<int, mixed>  $segments
     */
    private function matchesSearch(array $segments): bool
    {
        $search = $this->searchTerm();

        if ($search === '') {
            return true;
        }

        $haystack = Str::of(collect($segments)->flatten()->filter()->implode(' '))->lower();

        return $haystack->contains($search);
    }
}; ?>

<section class="w-full">
    <div class="relative mb-6 w-full">
        <flux:heading size="xl" level="1">{{ __('API Keys') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ __('Issue account or service keys now, then reuse them once the API layer is introduced.') }}</flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    <div class="mb-6 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm sm:p-6 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:input
            wire:model.live.debounce.300ms="search"
            :label="__('Search API keys')"
            type="search"
            :placeholder="__('Search by key name, owner, permission, or status')"
        />
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,24rem)_minmax(0,1fr)]">
        <div class="space-y-6">
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <flux:heading size="lg">{{ __('Create API key') }}</flux:heading>
                        <flux:subheading class="mt-2">{{ __('Choose whether the key belongs to your admin account or a named service integration, then define what it can read or write.') }}</flux:subheading>
                    </div>

                    <x-action-message on="api-key-created">{{ __('API key created.') }}</x-action-message>
                </div>

                <form wire:submit="createApiKey" class="mt-6 space-y-5">
                    <flux:input wire:model="name" :label="__('Key name')" type="text" required placeholder="Production sync" />

                    <div>
                        <label for="ownerType" class="mb-2 block text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ __('Owner') }}</label>
                        <select
                            id="ownerType"
                            wire:model.live="ownerType"
                            class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 outline-none transition focus:border-zinc-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100"
                        >
                            @foreach ($this->ownerOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('ownerType')
                            <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    @if ($ownerType === \App\Models\ApiKey::OWNER_SERVICE)
                        <flux:input wire:model="serviceName" :label="__('Service name')" type="text" required placeholder="Status page worker" />
                    @else
                        <div class="rounded-xl border border-dashed border-zinc-300 bg-zinc-50 p-4 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-950/40 dark:text-zinc-300">
                            {{ __('This key will be assigned to :email.', ['email' => auth()->user()->email]) }}
                        </div>
                    @endif

                    <div>
                        <label for="expirationOption" class="mb-2 block text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ __('Expires') }}</label>
                        <select
                            id="expirationOption"
                            wire:model="expirationOption"
                            class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 outline-none transition focus:border-zinc-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100"
                        >
                            @foreach ($this->expirationOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('expirationOption')
                            <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-3">
                        <div>
                            <flux:heading>{{ __('Permissions') }}</flux:heading>
                            <flux:subheading class="mt-1">{{ __('Every section gets read and write permissions. Add new sections to the API key config so they appear here automatically.') }}</flux:subheading>
                        </div>

                        <div class="space-y-4">
                            @foreach (config('api_keys.resources', []) as $resource => $label)
                                <div wire:key="permission-group-{{ $resource }}" class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-950/40">
                                    <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ __($label) }}</div>

                                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                        @foreach ($this->permissionGroups[$resource] ?? [] as $permission => $actionLabel)
                                            <label wire:key="permission-{{ $permission }}" class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 bg-white px-4 py-3 text-sm text-zinc-800 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                                <span>{{ __($actionLabel) }}</span>
                                                <input
                                                    wire:model="selectedPermissions"
                                                    type="checkbox"
                                                    value="{{ $permission }}"
                                                    class="h-4 w-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100"
                                                >
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @error('selectedPermissions')
                            <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                        @error('selectedPermissions.*')
                            <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <flux:button variant="primary" type="submit">{{ __('Create API key') }}</flux:button>
                </form>
            </div>

        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <flux:heading size="lg">{{ __('Issued keys') }}</flux:heading>
                    <flux:subheading class="mt-2">{{ __('Review ownership, expiry, and permissions before the API endpoints go live.') }}</flux:subheading>
                </div>

                <x-action-message on="api-key-revoked">{{ __('API key revoked.') }}</x-action-message>
            </div>

            @if ($this->apiKeys->isEmpty())
                <p class="mt-6 rounded-lg border border-dashed border-zinc-300 px-4 py-6 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                    {{ trim($search) !== '' ? __('No API keys match your search.') : __('No API keys have been created yet.') }}
                </p>
            @else
                <div class="mt-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                        <thead>
                            <tr class="text-left text-zinc-500 dark:text-zinc-400">
                                <th class="pb-3 font-medium">{{ __('Key') }}</th>
                                <th class="pb-3 font-medium">{{ __('Owner') }}</th>
                                <th class="pb-3 font-medium">{{ __('Permissions') }}</th>
                                <th class="pb-3 font-medium">{{ __('Expires') }}</th>
                                <th class="pb-3 font-medium">{{ __('Status') }}</th>
                                <th class="pb-3 font-medium">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            @foreach ($this->apiKeys as $apiKey)
                                <tr wire:key="api-key-{{ $apiKey->id }}" class="align-top">
                                    <td class="py-4 pe-4">
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $apiKey->name }}</div>
                                        <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ __('Prefix: :prefix', ['prefix' => $apiKey->token_prefix]) }}
                                        </div>
                                        <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ __('Created by :name', ['name' => $apiKey->creator?->name ?? 'Unknown']) }}
                                        </div>
                                    </td>
                                    <td class="py-4 pe-4 text-zinc-600 dark:text-zinc-300">
                                        @if ($apiKey->isServiceOwned())
                                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $apiKey->service_name }}</div>
                                            <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Service API key') }}</div>
                                        @else
                                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $apiKey->user?->name }}</div>
                                            <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $apiKey->user?->email }}</div>
                                        @endif
                                    </td>
                                    <td class="py-4 pe-4">
                                        <div class="flex flex-wrap gap-2">
                                            @foreach ($apiKey->permissions ?? [] as $permission)
                                                <span class="inline-flex rounded-full bg-zinc-100 px-3 py-1 text-xs font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                                                    {{ $permission }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="py-4 pe-4 text-zinc-600 dark:text-zinc-300">
                                        {{ __($apiKey->expirationLabel()) }}
                                    </td>
                                    <td class="py-4 pe-4">
                                        @if ($apiKey->isRevoked())
                                            <span class="inline-flex rounded-full bg-rose-100 px-3 py-1 text-xs font-medium text-rose-700 dark:bg-rose-500/15 dark:text-rose-300">
                                                {{ __('Revoked') }}
                                            </span>
                                        @elseif ($apiKey->isExpired())
                                            <span class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
                                                {{ __('Expired') }}
                                            </span>
                                        @else
                                            <span class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                                                {{ __('Active') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-4">
                                        @if (! $apiKey->isRevoked())
                                            <flux:button type="button" variant="danger" wire:click="revokeApiKey({{ $apiKey->id }})">
                                                {{ __('Revoke') }}
                                            </flux:button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <flux:modal wire:model="showNewApiKeyModal" class="max-w-xl">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Copy this API key now') }}</flux:heading>
                <flux:subheading class="mt-2">
                    {{ __('This API key will not be shown again after you close this modal.') }}
                </flux:subheading>
            </div>

            @if ($newlyCreatedToken)
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-500/30 dark:bg-emerald-500/10">
                    <p class="text-sm text-emerald-900 dark:text-emerald-200">
                        {{ __('Store it somewhere secure before continuing.') }}
                    </p>
                    <div class="mt-3 overflow-x-auto rounded-lg border border-emerald-200 bg-white px-4 py-3 font-mono text-sm text-emerald-900 dark:border-emerald-500/30 dark:bg-zinc-950 dark:text-emerald-200">
                        {{ $newlyCreatedToken }}
                    </div>
                </div>
            @endif

            <div class="flex justify-end">
                <flux:button type="button" variant="primary" wire:click="$set('showNewApiKeyModal', false)">
                    {{ __('I have copied this key') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</section>
