<?php

use App\Models\Recipient;
use App\Models\RecipientGroup;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Recipient group management')] class extends Component {
    public string $search = '';

    public ?int $editingGroupId = null;

    public string $groupName = '';

    /** @var array<int, string> */
    public array $selectedRecipientIds = [];

    public bool $showDeleteConfirmationModal = false;

    public ?int $deleteConfirmationId = null;

    public string $deleteConfirmationName = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    #[Computed]
    public function recipients()
    {
        return Recipient::query()
            ->orderBy('name')
            ->orderBy('endpoint')
            ->get();
    }

    #[Computed]
    public function recipientGroups()
    {
        $groups = RecipientGroup::query()
            ->with('recipients:id,name,endpoint')
            ->withCount('recipients')
            ->orderBy('name')
            ->get();

        if ($this->searchTerm() === '') {
            return $groups;
        }

        return $groups
            ->filter(fn (RecipientGroup $group): bool => $this->matchesSearch([
                $group->name,
                $group->recipients->pluck('name')->all(),
                $group->recipients->pluck('endpoint')->all(),
            ]))
            ->values();
    }

    public function saveGroup(): void
    {
        $validated = $this->validate($this->groupRules());

        $group = RecipientGroup::query()->updateOrCreate(
            ['id' => $this->editingGroupId],
            ['name' => trim($validated['groupName'])],
        );

        $group->recipients()->sync($validated['selectedRecipientIds'] ?? []);

        $this->resetGroupForm();
        $this->dispatch('group-saved');
    }

    public function editGroup(int $groupId): void
    {
        $group = RecipientGroup::query()
            ->with('recipients:id')
            ->findOrFail($groupId);

        $this->editingGroupId = $group->id;
        $this->groupName = $group->name;
        $this->selectedRecipientIds = $group->recipients
            ->pluck('id')
            ->map(fn (int $recipientId): string => (string) $recipientId)
            ->all();

        $this->resetValidation();
        $this->dispatch('focus-form', form: 'recipient-group');
    }

    public function confirmGroupDeletion(int $groupId): void
    {
        $group = RecipientGroup::query()->findOrFail($groupId);

        $this->deleteConfirmationId = $group->id;
        $this->deleteConfirmationName = $group->name;
        $this->showDeleteConfirmationModal = true;
    }

    public function cancelGroupEditing(): void
    {
        $this->resetGroupForm();
    }

    public function deleteConfirmedItem(): void
    {
        if ($this->deleteConfirmationId !== null) {
            RecipientGroup::query()->findOrFail($this->deleteConfirmationId)->delete();

            if ($this->editingGroupId === $this->deleteConfirmationId) {
                $this->resetGroupForm();
            }

            $this->dispatch('group-deleted');
        }

        $this->closeDeleteConfirmation();
    }

    public function cancelDeleteConfirmation(): void
    {
        $this->closeDeleteConfirmation();
    }

    /**
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
            'selectedRecipientIds' => ['array'],
            'selectedRecipientIds.*' => ['integer', Rule::exists('recipients', 'id')],
        ];
    }

    private function resetGroupForm(): void
    {
        $this->reset([
            'editingGroupId',
            'groupName',
            'selectedRecipientIds',
        ]);

        $this->resetValidation();
    }

    private function closeDeleteConfirmation(): void
    {
        $this->showDeleteConfirmationModal = false;
        $this->deleteConfirmationId = null;
        $this->deleteConfirmationName = '';
    }

    private function searchTerm(): string
    {
        return Str::lower(trim($this->search));
    }

    /**
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
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">{{ __('Recipient groups') }}</flux:heading>
                <flux:subheading size="lg" class="mb-6">{{ __('Create recipient groups, review their current members, and manage recipient membership from the group side.') }}</flux:subheading>
            </div>

            <flux:button variant="ghost" :href="route('recipients.index')" wire:navigate>
                {{ __('Manage recipients') }}
            </flux:button>
        </div>
        <flux:separator variant="subtle" />
    </div>

    <div
        x-data="{ isStuck: false, updateStickyState() { this.isStuck = this.$el.getBoundingClientRect().top <= 16 && window.scrollY > 0; } }"
        x-init="updateStickyState()"
        x-on:scroll.window.throttle.50ms="updateStickyState()"
        x-on:resize.window.throttle.50ms="updateStickyState()"
        :class="isStuck ? 'shadow-lg shadow-zinc-900/10 dark:shadow-black/30' : 'shadow-sm'"
        class="sticky top-4 z-20 mb-6 rounded-xl border border-zinc-200 bg-white/95 p-4 backdrop-blur transition-shadow duration-200 sm:p-6 dark:border-zinc-700 dark:bg-zinc-900/95"
    >
        <flux:input
            wire:model.live.debounce.300ms="search"
            :label="__('Search recipient groups')"
            type="search"
            :placeholder="__('Search by group or member recipient')"
        />
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,26rem)_minmax(0,1fr)]">
        <div class="min-w-0 space-y-6">
            <div
                x-data="{ highlight: false, timeout: null, focusForm() { this.$el.scrollIntoView({ behavior: 'smooth', block: 'start', inline: 'nearest' }); this.$nextTick(() => this.$el.querySelector('input, select, textarea, button')?.focus({ preventScroll: true })); this.highlight = true; if (this.timeout) { clearTimeout(this.timeout); } this.timeout = setTimeout(() => { this.highlight = false }, 2200); } }"
                x-on:focus-form.window="if ($event.detail.form === 'recipient-group') { focusForm() }"
                :class="{ 'ring-2 ring-sky-400/70 ring-offset-2 ring-offset-white shadow-lg shadow-sky-500/10 animate-pulse dark:ring-sky-300/60 dark:ring-offset-zinc-900': highlight }"
                class="min-w-0 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm transition-all duration-300 sm:p-6 dark:border-zinc-700 dark:bg-zinc-900"
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <flux:heading size="lg">{{ $editingGroupId ? __('Edit recipient group') : __('Create recipient group') }}</flux:heading>
                        <flux:subheading class="mt-2">{{ __('Use groups to organise recipients and adjust membership without leaving the group workflow.') }}</flux:subheading>
                    </div>

                    <x-action-message on="group-saved">{{ __('Group saved.') }}</x-action-message>
                </div>

                <form wire:submit="saveGroup" class="mt-6 space-y-5">
                    <flux:input wire:model="groupName" :label="__('Group name')" type="text" required placeholder="Operations" />

                    <div class="space-y-3 rounded-xl border border-zinc-200 bg-zinc-50 p-4 sm:p-5 dark:border-zinc-700 dark:bg-zinc-950/40">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <flux:heading>{{ __('Recipients') }}</flux:heading>
                                <flux:subheading class="mt-1">{{ __('Add or remove recipients in this group from here.') }}</flux:subheading>
                            </div>

                            <flux:button variant="subtle" size="sm" :href="route('recipients.index')" wire:navigate>
                                {{ __('Open recipients') }}
                            </flux:button>
                        </div>

                        @if ($this->recipients->isEmpty())
                            <p class="rounded-xl border border-dashed border-zinc-300 px-4 py-5 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">{{ __('Create recipients from the Recipients page and they will appear here for grouping.') }}</p>
                        @else
                            <div class="space-y-3">
                                @foreach ($this->recipients as $recipient)
                                    <label wire:key="recipient-option-{{ $recipient->id }}" class="flex min-w-0 items-center justify-between gap-3 rounded-xl border border-zinc-200 bg-white px-4 py-3 text-sm text-zinc-800 shadow-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                        <div class="min-w-0">
                                            <span class="block font-medium">{{ $recipient->name }}</span>
                                            <span class="mt-1 block text-xs text-zinc-500 dark:text-zinc-400">{{ __($recipient->endpointTypeLabel()) }} · {{ $recipient->endpointTarget() }}</span>
                                        </div>

                                        <input
                                            wire:model="selectedRecipientIds"
                                            type="checkbox"
                                            value="{{ $recipient->id }}"
                                            class="h-4 w-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100"
                                        >
                                    </label>
                                @endforeach
                            </div>
                            @error('selectedRecipientIds.*')
                                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center gap-3 pt-2">
                        <flux:button variant="primary" type="submit">{{ $editingGroupId ? __('Save recipient group') : __('Create recipient group') }}</flux:button>

                        @if ($editingGroupId)
                            <flux:button type="button" variant="ghost" wire:click="cancelGroupEditing">{{ __('Cancel') }}</flux:button>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <div class="min-w-0 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm sm:p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <flux:heading size="lg">{{ __('Managed recipient groups') }}</flux:heading>
                    <flux:subheading class="mt-2">{{ __('Recipient groups can be assigned to Service Groups or directly to Services.') }}</flux:subheading>
                </div>

                <x-action-message on="group-deleted">{{ __('Group removed.') }}</x-action-message>
            </div>

            @if ($this->recipientGroups->isEmpty())
                <p class="mt-6 rounded-lg border border-dashed border-zinc-300 px-4 py-6 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                    {{ trim($search) !== '' ? __('No recipient groups match your search.') : __('No recipient groups have been created yet.') }}
                </p>
            @else
                <div class="mt-6 grid gap-4 lg:grid-cols-2">
                    @foreach ($this->recipientGroups as $group)
                        <div wire:key="group-row-{{ $group->id }}" class="rounded-2xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-950/40">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $group->name }}</div>
                                    <div class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ trans_choice('{0} No recipients assigned|{1} :count recipient assigned|[2,*] :count recipients assigned', $group->recipients_count, ['count' => $group->recipients_count]) }}
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <flux:button type="button" variant="ghost" wire:click="editGroup({{ $group->id }})">{{ __('Edit') }}</flux:button>
                                    <flux:button type="button" variant="danger" wire:click="confirmGroupDeletion({{ $group->id }})">{{ __('Delete') }}</flux:button>
                                </div>
                            </div>

                            <div class="mt-4 flex flex-wrap gap-2">
                                @if ($group->recipients->isEmpty())
                                    <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No recipients assigned') }}</span>
                                @else
                                    @foreach ($group->recipients as $recipient)
                                        <span wire:key="group-recipient-chip-{{ $group->id }}-{{ $recipient->id }}" class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">{{ $recipient->name }}</span>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <flux:modal wire:model="showDeleteConfirmationModal" class="max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Are you sure you want to delete this group?') }}</flux:heading>
                <flux:subheading class="mt-2">{{ __('This will permanently delete the recipient group ":name". Linked recipients will remain, but the group assignment itself will be removed.', ['name' => $deleteConfirmationName]) }}</flux:subheading>
            </div>

            <div class="flex justify-end gap-3">
                <flux:button type="button" variant="ghost" wire:click="cancelDeleteConfirmation">
                    {{ __('Cancel') }}
                </flux:button>

                <flux:button type="button" variant="danger" wire:click="deleteConfirmedItem">
                    {{ __('Delete') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</section>
