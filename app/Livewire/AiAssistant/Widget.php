<?php

namespace App\Livewire\AiAssistant;

use App\Models\AiAssistantSetting;
use App\Support\AiAssistant\AiAssistantOrchestrator;
use App\Support\AiAssistant\AiAssistantRules;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Session;
use Livewire\Component;
use Throwable;

class Widget extends Component
{
    #[Session]
    public bool $isOpen = false;

    public string $draft = '';

    /** @var array<int, array{role: string, content: string}> */
    #[Session]
    public array $messages = [];

    public string $routeName = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        abort_unless(Auth::check(), 403);

        $this->routeName = request()->route()?->getName() ?? '';

        if ($this->messages === []) {
            $this->resetConversation();
        }
    }

    /**
     * Toggle the widget open state.
     */
    public function toggleOpen(): void
    {
        $this->isOpen = ! $this->isOpen;

        if ($this->isOpen) {
            $this->dispatch('ai-chat-updated');
        }
    }

    /**
     * Reset the conversation to its initial greeting.
     */
    public function resetConversation(): void
    {
        $this->messages = [[
            'role' => 'assistant',
            'content' => AiAssistantRules::welcomeMessage(Auth::user()),
        ]];

        $this->dispatch('ai-chat-updated');
    }

    /**
     * Send a chat message to the configured provider.
     */
    public function sendMessage(AiAssistantOrchestrator $orchestrator): void
    {
        $settings = AiAssistantSetting::enabled();

        if ($settings === null) {
            return;
        }

        $validated = $this->validate([
            'draft' => ['required', 'string', 'max:4000'],
        ]);

        $userMessage = trim($validated['draft']);

        $this->messages[] = [
            'role' => 'user',
            'content' => $userMessage,
        ];

        $this->draft = '';
        $this->isOpen = true;
        $this->dispatch('ai-chat-updated');

        try {
            $assistantReply = $orchestrator->reply(
                Auth::user(),
                $this->messages,
                $this->routeName,
            );
        } catch (Throwable $throwable) {
            report($throwable);

            $assistantReply = 'I could not reach the configured AI provider just now. Please try again in a moment or review the AI Assistant settings.';
        }

        $this->messages[] = [
            'role' => 'assistant',
            'content' => $assistantReply,
        ];

        $this->dispatch('ai-chat-updated');
    }

    public function render(): View
    {
        return view('livewire.ai-assistant.widget');
    }
}
