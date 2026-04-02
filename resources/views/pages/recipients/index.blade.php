<?php

use App\Models\Recipient;
use App\Models\RecipientGroup;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Recipient management')] class extends Component {
    public ?int $editingRecipientId = null;

    public ?int $editingGroupId = null;

    public string $name = '';

    public string $endpoint = '';

    public string $webhookAuthType = Recipient::WEBHOOK_AUTH_NONE;

    public string $webhookAuthUsername = '';

    public string $webhookAuthPassword = '';

    public string $webhookAuthToken = '';

    public string $webhookAuthHeaderName = '';

    public string $webhookAuthHeaderValue = '';

    /** @var array<int, string> */
    public array $selectedGroupIds = [];

    public string $groupName = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    /**
     * Get the recipients for the management table.
     */
    #[Computed]
    public function recipients()
    {
        return Recipient::query()
            ->with('groups:id,name')
            ->orderBy('name')
            ->orderBy('endpoint')
            ->get();
    }

    /**
     * Get the groups available for assignment.
     */
    #[Computed]
    public function groups()
    {
        return RecipientGroup::query()
            ->withCount('recipients')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get the supported webhook authentication types.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function webhookAuthenticationOptions(): array
    {
        return [
            Recipient::WEBHOOK_AUTH_NONE => 'None',
            Recipient::WEBHOOK_AUTH_BEARER => 'Bearer token',
            Recipient::WEBHOOK_AUTH_BASIC => 'Basic auth',
            Recipient::WEBHOOK_AUTH_HEADER => 'Custom header',
        ];
    }

    /**
     * Get the current form endpoint type.
     */
    #[Computed]
    public function formEndpointType(): ?string
    {
        return $this->detectEndpointType($this->endpoint);
    }

    /**
     * Create or update a recipient.
     */
    public function saveRecipient(): void
    {
        $validated = $this->validate($this->recipientRules());

        $recipient = Recipient::query()->updateOrCreate(
            ['id' => $this->editingRecipientId],
            $this->recipientPayload($validated)
        );

        $recipient->groups()->sync($validated['selectedGroupIds'] ?? []);

        $this->resetRecipientForm();
        $this->dispatch('recipient-saved');
    }

    /**
     * Populate the form for editing.
     */
    public function editRecipient(int $recipientId): void
    {
        $recipient = Recipient::query()
            ->with('groups:id')
            ->findOrFail($recipientId);

        $this->editingRecipientId = $recipient->id;
        $this->name = $recipient->name;
        $this->endpoint = $recipient->endpoint;
        $this->webhookAuthType = $recipient->webhook_auth_type;
        $this->webhookAuthUsername = $recipient->webhook_auth_username ?? '';
        $this->webhookAuthPassword = $recipient->webhook_auth_password ?? '';
        $this->webhookAuthToken = $recipient->webhook_auth_token ?? '';
        $this->webhookAuthHeaderName = $recipient->webhook_auth_header_name ?? '';
        $this->webhookAuthHeaderValue = $recipient->webhook_auth_header_value ?? '';
        $this->selectedGroupIds = $recipient->groups
            ->pluck('id')
            ->map(fn (int $groupId): string => (string) $groupId)
            ->all();

        $this->resetValidation();
    }

    /**
     * Delete a recipient.
     */
    public function deleteRecipient(int $recipientId): void
    {
        Recipient::query()->findOrFail($recipientId)->delete();

        if ($this->editingRecipientId === $recipientId) {
            $this->resetRecipientForm();
        }

        $this->dispatch('recipient-deleted');
    }

    /**
     * Cancel recipient editing.
     */
    public function cancelRecipientEditing(): void
    {
        $this->resetRecipientForm();
    }

    /**
     * Create or update a group.
     */
    public function saveGroup(): void
    {
        $validated = $this->validate($this->groupRules());

        RecipientGroup::query()->updateOrCreate(
            ['id' => $this->editingGroupId],
            ['name' => trim($validated['groupName'])]
        );

        $this->resetGroupForm();
        $this->dispatch('group-saved');
    }

    /**
     * Populate the group form for editing.
     */
    public function editGroup(int $groupId): void
    {
        $group = RecipientGroup::query()->findOrFail($groupId);

        $this->editingGroupId = $group->id;
        $this->groupName = $group->name;

        $this->resetValidation();
    }

    /**
     * Delete a group.
     */
    public function deleteGroup(int $groupId): void
    {
        RecipientGroup::query()->findOrFail($groupId)->delete();

        $this->selectedGroupIds = array_values(array_filter(
            $this->selectedGroupIds,
            fn (string $selectedGroupId): bool => (int) $selectedGroupId !== $groupId
        ));

        if ($this->editingGroupId === $groupId) {
            $this->resetGroupForm();
        }

        $this->dispatch('group-deleted');
    }

    /**
     * Cancel group editing.
     */
    public function cancelGroupEditing(): void
    {
        $this->resetGroupForm();
    }

    /**
     * Get the validation rules for recipients.
     *
     * @return array<string, array<int, mixed>>
     */
    private function recipientRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'endpoint' => [
                'required',
                'string',
                'max:2048',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value)) {
                        $fail(__('The endpoint must be a string.'));

                        return;
                    }

                    if (Str::startsWith($value, 'mailto://')) {
                        $email = trim(Str::after($value, 'mailto://'));

                        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $fail(__('Mail endpoints must use the format mailto://name@example.com.'));
                        }

                        return;
                    }

                    if (Str::startsWith($value, 'webhook://')) {
                        $target = trim(Str::after($value, 'webhook://'));
                        $normalizedTarget = Str::startsWith($target, ['http://', 'https://'])
                            ? $target
                            : 'https://'.ltrim($target, '/');

                        if ($target === '' || ! filter_var($normalizedTarget, FILTER_VALIDATE_URL)) {
                            $fail(__('Webhook endpoints must use the format webhook://example.com/path or webhook://https://example.com/path.'));
                        }

                        return;
                    }

                    $fail(__('Endpoints must start with mailto:// or webhook://.'));
                },
            ],
            'selectedGroupIds' => ['array'],
            'selectedGroupIds.*' => ['integer', Rule::exists('recipient_groups', 'id')],
            'webhookAuthType' => [
                Rule::requiredIf(fn (): bool => $this->isWebhookEndpoint($this->endpoint)),
                Rule::in(Recipient::webhookAuthTypes()),
            ],
            'webhookAuthUsername' => [
                Rule::requiredIf(fn (): bool => $this->isWebhookEndpoint($this->endpoint) && $this->webhookAuthType === Recipient::WEBHOOK_AUTH_BASIC),
                'nullable',
                'string',
                'max:255',
            ],
            'webhookAuthPassword' => [
                Rule::requiredIf(fn (): bool => $this->isWebhookEndpoint($this->endpoint) && $this->webhookAuthType === Recipient::WEBHOOK_AUTH_BASIC),
                'nullable',
                'string',
                'max:255',
            ],
            'webhookAuthToken' => [
                Rule::requiredIf(fn (): bool => $this->isWebhookEndpoint($this->endpoint) && $this->webhookAuthType === Recipient::WEBHOOK_AUTH_BEARER),
                'nullable',
                'string',
                'max:2048',
            ],
            'webhookAuthHeaderName' => [
                Rule::requiredIf(fn (): bool => $this->isWebhookEndpoint($this->endpoint) && $this->webhookAuthType === Recipient::WEBHOOK_AUTH_HEADER),
                'nullable',
                'string',
                'max:255',
            ],
            'webhookAuthHeaderValue' => [
                Rule::requiredIf(fn (): bool => $this->isWebhookEndpoint($this->endpoint) && $this->webhookAuthType === Recipient::WEBHOOK_AUTH_HEADER),
                'nullable',
                'string',
                'max:2048',
            ],
        ];
    }

    /**
     * Get the validation rules for groups.
     *
     * @return array<string, array<int, mixed>>
     */
    private function groupRules(): array
    {
        return [
            'groupName' => [
                'required',
                'string',
                'max:255',
                Rule::unique('recipient_groups', 'name')->ignore($this->editingGroupId),
            ],
        ];
    }

    /**
     * Build the model payload from validated input.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function recipientPayload(array $validated): array
    {
        $isWebhookEndpoint = $this->isWebhookEndpoint($validated['endpoint']);
        $webhookAuthType = $isWebhookEndpoint
            ? $validated['webhookAuthType']
            : Recipient::WEBHOOK_AUTH_NONE;

        return [
            'name' => trim($validated['name']),
            'endpoint' => trim($validated['endpoint']),
            'webhook_auth_type' => $webhookAuthType,
            'webhook_auth_username' => $isWebhookEndpoint && $webhookAuthType === Recipient::WEBHOOK_AUTH_BASIC
                ? trim((string) $validated['webhookAuthUsername'])
                : null,
            'webhook_auth_password' => $isWebhookEndpoint && $webhookAuthType === Recipient::WEBHOOK_AUTH_BASIC
                ? (string) $validated['webhookAuthPassword']
                : null,
            'webhook_auth_token' => $isWebhookEndpoint && $webhookAuthType === Recipient::WEBHOOK_AUTH_BEARER
                ? (string) $validated['webhookAuthToken']
                : null,
            'webhook_auth_header_name' => $isWebhookEndpoint && $webhookAuthType === Recipient::WEBHOOK_AUTH_HEADER
                ? trim((string) $validated['webhookAuthHeaderName'])
                : null,
            'webhook_auth_header_value' => $isWebhookEndpoint && $webhookAuthType === Recipient::WEBHOOK_AUTH_HEADER
                ? (string) $validated['webhookAuthHeaderValue']
                : null,
        ];
    }

    /**
     * Reset the recipient form to its default state.
     */
    private function resetRecipientForm(): void
    {
        $this->reset([
            'editingRecipientId',
            'name',
            'endpoint',
            'webhookAuthUsername',
            'webhookAuthPassword',
            'webhookAuthToken',
            'webhookAuthHeaderName',
            'webhookAuthHeaderValue',
            'selectedGroupIds',
        ]);

        $this->webhookAuthType = Recipient::WEBHOOK_AUTH_NONE;

        $this->resetValidation();
    }

    /**
     * Reset the group form to its default state.
     */
    private function resetGroupForm(): void
    {
        $this->reset(['editingGroupId', 'groupName']);
        $this->resetValidation();
    }

    /**
     * Determine the type of endpoint being edited.
     */
    private function detectEndpointType(string $endpoint): ?string
    {
        if (Str::startsWith($endpoint, 'mailto://')) {
            return Recipient::TYPE_MAIL;
        }

        if (Str::startsWith($endpoint, 'webhook://')) {
            return Recipient::TYPE_WEBHOOK;
        }

        return null;
    }

    /**
     * Determine whether the given endpoint is a webhook.
     */
    private function isWebhookEndpoint(string $endpoint): bool
    {
        return $this->detectEndpointType($endpoint) === Recipient::TYPE_WEBHOOK;
    }
}; ?>

<section class="w-full">
    <div class="relative mb-6 w-full">
        <flux:heading size="xl" level="1">{{ __('Recipients') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ __('Manage email and webhook recipients, organise them into groups, and configure webhook authentication when needed.') }}</flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="space-y-6">
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <flux:heading size="lg">
                            {{ $editingRecipientId ? __('Edit recipient') : __('Create recipient') }}
                        </flux:heading>
                        <flux:subheading class="mt-2">
                            {{ __('Recipients accept mailto:// addresses or webhook:// endpoints, and webhook recipients can carry their own authentication settings.') }}
                        </flux:subheading>
                    </div>

                    <x-action-message on="recipient-saved">{{ __('Recipient saved.') }}</x-action-message>
                </div>

                <form wire:submit="saveRecipient" class="mt-6 space-y-6">
                    <div class="grid gap-4 md:grid-cols-2">
                        <flux:input wire:model="name" :label="__('Name')" type="text" required />
                        <flux:input wire:model="endpoint" :label="__('Endpoint')" type="text" required placeholder="mailto://alerts@example.com" />
                    </div>

                    <div class="rounded-xl border border-dashed border-zinc-300 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-950/40">
                        <div class="flex flex-wrap items-center gap-3">
                            @if ($this->formEndpointType === \App\Models\Recipient::TYPE_MAIL)
                                <span class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                                    {{ __('Email recipient') }}
                                </span>
                                <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('Use mailto://name@example.com for mailbox-style recipients.') }}</p>
                            @elseif ($this->formEndpointType === \App\Models\Recipient::TYPE_WEBHOOK)
                                <span class="inline-flex rounded-full bg-sky-100 px-3 py-1 text-xs font-medium text-sky-700 dark:bg-sky-500/15 dark:text-sky-300">
                                    {{ __('Webhook recipient') }}
                                </span>
                                <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('Use webhook://example.com/path or webhook://https://example.com/path.') }}</p>
                            @else
                                <span class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
                                    {{ __('Choose a prefix') }}
                                </span>
                                <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('Start the endpoint with mailto:// or webhook:// so the destination can be validated and styled correctly.') }}</p>
                            @endif
                        </div>
                    </div>

                    @if ($this->formEndpointType === \App\Models\Recipient::TYPE_WEBHOOK)
                        <div class="space-y-4 rounded-xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-700 dark:bg-zinc-950/40">
                            <div>
                                <flux:heading>{{ __('Webhook authentication') }}</flux:heading>
                                <flux:subheading class="mt-1">{{ __('Choose how this webhook should authenticate when it is called.') }}</flux:subheading>
                            </div>

                            <div>
                                <label for="webhookAuthType" class="mb-2 block text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ __('Authentication type') }}</label>
                                <select
                                    id="webhookAuthType"
                                    wire:model="webhookAuthType"
                                    class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 outline-none transition focus:border-zinc-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100"
                                >
                                    @foreach ($this->webhookAuthenticationOptions as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('webhookAuthType')
                                    <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            @if ($webhookAuthType === \App\Models\Recipient::WEBHOOK_AUTH_BEARER)
                                <flux:input wire:model="webhookAuthToken" :label="__('Bearer token')" type="password" autocomplete="off" viewable />
                            @elseif ($webhookAuthType === \App\Models\Recipient::WEBHOOK_AUTH_BASIC)
                                <div class="grid gap-4 md:grid-cols-2">
                                    <flux:input wire:model="webhookAuthUsername" :label="__('Username')" type="text" autocomplete="off" />
                                    <flux:input wire:model="webhookAuthPassword" :label="__('Password')" type="password" autocomplete="off" viewable />
                                </div>
                            @elseif ($webhookAuthType === \App\Models\Recipient::WEBHOOK_AUTH_HEADER)
                                <div class="grid gap-4 md:grid-cols-2">
                                    <flux:input wire:model="webhookAuthHeaderName" :label="__('Header name')" type="text" placeholder="X-Webhook-Token" autocomplete="off" />
                                    <flux:input wire:model="webhookAuthHeaderValue" :label="__('Header value')" type="password" autocomplete="off" viewable />
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="space-y-3">
                        <div>
                            <flux:heading>{{ __('Groups') }}</flux:heading>
                            <flux:subheading class="mt-1">{{ __('Recipients can belong to multiple groups at once.') }}</flux:subheading>
                        </div>

                        @if ($this->groups->isEmpty())
                            <p class="rounded-lg border border-dashed border-zinc-300 px-4 py-3 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                                {{ __('Create a group from the side panel and it will appear here for assignment.') }}
                            </p>
                        @else
                            <div class="grid gap-3 md:grid-cols-2">
                                @foreach ($this->groups as $group)
                                    <label wire:key="group-option-{{ $group->id }}" class="flex items-center justify-between gap-3 rounded-xl border border-zinc-200 bg-white px-4 py-3 text-sm text-zinc-800 shadow-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                        <span>
                                            <span class="block font-medium">{{ $group->name }}</span>
                                            <span class="mt-1 block text-xs text-zinc-500 dark:text-zinc-400">
                                                {{ trans_choice('{0} No recipients|{1} :count recipient|[2,*] :count recipients', $group->recipients_count, ['count' => $group->recipients_count]) }}
                                            </span>
                                        </span>
                                        <input
                                            wire:model="selectedGroupIds"
                                            type="checkbox"
                                            value="{{ $group->id }}"
                                            class="h-4 w-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100"
                                        >
                                    </label>
                                @endforeach
                            </div>
                            @error('selectedGroupIds.*')
                                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center gap-4">
                        <flux:button variant="primary" type="submit">
                            {{ $editingRecipientId ? __('Save recipient') : __('Create recipient') }}
                        </flux:button>

                        @if ($editingRecipientId)
                            <flux:button type="button" variant="ghost" wire:click="cancelRecipientEditing">
                                {{ __('Cancel') }}
                            </flux:button>
                        @endif
                    </div>
                </form>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <flux:heading size="lg">{{ __('Manage recipients') }}</flux:heading>
                        <flux:subheading class="mt-2">{{ __('Review endpoints, confirm their authentication settings, and move recipients between groups.') }}</flux:subheading>
                    </div>

                    <div class="flex items-center gap-4">
                        <x-action-message on="recipient-deleted">{{ __('Recipient removed.') }}</x-action-message>
                    </div>
                </div>

                @if ($this->recipients->isEmpty())
                    <p class="mt-6 rounded-lg border border-dashed border-zinc-300 px-4 py-6 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                        {{ __('No recipients have been created yet.') }}
                    </p>
                @else
                    <div class="mt-6 overflow-x-auto">
                        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                            <thead>
                                <tr class="text-left text-zinc-500 dark:text-zinc-400">
                                    <th class="pb-3 font-medium">{{ __('Recipient') }}</th>
                                    <th class="pb-3 font-medium">{{ __('Endpoint') }}</th>
                                    <th class="pb-3 font-medium">{{ __('Authentication') }}</th>
                                    <th class="pb-3 font-medium">{{ __('Groups') }}</th>
                                    <th class="pb-3 font-medium">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                @foreach ($this->recipients as $recipient)
                                    <tr wire:key="recipient-{{ $recipient->id }}" class="align-top">
                                        <td class="py-4 pe-4">
                                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $recipient->name }}</div>
                                            <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                                {{ __('Saved as :endpoint', ['endpoint' => $recipient->endpoint]) }}
                                            </div>
                                        </td>
                                        <td class="py-4 pe-4">
                                            <div class="flex flex-col gap-2">
                                                <span class="inline-flex w-fit rounded-full px-3 py-1 text-xs font-medium {{ $recipient->isMailEndpoint() ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300' }}">
                                                    {{ __($recipient->endpointTypeLabel()) }}
                                                </span>
                                                <span class="break-all text-zinc-600 dark:text-zinc-300">{{ $recipient->endpointTarget() }}</span>
                                            </div>
                                        </td>
                                        <td class="py-4 pe-4 text-zinc-600 dark:text-zinc-300">
                                            {{ __($recipient->isWebhookEndpoint() ? $recipient->webhookAuthenticationSummary() : 'Not required') }}
                                        </td>
                                        <td class="py-4 pe-4">
                                            @if ($recipient->groups->isEmpty())
                                                <span class="text-zinc-500 dark:text-zinc-400">{{ __('No groups') }}</span>
                                            @else
                                                <div class="flex flex-wrap gap-2">
                                                    @foreach ($recipient->groups as $group)
                                                        <span class="inline-flex rounded-full bg-zinc-100 px-3 py-1 text-xs font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                                                            {{ $group->name }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </td>
                                        <td class="py-4">
                                            <div class="flex flex-wrap gap-2">
                                                <flux:button type="button" variant="ghost" wire:click="editRecipient({{ $recipient->id }})">
                                                    {{ __('Edit') }}
                                                </flux:button>
                                                <flux:button type="button" variant="danger" wire:click="deleteRecipient({{ $recipient->id }})">
                                                    {{ __('Delete') }}
                                                </flux:button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <aside class="space-y-6">
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <flux:heading size="lg">
                            {{ $editingGroupId ? __('Edit group') : __('Create group') }}
                        </flux:heading>
                        <flux:subheading class="mt-2">{{ __('Create shared groupings so recipients can be targeted together.') }}</flux:subheading>
                    </div>

                    <x-action-message on="group-saved">{{ __('Group saved.') }}</x-action-message>
                </div>

                <form wire:submit="saveGroup" class="mt-6 space-y-4">
                    <flux:input wire:model="groupName" :label="__('Group name')" type="text" required placeholder="Operations" />

                    <div class="flex flex-wrap items-center gap-4">
                        <flux:button variant="primary" type="submit">
                            {{ $editingGroupId ? __('Save group') : __('Create group') }}
                        </flux:button>

                        @if ($editingGroupId)
                            <flux:button type="button" variant="ghost" wire:click="cancelGroupEditing">
                                {{ __('Cancel') }}
                            </flux:button>
                        @endif
                    </div>
                </form>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <flux:heading size="lg">{{ __('Existing groups') }}</flux:heading>
                        <flux:subheading class="mt-2">{{ __('Rename or remove groups as your routing needs change.') }}</flux:subheading>
                    </div>

                    <x-action-message on="group-deleted">{{ __('Group removed.') }}</x-action-message>
                </div>

                @if ($this->groups->isEmpty())
                    <p class="mt-6 rounded-lg border border-dashed border-zinc-300 px-4 py-6 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                        {{ __('No groups have been created yet.') }}
                    </p>
                @else
                    <div class="mt-6 space-y-3">
                        @foreach ($this->groups as $group)
                            <div wire:key="group-row-{{ $group->id }}" class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-950/40">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $group->name }}</div>
                                        <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ trans_choice('{0} No recipients assigned|{1} :count recipient assigned|[2,*] :count recipients assigned', $group->recipients_count, ['count' => $group->recipients_count]) }}
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap gap-2">
                                        <flux:button type="button" variant="ghost" wire:click="editGroup({{ $group->id }})">
                                            {{ __('Edit') }}
                                        </flux:button>
                                        <flux:button type="button" variant="danger" wire:click="deleteGroup({{ $group->id }})">
                                            {{ __('Delete') }}
                                        </flux:button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </aside>
    </div>
</section>
