<div style="display: flex; flex-direction: column; gap: 1.5rem;">
    {{-- Input Section --}}
    <x-filament::section>
        <x-slot name="heading">
            {{ $this->conversation?->title ?? 'New Chat' }}
        </x-slot>
        <x-slot name="afterHeader">
            @if ($this->conversation)
                <x-filament::icon-button
                    icon="heroicon-o-plus-circle"
                    label="New Chat"
                    wire:click="newConversation"
                    size="sm"
                    color="gray"
                />
            @endif
        </x-slot>

        <form wire:submit="send">
            <div style="display: flex; align-items: flex-end; gap: 0.75rem;">
                <div style="flex: 1;">
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="text"
                            wire:model="input"
                            placeholder="Type your message and press Enter..."
                            :disabled="$isLoading"
                            x-on:keydown.enter.prevent="$wire.send()"
                        />
                    </x-filament::input.wrapper>
                </div>
                <x-filament::button
                    type="submit"
                    icon="heroicon-o-paper-airplane"
                    :disabled="$isLoading"
                >
                    {{ $isLoading ? 'Sending...' : 'Send' }}
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>

    {{-- Loading Indicator --}}
    @if ($isLoading)
        <x-filament::section>
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <x-filament::loading-indicator class="h-5 w-5 text-primary-500" />
                <span class="text-sm text-gray-500 dark:text-gray-400">AI is thinking...</span>
            </div>
        </x-filament::section>
    @endif

    {{-- Messages (newest first) --}}
    @foreach ($this->messages->reverse() as $message)
        <x-filament::section>
            <x-slot name="heading">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    @if ($message->isUser())
                        <x-filament::icon icon="heroicon-o-user" class="h-5 w-5 text-gray-400" />
                        <span>You</span>
                    @else
                        <x-filament::icon icon="heroicon-o-sparkles" class="h-5 w-5 text-primary-500" />
                        <span>AI Assistant</span>
                    @endif
                </div>
            </x-slot>
            <x-slot name="afterHeader">
                <span class="text-xs text-gray-500 dark:text-gray-400">
                    {{ $message->created_at->format('g:i A') }}
                </span>
            </x-slot>

            @if ($message->isUser())
                <p class="text-sm text-gray-950 dark:text-white">{{ $message->content }}</p>
            @else
                <div class="fi-in-rich-text prose max-w-none text-sm dark:prose-invert">
                    {!! \Illuminate\Support\Str::markdown($message->content) !!}
                </div>
            @endif
        </x-filament::section>
    @endforeach

    {{-- Empty State --}}
    @if ($this->messages->isEmpty() && !$isLoading)
        <x-filament::section>
            <div style="text-align: center; padding: 1.5rem 0;">
                <x-filament::icon icon="heroicon-o-chat-bubble-bottom-center-text" class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" />
                <h3 class="mt-2 text-sm font-semibold text-gray-950 dark:text-white">No messages yet</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Type a message above to start chatting with the AI assistant.
                </p>
            </div>
        </x-filament::section>
    @endif
</div>
