<?php

namespace Markc\AiAssistant\Livewire;

use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Markc\AiAssistant\Models\Conversation;
use Markc\AiAssistant\Models\Message;
use Markc\AiAssistant\Services\AnthropicService;

class ChatBox extends Component
{
    public ?int $conversationId = null;

    public string $input = '';

    public bool $isLoading = false;

    public function mount(?int $conversationId = null): void
    {
        $this->conversationId = $conversationId;
    }

    #[Computed]
    public function conversation(): ?Conversation
    {
        if (! $this->conversationId) {
            return null;
        }

        return Conversation::with('messages')
            ->where('user_id', auth()->id())
            ->find($this->conversationId);
    }

    #[Computed]
    public function messages(): Collection
    {
        return $this->conversation?->messages ?? collect();
    }

    public function send(): void
    {
        $input = trim($this->input);
        if (empty($input)) {
            return;
        }

        $this->isLoading = true;
        $this->input = '';

        try {
            if (! $this->conversationId) {
                $conversation = Conversation::create([
                    'user_id' => auth()->id(),
                    'model' => config('ai-assistant.model'),
                    'system_prompt' => config('ai-assistant.system_prompt'),
                ]);
                $this->conversationId = $conversation->id;
            }

            Message::create([
                'conversation_id' => $this->conversationId,
                'role' => 'user',
                'content' => $input,
            ]);

            unset($this->conversation, $this->messages);

            $service = app(AnthropicService::class);
            $messages = $this->conversation->getMessagesForApi();
            $response = $service->chat($messages);

            Message::create([
                'conversation_id' => $this->conversationId,
                'role' => 'assistant',
                'content' => $response,
            ]);

            $this->conversation->generateTitle();

        } catch (\Throwable $e) {
            if ($this->conversationId) {
                Message::create([
                    'conversation_id' => $this->conversationId,
                    'role' => 'assistant',
                    'content' => 'Error: '.$e->getMessage(),
                ]);
            }
        }

        $this->isLoading = false;
        unset($this->conversation, $this->messages);
        $this->dispatch('ai-assistant:message-sent');
    }

    public function newConversation(): void
    {
        $this->conversationId = null;
        $this->input = '';
        unset($this->conversation, $this->messages);
        $this->dispatch('ai-assistant:conversation-changed');
    }

    public function loadConversation(int $id): void
    {
        $this->conversationId = $id;
        unset($this->conversation, $this->messages);
    }

    public function render()
    {
        return view('ai-assistant::livewire.chat-box');
    }
}
