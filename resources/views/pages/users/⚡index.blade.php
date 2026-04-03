<?php

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('User management')] class extends Component {
    use PasswordValidationRules;
    use ProfileValidationRules;

    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $role = User::ROLE_USER;

    /** @var array<int, string> */
    public array $editingRoles = [];

    public bool $showDeleteConfirmationModal = false;

    public ?int $deleteConfirmationUserId = null;

    public string $deleteConfirmationUserName = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $this->syncEditingRoles();
    }

    /**
     * Get the users for the management table.
     */
    #[Computed]
    public function users()
    {
        return User::query()
            ->orderBy('name')
            ->orderBy('email')
            ->get();
    }

    /**
     * Get the selectable roles.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function roles(): array
    {
        return User::roles();
    }

    /**
     * Create a new user.
     */
    public function createUser(): void
    {
        $validated = $this->validate([
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'role' => ['required', Rule::in(User::roles())],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => $validated['role'],
        ]);

        $this->reset(['name', 'email', 'password', 'password_confirmation']);
        $this->role = User::ROLE_USER;
        $this->syncEditingRoles();

        $this->dispatch('user-created');
    }

    /**
     * Update an existing user's role.
     */
    public function updateRole(int $userId): void
    {
        $user = User::findOrFail($userId);
        $role = $this->editingRoles[$userId] ?? null;

        validator(
            ['role' => $role],
            ['role' => ['required', Rule::in(User::roles())]]
        )->validate();

        if ($user->id === auth()->id() && $role !== User::ROLE_ADMIN && User::query()->where('role', User::ROLE_ADMIN)->count() === 1) {
            $this->addError("editingRoles.$userId", __('The last admin must remain an admin.'));

            return;
        }

        $user->update(['role' => $role]);

        $this->resetErrorBag("editingRoles.$userId");
        $this->syncEditingRoles();

        $this->dispatch('role-updated');
    }

    /**
     * Prompt to delete a standard user.
     */
    public function confirmUserDeletion(int $userId): void
    {
        $user = User::query()->findOrFail($userId);

        if ($user->isAdmin()) {
            return;
        }

        $this->deleteConfirmationUserId = $user->id;
        $this->deleteConfirmationUserName = $user->name;
        $this->showDeleteConfirmationModal = true;
    }

    /**
     * Delete the selected standard user.
     */
    public function deleteConfirmedUser(): void
    {
        if ($this->deleteConfirmationUserId === null) {
            return;
        }

        $user = User::query()->findOrFail($this->deleteConfirmationUserId);

        if ($user->isAdmin()) {
            $this->closeDeleteConfirmation();

            return;
        }

        $user->delete();

        unset($this->editingRoles[$user->id]);

        $this->syncEditingRoles();
        $this->closeDeleteConfirmation();

        $this->dispatch('user-deleted');
    }

    /**
     * Close the delete confirmation modal.
     */
    public function cancelDeleteConfirmation(): void
    {
        $this->closeDeleteConfirmation();
    }

    /**
     * Synchronize editable roles with the current user list.
     */
    private function syncEditingRoles(): void
    {
        $this->editingRoles = User::query()
            ->orderBy('name')
            ->orderBy('email')
            ->pluck('role', 'id')
            ->map(fn ($role) => (string) $role)
            ->all();
    }

    /**
     * Close and reset the delete confirmation modal state.
     */
    private function closeDeleteConfirmation(): void
    {
        $this->showDeleteConfirmationModal = false;
        $this->deleteConfirmationUserId = null;
        $this->deleteConfirmationUserName = '';
    }
}; ?>

<section class="w-full">
    <div class="relative mb-6 w-full">
        <flux:heading size="xl" level="1">{{ __('Users') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ __('Create accounts and manage who has admin access.') }}</flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,22rem)_minmax(0,1fr)]">
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Create user') }}</flux:heading>
            <flux:subheading class="mt-2">{{ __('Add a new user and assign their role immediately.') }}</flux:subheading>

            <form wire:submit="createUser" class="mt-6 space-y-4">
                <flux:input wire:model="name" :label="__('Name')" type="text" required autocomplete="name" />
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />
                <flux:input wire:model="password" :label="__('Password')" type="password" required autocomplete="new-password" viewable />
                <flux:input wire:model="password_confirmation" :label="__('Confirm password')" type="password" required autocomplete="new-password" viewable />

                <div>
                    <label for="role" class="mb-2 block text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ __('Role') }}</label>
                    <select
                        id="role"
                        wire:model="role"
                        class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 outline-none transition focus:border-zinc-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100"
                    >
                        @foreach ($this->roles as $availableRole)
                            <option value="{{ $availableRole }}">{{ ucfirst($availableRole) }}</option>
                        @endforeach
                    </select>
                    @error('role')
                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center gap-4 pt-2">
                    <flux:button variant="primary" type="submit">{{ __('Create user') }}</flux:button>
                    <x-action-message on="user-created">{{ __('User created.') }}</x-action-message>
                </div>
            </form>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <flux:heading size="lg">{{ __('Manage users') }}</flux:heading>
                    <flux:subheading class="mt-2">{{ __('Review users, update their roles, and remove standard accounts when needed.') }}</flux:subheading>
                </div>
                <div class="flex items-center gap-4">
                    <x-action-message on="role-updated">{{ __('Role updated.') }}</x-action-message>
                    <x-action-message on="user-deleted">{{ __('User deleted.') }}</x-action-message>
                </div>
            </div>

            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead>
                        <tr class="text-left text-zinc-500 dark:text-zinc-400">
                            <th class="pb-3 font-medium">{{ __('Name') }}</th>
                            <th class="pb-3 font-medium">{{ __('Email') }}</th>
                            <th class="pb-3 font-medium">{{ __('Role') }}</th>
                            <th class="pb-3 font-medium">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @foreach ($this->users as $user)
                            <tr wire:key="user-row-{{ $user->id }}" class="align-top">
                                <td class="py-4 pe-4">
                                    <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $user->name }}</div>
                                    @if ($user->id === auth()->id())
                                        <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('You') }}</div>
                                    @endif
                                </td>
                                <td class="py-4 pe-4 text-zinc-600 dark:text-zinc-300">{{ $user->email }}</td>
                                <td class="py-4 pe-4">
                                    <select
                                        wire:model="editingRoles.{{ $user->id }}"
                                        class="w-full min-w-32 rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 outline-none transition focus:border-zinc-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100"
                                    >
                                        @foreach ($this->roles as $availableRole)
                                            <option value="{{ $availableRole }}">{{ ucfirst($availableRole) }}</option>
                                        @endforeach
                                    </select>
                                    @error("editingRoles.$user->id")
                                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </td>
                                <td class="py-4">
                                    <div class="flex flex-wrap gap-2">
                                        <flux:button wire:click="updateRole({{ $user->id }})" variant="ghost">
                                            {{ __('Save role') }}
                                        </flux:button>

                                        @if (! $user->isAdmin())
                                            <flux:button type="button" variant="danger" wire:click="confirmUserDeletion({{ $user->id }})">
                                                {{ __('Delete') }}
                                            </flux:button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <flux:modal wire:model="showDeleteConfirmationModal" class="max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Are you sure you want to delete this user?') }}</flux:heading>
                <flux:subheading class="mt-2">
                    {{ __('This will permanently delete the standard user ":name". Admin accounts cannot be deleted from this page.', ['name' => $deleteConfirmationUserName]) }}
                </flux:subheading>
            </div>

            <div class="flex justify-end gap-3">
                <flux:button type="button" variant="ghost" wire:click="cancelDeleteConfirmation">
                    {{ __('Cancel') }}
                </flux:button>

                <flux:button type="button" variant="danger" wire:click="deleteConfirmedUser">
                    {{ __('Delete user') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</section>
