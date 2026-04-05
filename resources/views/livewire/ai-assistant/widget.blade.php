<div
    x-data="{
        scrollToBottom() {
            this.$nextTick(() => {
                if (! this.$refs.messages) {
                    return;
                }

                this.$refs.messages.scrollTop = this.$refs.messages.scrollHeight;
            });
        },
    }"
    x-init="scrollToBottom()"
    x-on:ai-chat-updated.window="scrollToBottom()"
    class="fixed right-4 bottom-4 z-40 flex items-end gap-3"
>
    @if ($isOpen)
        <div class="w-[min(30rem,calc(100vw-1.5rem))] rounded-3xl border border-zinc-200 bg-white shadow-2xl shadow-zinc-900/15 dark:border-zinc-700 dark:bg-zinc-900 dark:shadow-black/40">
            <div class="flex items-start justify-between gap-3 border-b border-zinc-200 px-4 py-4 dark:border-zinc-700">
                <div>
                    <flux:heading size="lg">{{ __('Ask Dave') }}</flux:heading>
                    <flux:subheading class="mt-1">{{ __('Ask for help with outages, routing, or admin-safe changes.') }}</flux:subheading>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        wire:click="resetConversation"
                        class="inline-flex h-8 items-center rounded-full border border-zinc-200 px-3 text-xs font-medium whitespace-nowrap text-zinc-600 transition hover:border-zinc-300 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:text-zinc-100"
                    >
                        {{ __('New chat') }}
                    </button>

                    <button
                        type="button"
                        wire:click="toggleOpen"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-zinc-200 text-zinc-500 transition hover:border-zinc-300 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:text-zinc-100"
                        aria-label="{{ __('Close Dave') }}"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4">
                            <path fill-rule="evenodd" d="M4.22 4.22a.75.75 0 0 1 1.06 0L10 8.94l4.72-4.72a.75.75 0 1 1 1.06 1.06L11.06 10l4.72 4.72a.75.75 0 1 1-1.06 1.06L10 11.06l-4.72 4.72a.75.75 0 0 1-1.06-1.06L8.94 10 4.22 5.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
            </div>

            <div x-ref="messages" class="max-h-[26rem] space-y-3 overflow-y-auto px-4 py-4">
                @foreach ($messages as $index => $message)
                    <div wire:key="assistant-message-{{ $index }}" class="flex {{ $message['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-[85%] rounded-2xl px-4 py-3 text-sm leading-6 {{ $message['role'] === 'user' ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'border border-zinc-200 bg-zinc-50 text-zinc-700 dark:border-zinc-700 dark:bg-zinc-950/60 dark:text-zinc-200' }}">
                            {{ $message['content'] }}
                        </div>
                    </div>
                @endforeach

                <div wire:loading.flex wire:target="sendMessage" class="justify-start">
                    <div class="max-w-[85%] rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-950/60 dark:text-zinc-300">
                        {{ __('Thinking...') }}
                    </div>
                </div>
            </div>

            <form wire:submit="sendMessage" class="border-t border-zinc-200 px-4 py-4 dark:border-zinc-700">
                <flux:textarea
                    wire:model="draft"
                    x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); $wire.sendMessage(); scrollToBottom(); }"
                    rows="3"
                    :label="__('Message')"
                    :placeholder="__('For example: Why is Billing API down? or Create a user called Alex with alex@example.com')"
                />

                <div class="mt-3 flex items-center justify-between gap-3">
                    <p class="text-xs leading-5 text-zinc-500 dark:text-zinc-400">
                        {{ auth()->user()->isAdmin() ? __('Admin actions are enabled for your account.') : __('Read-only guidance is available for your account.') }}
                    </p>

                    <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="sendMessage">
                        {{ __('Send') }}
                    </flux:button>
                </div>
            </form>
        </div>
    @endif

    <flux:button
        type="button"
        variant="primary"
        square
        wire:click="toggleOpen"
        class="h-14 w-14 rounded-full shadow-xl shadow-zinc-900/20 dark:shadow-black/40"
        icon="sparkles"
        aria-label="{{ $isOpen ? __('Close Dave') : __('Open Dave') }}"
    />
</div>
