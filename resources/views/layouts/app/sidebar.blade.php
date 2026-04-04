<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Monitoring')" class="grid">
                    <flux:sidebar.item icon="circle-gauge" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                    @if (auth()->user()->isAdmin())
                        <flux:sidebar.item icon="cpu-chip" :href="route('services.index')" :current="request()->routeIs('services.*')" wire:navigate>
                            {{ __('Services') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="folder" :href="route('recipients.index')" :current="request()->routeIs('recipients.*')" wire:navigate>
                            {{ __('Recipients') }}
                        </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>
            </flux:sidebar.nav>

            @if (auth()->user()->isAdmin())
                <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Access')" class="grid">
                    <flux:sidebar.item icon="users" :href="route('users.index')" :current="request()->routeIs('users.*')" wire:navigate>
                        {{ __('Users') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="key" :href="route('api-keys.index')" :current="request()->routeIs('api-keys.*')" wire:navigate>
                        {{ __('API Keys') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>
        @endif

            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Dig Deeper')" class="grid">
                    <flux:sidebar.item icon="square-library" :href="route('user-guide')" :current="request()->routeIs('user-guide')" wire:navigate>
                        {{ __('User Guide') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="square-code" :href="route('api-documentation')" :current="request()->routeIs('api-documentation')" wire:navigate>
                        {{ __('API Documentation') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="command-line" :href="route('api-playground')" :current="request()->routeIs('api-playground')" wire:navigate>
                        {{ __('API Playground') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="link" :href="route('webhook-documentation')" :current="request()->routeIs('webhook-documentation')" wire:navigate>
                        {{ __('Webhook Documentation') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        @if (auth()->user()->isAdmin())
                            <flux:menu.item :href="route('recipients.index')" icon="folder" wire:navigate>
                                {{ __('Recipients') }}
                            </flux:menu.item>
                            <flux:menu.item :href="route('services.index')" icon="cpu-chip" wire:navigate>
                                {{ __('Services') }}
                            </flux:menu.item>
                            <flux:menu.separator />
                            <flux:menu.item :href="route('users.index')" icon="users" wire:navigate>
                                {{ __('Users') }}
                            </flux:menu.item>
                            <flux:menu.item :href="route('api-keys.index')" icon="key" wire:navigate>
                                {{ __('API Keys') }}
                            </flux:menu.item>
                        @endif
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @if (auth()->check() && \App\Models\AiAssistantSetting::enabled() !== null)
            <livewire:ai-assistant.widget />
        @endif

        @fluxScripts
    </body>
</html>
