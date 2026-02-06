<?php

namespace Markc\AiAssistant\Livewire;

use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Markc\AiAssistant\Models\Conversation;
use Markc\AiAssistant\Models\Message;
use Markc\AiAssistant\Services\AnthropicService;

class ChatBox extends Component
{
    use WithFileUploads;

    public ?int $conversationId = null;

    public string $input = '';

    public string $selectedModel = '';

    public bool $isLoading = false;

    /** @var TemporaryUploadedFile|null */
    public $attachment = null;

    public function mount(?int $conversationId = null): void
    {
        $this->conversationId = $conversationId;
        $this->selectedModel = config('ai-assistant.model', 'claude-sonnet-4-5-20250929');
    }

    public function getAvailableModels(): array
    {
        return [
            'claude-haiku-4-5-20251001' => 'Haiku 4.5',
            'claude-sonnet-4-5-20250929' => 'Sonnet 4.5',
            'claude-opus-4-6' => 'Opus 4.6',
        ];
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
    public function chatMessages(): Collection
    {
        return $this->conversation?->messages ?? collect();
    }

    public function send(): void
    {
        $input = trim($this->input);
        if (empty($input) && ! $this->attachment) {
            return;
        }

        if ($this->attachment) {
            $this->validate([
                'attachment' => ['image', 'max:10240'],
            ]);
        }

        $this->isLoading = true;
        $this->input = '';

        try {
            if (! $this->conversationId) {
                $conversation = Conversation::create([
                    'user_id' => auth()->id(),
                    'model' => $this->selectedModel,
                    'system_prompt' => config('ai-assistant.system_prompt'),
                ]);
                $this->conversationId = $conversation->id;
            }

            $attachments = null;
            if ($this->attachment) {
                $path = $this->attachment->store('ai-attachments', 'public');
                $attachments = [[
                    'path' => $path,
                    'media_type' => $this->attachment->getMimeType(),
                    'original_name' => $this->attachment->getClientOriginalName(),
                ]];
                $this->attachment = null;
            }

            Message::create([
                'conversation_id' => $this->conversationId,
                'role' => 'user',
                'content' => $input ?: 'What is in this image?',
                'attachments' => $attachments,
            ]);

            unset($this->conversation, $this->chatMessages);

            $service = app(AnthropicService::class);
            $service->setModel($this->selectedModel);
            $messages = $this->conversation->getMessagesForApi();
            $response = $service->chat($messages);

            Message::create([
                'conversation_id' => $this->conversationId,
                'role' => 'assistant',
                'content' => $response['content'],
                'input_tokens' => $response['input_tokens'],
                'output_tokens' => $response['output_tokens'],
                'stop_reason' => $response['stop_reason'],
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
        unset($this->conversation, $this->chatMessages);
        $this->dispatch('ai-assistant:message-sent');
    }

    public function removeAttachment(): void
    {
        $this->attachment = null;
    }

    public function newConversation(): void
    {
        $this->conversationId = null;
        $this->input = '';
        unset($this->conversation, $this->chatMessages);
        $this->dispatch('ai-assistant:conversation-changed');
    }

    public function loadConversation(int $id): void
    {
        $this->conversationId = $id;
        unset($this->conversation, $this->chatMessages);
    }

    public function render()
    {
        return view('ai-assistant::livewire.chat-box');
    }
}
