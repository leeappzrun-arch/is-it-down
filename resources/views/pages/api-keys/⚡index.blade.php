<?php

use App\Concerns\ApiKeyValidation;
use App\Models\ApiKey;
use App\Support\ApiKeys\ApiKeyData;
use App\Support\ApiKeyPermissions;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('API key management')] class extends Component {
    use ApiKeyValidation;

    public string $search = '';

    public string $name = '';

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
     * Clear the plain-text token once the confirmation modal closes.
     */
    public function updatedShowNewApiKeyModal(bool $showModal): void
    {
        if (! $showModal) {
            $this->newlyCreatedToken = null;
        }
    }

    /**
     * Get the expiration presets available to admins.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function expirationOptions(): array
    {
        return $this->apiKeyExpirationOptions();
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
                    implode(' ', $apiKey->permissions ?? []),
                    $apiKey->expirationLabel(),
                    $apiKey->lastUsedLabel(),
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
        $validated = $this->validate($this->apiKeyCreationRules());
        $plainTextToken = ApiKey::generatePlainTextToken();

        ApiKey::query()->create(
            ApiKeyData::createPayload($validated, auth()->user(), $plainTextToken)
        );

        $this->newlyCreatedToken = $plainTextToken;
        $this->showNewApiKeyModal = true;
        $this->reset(['name']);
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
        <flux:subheading size="lg" class="mb-6">{{ __('Create personal API keys for the live REST API, then scope them to the exact areas this account should read or write.') }}</flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    <div class="sticky top-4 z-20 mb-6 rounded-xl border border-zinc-200 bg-white/95 p-4 shadow-sm backdrop-blur sm:p-6 dark:border-zinc-700 dark:bg-zinc-900/95">
        <flux:input
            wire:model.live.debounce.300ms="search"
            :label="__('Search API keys')"
            type="search"
            :placeholder="__('Search by key name, creator, permission, or status')"
        />
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,24rem)_minmax(0,1fr)]">
        <div class="space-y-6">
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <flux:heading size="lg">{{ __('Create API key') }}</flux:heading>
                        <flux:subheading class="mt-2">{{ __('This key will always belong to your account. Choose what it can read or write before sharing it with an integration.') }}</flux:subheading>
                    </div>

                    <x-action-message on="api-key-created">{{ __('API key created.') }}</x-action-message>
                </div>

                <form wire:submit="createApiKey" class="mt-6 space-y-5">
                    <flux:input wire:model="name" :label="__('Key name')" type="text" required placeholder="Production sync" />

                    <div class="rounded-xl border border-dashed border-zinc-300 bg-zinc-50 p-4 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-950/40 dark:text-zinc-300">
                        {{ __('This key will be assigned to :email.', ['email' => auth()->user()->email]) }}
                    </div>

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
                            <flux:subheading class="mt-1">{{ __('Choose which areas this key can read or write.') }}</flux:subheading>
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
                    <flux:subheading class="mt-2">{{ __('Review ownership, expiry, and permissions for every personal API key that has been issued.') }}</flux:subheading>
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
                                <th class="pb-3 font-medium">{{ __('Last Used') }}</th>
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
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $apiKey->user?->name }}</div>
                                        <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $apiKey->user?->email }}</div>
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
                                    <td class="py-4 pe-4 text-zinc-600 dark:text-zinc-300">
                                        {{ __($apiKey->lastUsedLabel()) }}
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
